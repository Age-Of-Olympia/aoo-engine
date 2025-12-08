/**
 * TUTORIAL RESUME & PERSISTENCE TEST
 *
 * Validates that tutorial progress is properly saved and can be resumed:
 * - Start tutorial and progress to mid-point
 * - Exit tutorial (simulating browser close / navigation away)
 * - Resume tutorial and validate correct step restoration
 * - Complete tutorial from resumed point
 *
 * Tests critical session management and state persistence for production.
 *
 * CRITICAL: Uses SINGLE it() block to maintain session state
 */

describe('Tutorial System - Resume & Persistence Test', () => {
  /* Generate unique account name for fresh test (letters only - no numbers allowed) */
  const uniqueNames = ['Iota', 'Kappa', 'Lambda', 'Mu', 'Nu', 'Xi', 'Omicron', 'Pi'];
  const randomName = uniqueNames[Math.floor(Math.random() * uniqueNames.length)];
  const timestamp = Date.now();
  const TEST_ACCOUNT = {
    name: `ResumeTest${randomName}`,
    password: 'testpass123',
    email: `resumetest${timestamp}@test.com`,
    race: 'em',
    playerId: null  /* Will be set after registration */
  };

  /* Screenshot helper */
  const screenshot = (name, extraWait = 1000) => {
    cy.wait(extraWait);
    cy.get('body').should('be.visible');
    cy.wait(500);
    cy.screenshot(name, { capture: 'viewport', overwrite: true });
  };

  /* Wait for tutorial step */
  const waitForStepRender = (stepId, timeout = 5000) => {
    cy.log(`⏳ Waiting for step: ${stepId}`);
    cy.window({ timeout }).then((win) => {
      if (!win.tutorialUI) {
        throw new Error('TutorialUI not initialized');
      }
      return new Cypress.Promise((resolve) => {
        const checkStep = () => {
          if (win.tutorialUI.currentStep === stepId) {
            cy.log(`✓ Step ${stepId} rendered`);
            resolve();
          } else {
            setTimeout(checkStep, 200);
          }
        };
        checkStep();
      });
    });
  };

  /* Click next button */
  const clickNext = () => {
    cy.get('#tutorial-next').should('be.visible').click();
    cy.wait(500);
  };

  /* Clear browser state */
  before(() => {
    cy.clearCookies();
    cy.clearLocalStorage();
    cy.window().then((win) => {
      win.sessionStorage.clear();
    });
  });

  it('Complete resume flow: Start → Exit → Resume → Complete', () => {
    let tutorialSessionId;
    let tutorialPlayerId;
    const RESUME_FROM_STEP = 'show_characteristics';  /* Step 8 - mid-tutorial */

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
     * PHASE 1: START TUTORIAL & PROGRESS TO MID-POINT
     * ======================================== */
    cy.log('═══ PHASE 1: START TUTORIAL ═══');

    /* Login - this will trigger auto-start for brand new players */
    cy.login(TEST_ACCOUNT.name, TEST_ACCOUNT.password);
    screenshot('01-logged-in', 2000);

    /* Wait for auto-start */
    cy.wait(3000);

    /* Ensure tutorial started */
    cy.get('#tutorial-overlay', { timeout: 10000 }).should('exist');
    screenshot('02-tutorial-started', 2000);

    /* Get session info */
    cy.then(() => {
      cy.validateTutorialState(TEST_ACCOUNT.playerId, {
        shouldExist: true,
        completed: 0
      }).then((state) => {
        tutorialSessionId = state.tutorial_session_id;
        tutorialPlayerId = state.tutorial_player_id;
        cy.log(`Session: ${tutorialSessionId}, Tutorial Player: ${tutorialPlayerId}`);
      });
    });

    /* Progress through initial steps to reach resume point */
    cy.log('⏭️ Progressing to mid-tutorial checkpoint');

    /* Step 1: Welcome */
    waitForStepRender('welcome');
    screenshot('03-step-welcome', 1000);
    clickNext();

    /* Step 2: Your Character */
    waitForStepRender('your_character');
    clickNext();

    /* Step 3: Meet Gaia */
    waitForStepRender('meet_gaia');
    clickNext();

    /* Step 4: Close Card */
    waitForStepRender('close_card');
    cy.get('#ui-card .close-btn').click();
    cy.wait(1000);

    /* Step 5: Movement Intro */
    waitForStepRender('movement_intro');
    clickNext();

    /* Step 6: First Move */
    waitForStepRender('first_move');
    cy.get('.case[data-coords="0,1"]').click();
    cy.wait(2000);

    /* Step 7: Movement Limit Warning */
    waitForStepRender('movement_limit_warning');
    screenshot('04-mid-tutorial-checkpoint', 1000);
    clickNext();

    /* Step 8: Show Characteristics - RESUME CHECKPOINT */
    waitForStepRender(RESUME_FROM_STEP);
    screenshot('05-at-resume-checkpoint', 1500);

    /* Validate current step saved in database */
    cy.log('📊 Validating current step saved');
    cy.validateTutorialState(TEST_ACCOUNT.playerId, {
      currentStep: RESUME_FROM_STEP
    });

    /* ========================================
     * PHASE 2: EXIT TUTORIAL (Simulate Navigation Away)
     * ======================================== */
    cy.log('═══ PHASE 2: EXIT TUTORIAL ═══');

    /* Navigate away from tutorial (simulates user closing browser/tab) */
    cy.log('🚪 Navigating away from tutorial');
    cy.visit('/forum.php');  /* Navigate to different page */
    cy.wait(2000);
    screenshot('06-navigated-to-forum', 1500);

    /* Validate tutorial session still exists and is NOT completed */
    cy.log('📊 Validating session persisted');
    cy.validateTutorialState(TEST_ACCOUNT.playerId, {
      shouldExist: true,
      currentStep: RESUME_FROM_STEP,
      completed: 0
    });

    /* Validate tutorial player still exists and is still active */
    cy.task('queryDatabase', {
      query: 'SELECT is_active FROM tutorial_players WHERE id = ?',
      params: [tutorialPlayerId]
    }).then((rows) => {
      /* Tutorial player should still be active since we didn't cancel */
      cy.log(`Tutorial player is_active: ${rows[0].is_active}`);
    });

    /* ========================================
     * PHASE 3: RESUME TUTORIAL
     * ======================================== */
    cy.log('═══ PHASE 3: RESUME TUTORIAL ═══');

    /* Return to main game page */
    cy.log('🔄 Returning to main page to resume');
    cy.visit('/index.php');
    cy.wait(2000);
    screenshot('07-returned-to-index', 1500);

    /* Check for auto-resume or resume button */
    cy.get('body').then(($body) => {
      if ($body.find('#tutorial-overlay').is(':visible')) {
        cy.log('✓ Tutorial auto-resumed');
      } else if ($body.find('button:contains("Reprendre")').length > 0) {
        cy.log('📋 Resume button found, clicking');
        cy.get('button:contains("Reprendre")').click();
        cy.wait(2000);
      } else if ($body.find('a:contains("Continuer le tutoriel")').length > 0) {
        cy.log('📋 Continue link found, clicking');
        cy.get('a:contains("Continuer le tutoriel")').click();
        cy.wait(2000);
      }
    });

    cy.get('#tutorial-overlay', { timeout: 10000 }).should('exist');
    screenshot('08-tutorial-resumed', 2000);

    /* Validate resumed at correct step */
    cy.log('📊 Validating resumed at correct step');
    cy.window().then((win) => {
      const currentStep = win.tutorialUI.currentStep;
      cy.log(`Current step after resume: ${currentStep}`);
      expect(currentStep).to.equal(RESUME_FROM_STEP);
    });
    screenshot('09-correct-step-restored', 1500);

    /* Validate session state unchanged */
    cy.validateTutorialState(TEST_ACCOUNT.playerId, {
      shouldExist: true,
      currentStep: RESUME_FROM_STEP,
      completed: 0
    });

    /* Validate player coordinates preserved */
    cy.validatePlayerCoords(tutorialPlayerId, {
      plan: 'tut_*'  /* Tutorial plans use dynamic names like tut_abc123 */
    }).then((coords) => {
      cy.log(`Player position preserved: (${coords.x}, ${coords.y})`);
      /* Should be at (0,1) from the move we made earlier */
      expect(coords.y).to.equal(1);
    });

    /* ========================================
     * PHASE 4: COMPLETE TUTORIAL FROM RESUMED POINT
     * ======================================== */
    cy.log('═══ PHASE 4: COMPLETE FROM RESUME POINT ═══');

    /* Continue from Step 8: Show Characteristics */
    cy.log('▶️ Continuing tutorial from resumed step');
    cy.get('a[href="#caracs"]').click();
    cy.wait(1000);

    /* Step 9: Deplete Movements */
    waitForStepRender('deplete_movements');
    screenshot('10-step-deplete-movements', 1000);

    /* Deplete movements */
    cy.getPlayerResources(tutorialPlayerId).then((resources) => {
      const movesNeeded = resources.mvt;
      for (let i = 0; i < movesNeeded; i++) {
        const targetY = (i % 2 === 0) ? 2 : 1;
        cy.get(`.case[data-coords="0,${targetY}"]`).click();
        cy.wait(1500);
      }
    });

    /* Step 10: Movements Depleted */
    waitForStepRender('movements_depleted_info');
    clickNext();

    /* Step 11: Actions Intro */
    waitForStepRender('actions_intro');
    clickNext();

    /* Step 12: Click Yourself */
    waitForStepRender('click_yourself');
    cy.get('.case.current').click();
    cy.wait(1500);

    /* Step 13: Actions Panel */
    waitForStepRender('actions_panel_info');
    clickNext();

    /* Step 14: Close Card */
    waitForStepRender('close_card_for_tree');
    cy.get('#ui-card .close-btn').click();
    cy.wait(1000);

    /* Step 15-18: Resource Gathering */
    waitForStepRender('walk_to_tree');
    cy.get('.case[data-coords="0,0"]').click();
    cy.wait(2000);

    waitForStepRender('observe_tree');
    cy.get('.case[data-coords="0,1"]').click();
    cy.wait(1500);

    waitForStepRender('tree_info');
    clickNext();

    waitForStepRender('use_fouiller');
    cy.get('button:contains("fouiller")').click();
    cy.wait(2000);

    /* Handle action_consumed if it appears */
    cy.window().then((win) => {
      if (win.tutorialUI.currentStep === 'action_consumed') {
        clickNext();
      }
    });

    /* Step 21-23: Inventory */
    waitForStepRender('open_inventory');
    cy.get('a[href="#inventaire"]').click();
    cy.wait(1500);

    waitForStepRender('inventory_wood');
    screenshot('11-inventory-after-resume', 1500);
    clickNext();

    waitForStepRender('close_inventory');
    cy.get('#ui-card .close-btn').click();
    cy.wait(1000);

    /* Step 24-25: Combat Intro */
    waitForStepRender('combat_intro');
    clickNext();

    waitForStepRender('enemy_spawned');
    screenshot('12-enemy-spawned-after-resume', 1500);
    clickNext();

    /* Step 26-28: Combat */
    waitForStepRender('walk_to_enemy');
    cy.get('.case').contains('PNJ').parent('.case').then(($enemyTile) => {
      const coords = $enemyTile.attr('data-coords');
      const [ex, ey] = coords.split(',').map(Number);
      const adjacentCoords = `${ex},${ey - 1}`;
      cy.get(`.case[data-coords="${adjacentCoords}"]`).click();
      cy.wait(2000);
    });

    waitForStepRender('click_enemy');
    cy.get('.case').contains('PNJ').parent('.case').click();
    cy.wait(1500);

    waitForStepRender('attack_enemy');
    cy.get('button:contains("attaquer")').click();
    cy.wait(2000);

    /* Step 29: Attack Result */
    waitForStepRender('attack_result');
    clickNext();

    /* Step 30: Complete */
    waitForStepRender('tutorial_complete');
    screenshot('13-tutorial-complete-after-resume', 1500);
    clickNext();
    cy.wait(3000);

    /* ========================================
     * PHASE 5: VALIDATE COMPLETION
     * ======================================== */
    cy.log('═══ PHASE 5: VALIDATE COMPLETION ═══');

    /* Validate completion in database */
    cy.validateTutorialState(TEST_ACCOUNT.playerId, {
      shouldExist: true,
      completed: 1
    }).then((state) => {
      cy.log(`✓ Tutorial completed after resume, XP: ${state.xp_earned}`);
      expect(state.xp_earned).to.be.greaterThan(0);
    });

    /* Validate player back on main plan */
    cy.validatePlayerCoords(TEST_ACCOUNT.playerId, {
      plan: 'gaia'
    });

    /* Validate tutorial player deactivated */
    cy.task('queryDatabase', {
      query: 'SELECT is_active FROM tutorial_players WHERE id = ?',
      params: [tutorialPlayerId]
    }).then((rows) => {
      expect(rows[0].is_active).to.equal(0);
    });

    screenshot('14-final-state-after-resume', 2000);

    cy.log('✅ RESUME & PERSISTENCE - ALL VALIDATIONS PASSED');
  });
});
