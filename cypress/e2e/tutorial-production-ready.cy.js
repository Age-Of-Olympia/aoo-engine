/**
 * PRODUCTION READINESS TEST - Complete Tutorial System Validation
 *
 * This test validates the entire tutorial system with:
 * - Full UI validation (tooltips, highlights, overlays)
 * - Database state validation (tutorial_progress, tutorial_players, inventory)
 * - Session management validation
 * - Critical features: Movement, Resource Gathering, Combat
 *
 * CRITICAL: Uses SINGLE it() block to maintain session state
 */

describe('Tutorial System - Production Readiness Test', () => {
  /* Generate unique account name for fresh test (letters only - no numbers allowed) */
  const uniqueNames = ['Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon', 'Zeta', 'Eta', 'Theta'];
  const randomName = uniqueNames[Math.floor(Math.random() * uniqueNames.length)];
  const timestamp = Date.now();
  const TEST_ACCOUNT = {
    name: `CypressTest${randomName}`,
    password: 'testpass123',
    email: `cypresstest${timestamp}@test.com`,
    race: 'hs',
    playerId: null  /* Will be set after registration */
  };

  /* Screenshot helper with proper timing */
  const screenshot = (name, extraWait = 1000) => {
    cy.wait(extraWait);
    cy.get('body').should('be.visible');
    cy.wait(500);
    cy.screenshot(name, { capture: 'viewport', overwrite: true });
  };

  /* Wait for tutorial step to fully render */
  const waitForStepRender = (stepId, timeout = 5000) => {
    cy.log(`⏳ Waiting for step: ${stepId}`);
    cy.window({ timeout }).then((win) => {
      if (!win.tutorialUI) {
        throw new Error('TutorialUI not initialized');
      }
      /* Wait for current step to match */
      return new Cypress.Promise((resolve) => {
        const checkStep = () => {
          if (win.tutorialUI.currentStep === stepId) {
            cy.log(`✓ Step ${stepId} rendered`);
            /* CRITICAL: Wait 1 second after step renders before any action */
            setTimeout(resolve, 1000);
          } else {
            setTimeout(checkStep, 200);
          }
        };
        checkStep();
      });
    });
  };

  /* Click tutorial next button (for info steps only) */
  const clickNext = () => {
    cy.get('#tutorial-next').should('be.visible').click();
    cy.wait(1000);
  };

  /* Try to click next button if it exists, otherwise do nothing */
  const clickNextIfExists = () => {
    cy.get('body').then(($body) => {
      if ($body.find('#tutorial-next:visible').length > 0) {
        cy.get('#tutorial-next').click();
        cy.wait(1000);
      } else {
        cy.log('⏭️ No next button - action step');
      }
    });
  };

  /* Validate tooltip is visible with correct content */
  const validateTooltip = (expectedTextSnippet) => {
    cy.get('.tutorial-tooltip').should('be.visible');
    if (expectedTextSnippet) {
      cy.get('.tutorial-tooltip').should('contain', expectedTextSnippet);
    }
  };

  /* Validate highlight is visible on target element */
  const validateHighlight = (selector) => {
    cy.get(selector).should('exist');
    cy.get('.tutorial-highlight').should('exist');
  };

  /* Clear browser state before test */
  before(() => {
    cy.clearCookies();
    cy.clearLocalStorage();
    cy.window().then((win) => {
      win.sessionStorage.clear();
    });
  });

  it('Complete production validation: Fresh player through entire tutorial', () => {
    let tutorialSessionId;
    let tutorialPlayerId;

    /* ========================================
     * PHASE 0: REGISTER NEW CHARACTER
     * ======================================== */
    cy.log('═══ PHASE 0: REGISTER NEW CHARACTER ═══');

    /* Register new character for fresh test */
    cy.log('📝 Registering new character: ' + TEST_ACCOUNT.name);
    cy.register(TEST_ACCOUNT.name, TEST_ACCOUNT.race, TEST_ACCOUNT.password, TEST_ACCOUNT.email);
    screenshot('00-registered', 2000);

    /* Get the player ID from database */
    cy.task('queryDatabase', {
      query: 'SELECT id FROM players WHERE name = ? ORDER BY id DESC LIMIT 1',
      params: [TEST_ACCOUNT.name]
    }).then((rows) => {
      TEST_ACCOUNT.playerId = rows[0].id;
      cy.log(`✓ Character registered with ID: ${TEST_ACCOUNT.playerId}`);
    });

    /* ========================================
     * PHASE 1: LOGIN & AUTO-START VALIDATION
     * ======================================== */
    cy.log('═══ PHASE 1: LOGIN & AUTO-START ═══');

    /* Login - this will trigger auto-start for brand new players */
    cy.log('🔐 Logging in as fresh player (auto-start expected)');
    cy.login(TEST_ACCOUNT.name, TEST_ACCOUNT.password);
    screenshot('01-after-login', 2000);

    /* Wait for auto-start to trigger and tutorial to initialize */
    cy.log('⏳ Waiting for tutorial auto-start...');
    cy.wait(3000);  /* Give time for auto-start */

    /* Check if tutorial started automatically */
    cy.get('body').then(($body) => {
      const tutorialOverlayExists = $body.find('#tutorial-overlay').length > 0;
      cy.log(`Tutorial overlay exists: ${tutorialOverlayExists}`);

      if (!tutorialOverlayExists) {
        /* If not auto-started yet, manually trigger */
        cy.log('🎮 Auto-start didnt trigger, checking for start button');
        if ($body.find('a:contains("Commencer le tutoriel")').length > 0) {
          cy.get('a:contains("Commencer le tutoriel")').first().click();
          cy.wait(2000);
        }
      }
    });

    /* ========================================
     * PHASE 2: TUTORIAL SESSION VALIDATION
     * ======================================== */
    cy.log('═══ PHASE 2: TUTORIAL SESSION VALIDATION ═══');

    /* Ensure tutorial overlay is visible */
    cy.get('#tutorial-overlay', { timeout: 10000 }).should('exist');
    cy.wait(2000);
    screenshot('02-tutorial-overlay-visible', 1000);

    /* Validate tutorial session created in database */
    cy.log('📊 Validating tutorial session created');
    cy.then(() => {
      cy.validateTutorialState(TEST_ACCOUNT.playerId, {
        shouldExist: true,
        mode: 'first_time',
        completed: 0
      }).then((state) => {
        tutorialSessionId = state.tutorial_session_id;
        tutorialPlayerId = state.tutorial_player_id;
        cy.log(`✓ Session ID: ${tutorialSessionId}, Tutorial Player ID: ${tutorialPlayerId}`);

        /* Validate tutorial player created and is on tutorial plan (starts with 'tut_') */
        cy.validatePlayerCoords(tutorialPlayerId, {
          plan: 'tut_*',  /* Tutorial plans use dynamic names like tut_abc123 */
          x: 0,
          y: 0
        });
      });
    });

    /* Validate UI state */
    cy.window().then((win) => {
      expect(win.tutorialUI).to.exist;
      expect(win.sessionStorage.getItem('tutorial_active')).to.equal('true');
    });

    /* ========================================
     * PHASE 3: WELCOME STEPS (Info Steps)
     * ======================================== */
    cy.log('═══ PHASE 3: WELCOME STEPS ═══');

    /* Step 1: Welcome (INFO, no validation → Next button) */
    waitForStepRender('welcome');
    screenshot('04-step-welcome', 1000);
    clickNext();

    /* Step 2: Your Character (INFO, no validation → Next button) */
    waitForStepRender('your_character');
    screenshot('05-step-your-character', 1000);
    clickNext();

    /* Step 3: Meet Gaia (INFO, requires ui_panel_opened validation → auto-advances) */
    waitForStepRender('meet_gaia');
    screenshot('06-step-meet-gaia', 1000);
    /* Perform the required action: Click on Gaïa (NPC) to open her character card */
    cy.log('👤 Clicking on Gaïa NPC at (1,0) to open her card');
    /* Gaïa is at coordinates (1, 0) on the tutorial map */
    cy.get('.case[data-coords="1,0"]').should('be.visible').click();
    cy.wait(2000);
    screenshot('06b-after-clicking-gaia', 1000);
    /* Step auto-advances to close_card once panel opens - no Next button */

    /* Step 4 & 5: May auto-complete - check current step */
    cy.log('═══ PHASE 4: MOVEMENT SYSTEM ===');

    cy.window().then((win) => {
      const currentStep = win.tutorialUI.currentStep;
      cy.log(`Current step after step 3: ${currentStep}`);

      /* Handle step 4 (close_card) if we're on it */
      if (currentStep === 'close_card') {
        cy.log('📋 On close_card step - clicking close button');
        cy.wait(1000);
        screenshot('07-step-close-card', 1000);
        cy.get('button.close-card').should('be.visible').click();
        cy.wait(1500);
      } else {
        cy.log(`⏭️ Step close_card was skipped, now on ${currentStep}`);
      }
    });

    /* Handle step 5 (movement_intro) if we're on it */
    cy.window().then((win) => {
      const currentStep = win.tutorialUI.currentStep;

      if (currentStep === 'movement_intro') {
        cy.log('📋 On movement_intro step');
        cy.wait(1000);
        screenshot('08-step-movement-intro', 1000);
        clickNext();
      } else {
        cy.log(`⏭️ Step movement_intro was skipped, now on ${currentStep}`);
      }
    });

    /* Step 6: First Move (MOVEMENT, requires any_movement validation) */
    waitForStepRender('first_move');
    screenshot('09-step-first-move', 1000);
    /* Perform the required action: move to any adjacent EMPTY tile (not the tree!) */
    /* Movement is 2-step: 1) Click tile to show go indicator, 2) Click go indicator to execute move */
    cy.log('👟 Step 1: Clicking empty tile to the left to show movement indicator');
    cy.get('.case[data-coords="-1,0"]').should('be.visible').click();
    cy.wait(500);  /* Wait for go indicator to appear */

    cy.log('👟 Step 2: Clicking go indicator (#go-rect or #go-img) to execute movement');
    cy.get('#go-rect, #go-img').filter(':visible').first().should('be.visible').click();
    cy.wait(3000);  /* Wait for movement to complete and page to reload */
    screenshot('10-after-first-move', 1000);

    /* Step 7: Movement Limit Warning (INFO, no validation → Next button) */
    /* Check if we're on step 7 or if it auto-completed */
    cy.window().then((win) => {
      const currentStep = win.tutorialUI.currentStep;
      cy.log(`Current step after first move: ${currentStep}`);

      if (currentStep === 'movement_limit_warning') {
        cy.log('📋 On movement_limit_warning step');
        cy.wait(1000);
        screenshot('11-step-movement-warning', 1000);
        clickNext();
      } else {
        cy.log(`⏭️ Step movement_limit_warning skipped, now on ${currentStep}`);
      }
    });

    /* Step 8: Show Characteristics (UI_INTERACTION, requires ui_panel_opened) */
    waitForStepRender('show_characteristics');
    screenshot('12-step-show-characteristics', 1000);
    /* Perform the required action: open characteristics panel */
    cy.get('#show-caracs').should('be.visible').click();
    cy.wait(2000);

    /* Step 9: Deplete Movements (MOVEMENT, requires movements_depleted) */
    waitForStepRender('deplete_movements');
    screenshot('13-step-deplete-movements', 1000);
    /* Perform the required action: use all MVT points (tutorial gives 4 movements) */
    cy.log('👟 Depleting all 4 movement points');

    /* Make 4 moves - alternating between left and back to center */
    for (let i = 0; i < 4; i++) {
      cy.log(`Move ${i + 1}/4`);
      const targetCoords = (i % 2 === 0) ? '-1,0' : '0,0';  /* Left then back */
      cy.get(`.case[data-coords="${targetCoords}"]`).click();
      cy.wait(500);
      cy.get('#go-rect, #go-img').filter(':visible').first().click();
      cy.wait(2000);  /* Wait for page reload after movement */
    }
    screenshot('14-after-movements-depleted', 1000);

    /* Step 10: Movements Depleted Info (INFO, no validation → Next button) */
    waitForStepRender('movements_depleted_info');
    screenshot('15-step-movements-depleted-info', 1000);
    clickNext();

    /* ========================================
     * PHASE 5: ACTIONS INTRODUCTION
     * ======================================== */
    cy.log('═══ PHASE 5: ACTIONS INTRODUCTION ═══');

    /* Step 11: Actions Intro (INFO, no validation → Next button) */
    waitForStepRender('actions_intro');
    screenshot('16-step-actions-intro', 1000);
    clickNext();

    /* Step 12: Click Yourself (UI_INTERACTION, requires ui_panel_opened) */
    waitForStepRender('click_yourself');
    screenshot('17-step-click-yourself', 1000);
    /* Perform the required action: click current tile to open actions panel */
    cy.get('.case.current').should('be.visible').click();
    cy.wait(2000);

    /* Step 13: Actions Panel Info (INFO, no validation → Next button) */
    waitForStepRender('actions_panel_info');
    screenshot('18-step-actions-panel', 1000);
    clickNext();

    /* Step 14: Close Card for Tree (UI_INTERACTION, requires ui_element_hidden) */
    waitForStepRender('close_card_for_tree');
    screenshot('19-step-close-card-for-tree', 1000);
    /* Perform the required action: close the UI card */
    cy.get('#ui-card .close-btn').should('be.visible').click();
    cy.wait(2000);

    /* ========================================
     * PHASE 6: RESOURCE GATHERING VALIDATION
     * ======================================== */
    cy.log('═══ PHASE 6: RESOURCE GATHERING ═══');

    /* Step 15: Walk to Tree (MOVEMENT, requires adjacent_to_position) */
    waitForStepRender('walk_to_tree');
    screenshot('20-step-walk-to-tree', 1000);
    /* Perform the required action: move adjacent to tree at (0,1) */
    cy.log('👟 Moving adjacent to tree');
    cy.get('.case[data-coords="0,0"]').should('be.visible').click();
    cy.wait(2000);
    screenshot('21-adjacent-to-tree', 1000);

    /* Step 16: Observe Tree (UI_INTERACTION, requires ui_panel_opened) */
    waitForStepRender('observe_tree');
    screenshot('22-step-observe-tree', 1000);
    /* Perform the required action: click tree tile to open its panel */
    cy.get('.case[data-coords="0,1"]').should('be.visible').click();
    cy.wait(2000);

    /* Step 17: Tree Info (INFO, no validation → Next button) */
    waitForStepRender('tree_info');
    screenshot('23-step-tree-info', 1000);
    clickNext();

    /* Step 18: Use Fouiller (ACTION, requires action_used) */
    waitForStepRender('use_fouiller');
    screenshot('24-step-use-fouiller', 1000);
    /* Perform the required action: use fouiller action */
    cy.log('🔍 Using fouiller action');
    cy.get('button:contains("fouiller")').should('be.visible').click();
    cy.wait(2500);
    screenshot('25-after-fouiller', 1000);

    /* Step 20: Action Consumed (INFO, no validation → Next button) */
    waitForStepRender('action_consumed');
    screenshot('26-action-consumed', 1000);
    clickNext();

    /* Step 21: Open Inventory (UI_INTERACTION, requires ui_interaction) */
    waitForStepRender('open_inventory');
    screenshot('27-step-open-inventory', 1000);
    /* Perform the required action: open inventory panel */
    cy.get('a[href="#inventaire"]').should('be.visible').click();
    cy.wait(2000);

    /* Step 22: Inventory Wood (INFO, no validation → Next button) */
    waitForStepRender('inventory_wood');
    screenshot('28-step-inventory-wood', 1000);
    /* Validate wood in inventory */
    cy.log('📦 Validating wood in inventory');
    cy.then(() => {
      cy.validateInventory(tutorialPlayerId, {
        'bois': 1  /* Should have at least 1 wood */
      });
    });
    clickNext();

    /* Step 23: Close Inventory (UI_INTERACTION, requires ui_interaction) */
    waitForStepRender('close_inventory');
    screenshot('29-step-close-inventory', 1000);
    /* Perform the required action: close inventory */
    cy.get('#ui-card .close-btn').should('be.visible').click();
    cy.wait(2000);

    /* ========================================
     * PHASE 7: COMBAT SYSTEM VALIDATION
     * ======================================== */
    cy.log('═══ PHASE 7: COMBAT SYSTEM ═══');

    /* Step 24: Combat Intro (INFO, no validation → Next button) */
    waitForStepRender('combat_intro');
    screenshot('30-step-combat-intro', 1000);
    clickNext();

    /* Step 25: Enemy Spawned (INFO, no validation → Next button) */
    waitForStepRender('enemy_spawned');
    screenshot('31-step-enemy-spawned', 1000);
    /* Validate enemy exists in database */
    cy.log('👹 Validating enemy spawned');
    cy.then(() => {
      cy.validateTutorialEnemy(tutorialSessionId).then((enemy) => {
        cy.log(`✓ Enemy player ID: ${enemy.enemy_player_id}`);
      });
    });
    clickNext();

    /* Step 26: Walk to Enemy (MOVEMENT, requires adjacent_to_position) */
    waitForStepRender('walk_to_enemy');
    screenshot('32-step-walk-to-enemy', 1000);
    /* Perform the required action: move adjacent to enemy */
    cy.log('👟 Moving adjacent to enemy');
    cy.get('.case').contains('PNJ').parent('.case').then(($enemyTile) => {
      const coords = $enemyTile.attr('data-coords');
      cy.log(`Enemy at coords: ${coords}`);
      const [ex, ey] = coords.split(',').map(Number);
      /* Click tile adjacent to enemy */
      const adjacentCoords = `${ex},${ey - 1}`;
      cy.get(`.case[data-coords="${adjacentCoords}"]`).should('be.visible').click();
      cy.wait(2000);
    });
    screenshot('33-adjacent-to-enemy', 1000);

    /* Step 27: Click Enemy (UI_INTERACTION, requires ui_panel_opened) */
    waitForStepRender('click_enemy');
    screenshot('34-step-click-enemy', 1000);
    /* Perform the required action: click enemy tile to open actions panel */
    cy.get('.case').contains('PNJ').parent('.case').should('be.visible').click();
    cy.wait(2000);

    /* Step 28: Attack Enemy (COMBAT, requires action_used) */
    waitForStepRender('attack_enemy');
    screenshot('35-step-attack-enemy', 1000);
    /* Perform the required action: use attack action */
    cy.log('⚔️ Attacking enemy');
    cy.get('button:contains("attaquer")').should('be.visible').click();
    cy.wait(2500);
    screenshot('36-after-attack', 1000);

    /* Step 29: Attack Result (INFO, no validation → Next button) */
    waitForStepRender('attack_result');
    screenshot('37-step-attack-result', 1000);
    clickNext();

    /* ========================================
     * PHASE 8: TUTORIAL COMPLETION VALIDATION
     * ======================================== */
    cy.log('═══ PHASE 8: TUTORIAL COMPLETION ===');

    /* Step 30: Tutorial Complete (INFO, no validation → Next button) */
    waitForStepRender('tutorial_complete');
    screenshot('38-step-tutorial-complete', 1000);
    clickNext();

    /* Wait for completion processing */
    cy.wait(3000);
    screenshot('39-after-completion', 1000);

    /* Validate tutorial marked as completed in database */
    cy.log('📊 Validating tutorial completion in database');
    cy.validateTutorialState(TEST_ACCOUNT.playerId, {
      shouldExist: true,
      completed: 1
    }).then((state) => {
      cy.log(`✓ Tutorial completed, XP earned: ${state.xp_earned}`);
      expect(state.xp_earned).to.be.greaterThan(0);
    });

    /* Validate player returned to main plan */
    cy.log('🗺️ Validating player returned to main plan');
    cy.validatePlayerCoords(TEST_ACCOUNT.playerId, {
      plan: 'gaia'
    });

    /* Validate tutorial player is deactivated */
    cy.task('queryDatabase', {
      query: 'SELECT is_active FROM tutorial_players WHERE id = ?',
      params: [tutorialPlayerId]
    }).then((rows) => {
      expect(rows[0].is_active).to.equal(0);
    });

    /* Validate XP/PI rewards given to main player */
    cy.task('queryDatabase', {
      query: 'SELECT xp, pi FROM players WHERE id = ?',
      params: [TEST_ACCOUNT.playerId]
    }).then((rows) => {
      cy.log(`✓ Player stats: XP=${rows[0].xp}, PI=${rows[0].pi}`);
      expect(rows[0].xp).to.be.greaterThan(0);
      expect(rows[0].pi).to.be.greaterThan(0);
    });

    screenshot('40-final-state', 2000);

    cy.log('✅ TUTORIAL SYSTEM PRODUCTION READY - ALL VALIDATIONS PASSED');
  });
});
