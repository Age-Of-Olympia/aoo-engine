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
    /* Check for errors in response */
    if (response.body.includes('error') || response.body.includes('déjà pris')) {
      cy.log('Registration failed: ' + response.body);
      throw new Error('Registration failed: ' + response.body);
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
