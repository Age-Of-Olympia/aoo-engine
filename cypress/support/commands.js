// ***********************************************
// Custom Cypress Commands for Tutorial Testing
// ***********************************************

/**
 * Register command - creates a new player account
 * @param {string} name - Player name
 * @param {string} race - Player race (hs, elfe, nain, etc.)
 * @param {string} password - Player password
 * @param {string} email - Player email
 */
Cypress.Commands.add('register', (name, race, password, email) => {
  /* Submit registration via POST request */
  cy.request({
    method: 'POST',
    url: '/register.php',
    form: true,
    body: {
      name: name,
      race: race,
      psw1: password,
      psw2: password,
      mail: email
    },
    failOnStatusCode: false
  }).then((response) => {
    /* Known registration failure strings. We don't substring-match "error"
     * because xdebug deprecation warnings render with class="xdebug-error"
     * and unrelated env-level noise would otherwise bail the test. */
    const body = response.body || '';
    const knownFailures = [
      'Ce nom de personnage est déjà pris',
      'Ce courriel est déjà utilisé',
      'Erreur lors de l\'inscription',
      'mot de passe',  /* psw validation */
    ];
    const failure = knownFailures.find((msg) => body.includes(msg));
    if (failure) {
      cy.log('Registration failed: ' + failure);
      throw new Error('Registration failed (' + failure + '): ' + body.slice(0, 500));
    }
    cy.log('Registration successful for: ' + name);
  });

  /* Wait for registration to complete */
  cy.wait(2000);
});

/**
 * Login command - logs in a player
 * @param {string} playerName - Player name (not ID)
 * @param {string} password - Player password
 */
Cypress.Commands.add('login', (playerName, password) => {
  /* Use request to login via API and preserve cookies */
  cy.request({
    method: 'POST',
    url: '/login.php',
    form: true,
    body: {
      name: playerName,
      psw: password,
      footprint: 'cypress-test-' + Date.now()
    },
    failOnStatusCode: false
  }).then((response) => {
    /* Check for error messages */
    if (response.body.includes('Mauvais mot de passe') ||
        response.body.includes('Aucun personnage')) {
      throw new Error('Login failed: ' + response.body);
    }
    cy.log('Login successful for: ' + playerName);
  });

  /* Now visit index.php with the session cookie set */
  cy.visit('/index.php');

  /* Wait for page to actually load */
  cy.get('body', { timeout: 10000 }).should('exist');
  cy.wait(1000); /* Give page time to render */
});

/**
 * Wait for tutorial UI to be ready
 */
Cypress.Commands.add('waitForTutorial', () => {
  cy.window().its('tutorialUI').should('exist');
  cy.get('#tutorial-overlay').should('exist');
});

/**
 * Get current tutorial step
 */
Cypress.Commands.add('getTutorialStep', () => {
  return cy.window().then((win) => {
    return win.tutorialUI ? win.tutorialUI.currentStep : null;
  });
});

/**
 * Advance tutorial to next step
 */
Cypress.Commands.add('advanceTutorialStep', () => {
  return cy.window().then((win) => {
    if (win.tutorialUI) {
      return win.tutorialUI.next();
    }
  });
});

/**
 * Cancel tutorial
 */
Cypress.Commands.add('cancelTutorial', () => {
  return cy.window().then((win) => {
    if (win.tutorialUI) {
      return win.tutorialUI.cancel();
    }
  });
});

/**
 * Take a labeled screenshot with timestamp
 * @param {string} label - Screenshot label
 */
Cypress.Commands.add('takeScreenshot', (label) => {
  const timestamp = new Date().toISOString().replace(/:/g, '-').substring(0, 19);
  cy.screenshot(`${timestamp}_${label}`, {
    capture: 'fullPage',
    overwrite: true
  });
});

/**
 * Check if player has invisibleMode option
 */
Cypress.Commands.add('checkInvisibleMode', () => {
  return cy.request({
    method: 'POST',
    url: '/api/debug/check_invisible.php',
    failOnStatusCode: false
  }).then((response) => {
    return response.body.has_invisible || false;
  });
});

/**
 * Get player XP/PI from database
 */
Cypress.Commands.add('getPlayerStats', (playerId) => {
  return cy.request({
    method: 'POST',
    url: '/api/debug/get_player_stats.php',
    body: { player_id: playerId },
    failOnStatusCode: false
  }).then((response) => {
    return response.body;
  });
});

/**
 * Validate tutorial database state
 * Checks tutorial_progress, tutorial_players, and session state
 */
Cypress.Commands.add('validateTutorialState', (playerId, expectedState) => {
  return cy.task('queryDatabase', {
    query: `
      SELECT
        tp.tutorial_session_id,
        tp.current_step,
        tp.xp_earned,
        tp.completed,
        tp.tutorial_mode,
        tp.tutorial_version,
        tpl.player_id as tutorial_player_id,
        tpl.is_active
      FROM tutorial_progress tp
      LEFT JOIN tutorial_players tpl ON tp.tutorial_session_id = tpl.tutorial_session_id
      WHERE tp.player_id = ?
      ORDER BY tp.id DESC
      LIMIT 1
    `,
    params: [playerId]
  }).then((rows) => {
    if (expectedState.shouldExist && rows.length === 0) {
      throw new Error(`Expected tutorial session for player ${playerId} but found none`);
    }
    if (!expectedState.shouldExist && rows.length > 0) {
      throw new Error(`Expected no tutorial session for player ${playerId} but found one`);
    }
    if (rows.length > 0 && expectedState) {
      const state = rows[0];
      if (expectedState.currentStep && state.current_step !== expectedState.currentStep) {
        throw new Error(`Expected step ${expectedState.currentStep} but found ${state.current_step}`);
      }
      if (expectedState.completed !== undefined && state.completed !== expectedState.completed) {
        throw new Error(`Expected completed=${expectedState.completed} but found ${state.completed}`);
      }
      if (expectedState.mode && state.tutorial_mode !== expectedState.mode) {
        throw new Error(`Expected mode ${expectedState.mode} but found ${state.tutorial_mode}`);
      }
      return state;
    }
    return null;
  });
});

/**
 * Validate player inventory contains specific items
 */
Cypress.Commands.add('validateInventory', (playerId, expectedItems) => {
  return cy.task('queryDatabase', {
    query: `
      SELECT i.name, pi.quantity
      FROM players_items pi
      JOIN items i ON pi.item_id = i.id
      WHERE pi.player_id = ?
    `,
    params: [playerId]
  }).then((rows) => {
    const inventory = {};
    rows.forEach(row => {
      inventory[row.name] = row.quantity;
    });

    for (const [itemName, expectedQty] of Object.entries(expectedItems)) {
      const actualQty = inventory[itemName] || 0;
      if (actualQty < expectedQty) {
        throw new Error(`Expected at least ${expectedQty} ${itemName} but found ${actualQty}`);
      }
    }
    return inventory;
  });
});

/**
 * Validate player coordinates
 */
Cypress.Commands.add('validatePlayerCoords', (playerId, expectedCoords) => {
  return cy.task('queryDatabase', {
    query: `
      SELECT c.x, c.y, c.z, c.plan
      FROM players p
      JOIN coords c ON p.coords_id = c.id
      WHERE p.id = ?
    `,
    params: [playerId]
  }).then((rows) => {
    if (rows.length === 0) {
      throw new Error(`Player ${playerId} not found`);
    }
    const coords = rows[0];
    if (expectedCoords.x !== undefined && coords.x !== expectedCoords.x) {
      throw new Error(`Expected x=${expectedCoords.x} but found ${coords.x}`);
    }
    if (expectedCoords.y !== undefined && coords.y !== expectedCoords.y) {
      throw new Error(`Expected y=${expectedCoords.y} but found ${coords.y}`);
    }
    if (expectedCoords.plan) {
      /* Support wildcard matching (e.g., "tut_*" matches "tut_abc123") */
      if (expectedCoords.plan.includes('*')) {
        const pattern = new RegExp('^' + expectedCoords.plan.replace('*', '.*') + '$');
        if (!pattern.test(coords.plan)) {
          throw new Error(`Expected plan matching ${expectedCoords.plan} but found ${coords.plan}`);
        }
      } else if (coords.plan !== expectedCoords.plan) {
        throw new Error(`Expected plan=${expectedCoords.plan} but found ${coords.plan}`);
      }
    }
    return coords;
  });
});

/**
 * Validate tutorial enemy exists
 */
Cypress.Commands.add('validateTutorialEnemy', (sessionId) => {
  return cy.task('queryDatabase', {
    query: `
      SELECT enemy_player_id, enemy_coords_id
      FROM tutorial_enemies
      WHERE tutorial_session_id = ?
    `,
    params: [sessionId]
  }).then((rows) => {
    if (rows.length === 0) {
      throw new Error(`No tutorial enemy found for session ${sessionId}`);
    }
    return rows[0];
  });
});

/**
 * Get the player's current remaining PA and MVT, matching Player::getRemaining().
 *
 * Turn data lives in datas/private/players/<id>.turn.json (rewritten every time
 * get_caracs() runs), not in a DB column. The `readPlayerTurn` Node task reads
 * both turn.json and caracs.json and returns the same value getRemaining() would.
 */
Cypress.Commands.add('getPlayerResources', (playerId) => {
  return cy.task('readPlayerTurn', { playerId }).then((res) => {
    return { pa: res.pa, mvt: res.mvt };
  });
});
