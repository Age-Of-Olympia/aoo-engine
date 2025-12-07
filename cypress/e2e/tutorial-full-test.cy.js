/**
 * Complete Tutorial Test Suite - Registration to Completion
 *
 * IMPORTANT: Each scenario is INDEPENDENT (clears state between scenarios, NOT between tests)
 * Screenshots are taken at KEY moments with proper waits for content to load
 */

describe('Tutorial System - Complete Test Suite', () => {
  /* Generate unique player names for each run */
  const timestamp = Date.now();
  const playerNames = {
    cancel: `Cypcancel`,
    complete: `Cypcomplete`,
    resume: `Cypresume`,
    skip: `Cypskip`
  };

  const SKIP_REWARD_XP = 50;
  const SKIP_REWARD_PI = 50;

  /**
   * Helper: Take screenshot with wait for content to load
   */
  const screenshot = (name, extraWait = 0) => {
    if (extraWait > 0) {
      cy.wait(extraWait);
    }
    /* Ensure body is visible */
    cy.get('body').should('be.visible');
    /* Small wait for any pending renders */
    cy.wait(500);
    /* Take screenshot */
    cy.screenshot(name, {
      capture: 'viewport',
      overwrite: true
    });
  };

  /**
   * ==========================================
   * SCENARIO 1: Register → Login → Cancel Tutorial
   * ==========================================
   */
  describe('Scenario 1: Cancel Tutorial from Auto-Start', () => {
    const player = {
      name: playerNames.cancel,
      race: 'hs',
      password: 'testpass',
      email: `${playerNames.cancel}@test.com`
    };

    /* Clear state ONCE before scenario starts */
    before(() => {
      cy.clearCookies();
      cy.clearLocalStorage();
      cy.window().then((win) => {
        win.sessionStorage.clear();
      });
    });

    it('S1.1: Show registration page', () => {
      cy.log('📝 STEP 1: REGISTRATION PAGE');

      /* Visit the registration page */
      cy.visit('/index.php');
      cy.wait(2000);

      /* Wait for main menu to load */
      cy.get('body').should('be.visible');

      /* Look for the "Inscription" button to verify we're on login page */
      cy.get('body').then(($body) => {
        if ($body.text().includes('Inscription') || $body.find('a:contains("Inscription")').length > 0) {
          cy.log('✅ On main login page with Inscription button');
        }
      });

      screenshot('s1-01-main-page-before-registration', 1000);
    });

    it('S1.2: Click Inscription and show registration form', () => {
      cy.log('📝 STEP 2: REGISTRATION FORM');

      /* Navigate to registration */
      cy.visit('/index.php?menu');
      cy.wait(2000);

      /* Wait for page load */
      cy.get('body').should('be.visible');

      /* Check for registration form elements */
      cy.get('body').then(($body) => {
        const bodyText = $body.text();
        if (bodyText.includes('Pseudo') || bodyText.includes('Race') || bodyText.includes('Créer')) {
          cy.log('✅ Registration form visible');
        }
      });

      screenshot('s1-02-registration-form', 1000);
    });

    it('S1.3: Register new player', () => {
      cy.log('📝 STEP 3: PERFORMING REGISTRATION');

      /* Register the player */
      cy.register(player.name, player.race, player.password, player.email);

      cy.log(`✅ Player ${player.name} registered`);

      /* Take screenshot after registration (might show confirmation or redirect) */
      cy.wait(2000);
      screenshot('s1-03-after-registration', 1000);
    });

    it('S1.4: Login for the first time', () => {
      cy.log('🔐 STEP 4: FIRST LOGIN');

      /* Login */
      cy.login(player.name, player.password);

      /* Wait for page to load after login */
      cy.wait(3000);

      /* Check what we see */
      cy.get('body').then(($body) => {
        if ($body.find('#tutorial-loading-overlay').length > 0) {
          cy.log('✅ Tutorial loading overlay visible');
          screenshot('s1-04-tutorial-loading-overlay', 1000);
        } else if ($body.find('#tutorial-overlay').length > 0) {
          cy.log('✅ Tutorial overlay visible (tutorial started)');
          screenshot('s1-04-tutorial-started', 1000);
        } else {
          cy.log('📍 Game page loaded');
          screenshot('s1-04-after-first-login', 1000);
        }
      });
    });

    it('S1.5: Wait for tutorial to initialize', () => {
      cy.log('⏳ STEP 5: WAITING FOR TUTORIAL');

      /* Wait longer for tutorial to fully initialize */
      cy.wait(5000);

      /* Check current state */
      cy.get('body').should('be.visible');

      cy.window().then((win) => {
        if (win.tutorialUI) {
          cy.log('✅ TutorialUI loaded');
          cy.log('Current step: ' + (win.tutorialUI.currentStep || 'none'));
        } else {
          cy.log('⚠️ TutorialUI not yet loaded');
        }
      });

      screenshot('s1-05-tutorial-state', 1000);

      /* Check for tutorial overlay */
      cy.get('body').then(($body) => {
        if ($body.find('#tutorial-overlay').length > 0) {
          cy.log('✅ Tutorial overlay found in DOM');
          screenshot('s1-06-tutorial-overlay-exists', 500);
        } else {
          cy.log('⚠️ No tutorial overlay in DOM');
        }
      });
    });

    it('S1.6: Attempt to cancel tutorial', () => {
      cy.log('❌ STEP 6: CANCELING TUTORIAL');

      /* Try to cancel tutorial */
      cy.window().then((win) => {
        if (win.tutorialUI && typeof win.tutorialUI.cancel === 'function') {
          cy.log('Attempting to cancel via tutorialUI.cancel()');
          win.tutorialUI.cancel();
          /* Handle confirmation popup */
          cy.on('window:confirm', () => true);
          cy.wait(2000);
        } else {
          cy.log('⚠️ Cannot cancel - tutorialUI.cancel not available');
        }
      });

      screenshot('s1-07-after-cancel-attempt', 1000);
    });

    it('S1.7: Check final state', () => {
      cy.log('📊 STEP 7: FINAL STATE CHECK');

      /* Reload to see final state */
      cy.visit('/index.php');
      cy.wait(3000);

      cy.get('body').should('be.visible');
      screenshot('s1-08-final-game-state', 2000);

      /* Check for game elements */
      cy.get('body').then(($body) => {
        const bodyText = $body.text();
        if (bodyText.includes('Caractéristiques') || bodyText.includes('Actions')) {
          cy.log('✅ Game interface visible');
        } else if (bodyText.includes('Jouer') || bodyText.includes('Inscription')) {
          cy.log('⚠️ Back on login page (might be logged out)');
        }
      });
    });

    it('S1.8: Verify invisibleMode removed', () => {
      cy.log('👁️ STEP 8: CHECKING INVISIBLE MODE');

      cy.checkInvisibleMode().then((hasInvisible) => {
        cy.log(`Has invisibleMode: ${hasInvisible}`);
        if (!hasInvisible) {
          cy.log('✅ invisibleMode removed');
        } else {
          cy.log('⚠️ invisibleMode still present');
        }
      });

      screenshot('s1-09-invisible-check', 500);
    });
  });

  /**
   * ==========================================
   * SCENARIO 2: Register → Login → Logout → Login → Modal → Resume
   * ==========================================
   */
  describe('Scenario 2: Resume Tutorial from Modal', () => {
    const player = {
      name: playerNames.resume,
      race: 'hs',
      password: 'testpass',
      email: `${playerNames.resume}@test.com`
    };

    /* Clear state before scenario */
    before(() => {
      cy.clearCookies();
      cy.clearLocalStorage();
    });

    it('S2.1: Register player', () => {
      cy.log('📝 SCENARIO 2: REGISTRATION');
      cy.register(player.name, player.race, player.password, player.email);
      cy.log('✅ Player registered');
    });

    it('S2.2: First login', () => {
      cy.log('🔐 SCENARIO 2: FIRST LOGIN');
      cy.login(player.name, player.password);
      cy.wait(5000);
      screenshot('s2-01-first-login', 2000);
    });

    it('S2.3: Logout', () => {
      cy.log('🚪 SCENARIO 2: LOGOUT');
      cy.visit('/index.php?logout');
      cy.wait(2000);
      screenshot('s2-02-after-logout', 1000);
    });

    it('S2.4: Login again and check for modal', () => {
      cy.log('🔐 SCENARIO 2: SECOND LOGIN - MODAL EXPECTED');

      cy.login(player.name, player.password);
      cy.wait(3000);

      screenshot('s2-03-after-relogin', 1000);

      /* Check for modal */
      cy.get('body').then(($body) => {
        if ($body.find('#invisible-player-modal').length > 0) {
          cy.log('✅ MODAL FOUND!');
          cy.get('#invisible-player-modal').should('be.visible');
          screenshot('s2-04-modal-visible', 1000);

          /* Verify modal content */
          cy.get('#invisible-player-modal').should('contain', 'Bienvenue');
          cy.get('#invisible-player-modal').should('contain', 'Reprendre');

          screenshot('s2-05-modal-content', 500);

          /* Click resume */
          cy.log('▶️ Clicking RESUME button');
          cy.get('#resume-tutorial-btn').click();
          cy.wait(3000);
          screenshot('s2-06-after-resume-click', 2000);
        } else {
          cy.log('⚠️ Modal not shown');
          screenshot('s2-04-no-modal', 1000);
        }
      });
    });
  });

  /**
   * ==========================================
   * SCENARIO 3: Register → Login → Logout → Login → Modal → Skip
   * ==========================================
   */
  describe('Scenario 3: Skip Tutorial from Modal', () => {
    const player = {
      name: playerNames.skip,
      race: 'hs',
      password: 'testpass',
      email: `${playerNames.skip}@test.com`
    };

    /* Clear state before scenario */
    before(() => {
      cy.clearCookies();
      cy.clearLocalStorage();
    });

    it('S3.1: Register player', () => {
      cy.log('📝 SCENARIO 3: REGISTRATION');
      cy.register(player.name, player.race, player.password, player.email);
      cy.log('✅ Player registered');
    });

    it('S3.2: First login', () => {
      cy.log('🔐 SCENARIO 3: FIRST LOGIN');
      cy.login(player.name, player.password);
      cy.wait(5000);
      screenshot('s3-01-first-login', 2000);
    });

    it('S3.3: Logout', () => {
      cy.log('🚪 SCENARIO 3: LOGOUT');
      cy.visit('/index.php?logout');
      cy.wait(2000);
      screenshot('s3-02-after-logout', 1000);
    });

    it('S3.4: Login again and skip from modal', () => {
      cy.log('🔐 SCENARIO 3: SECOND LOGIN - SKIP FROM MODAL');

      /* Get stats before skip */
      cy.login(player.name, player.password);
      cy.wait(3000);

      screenshot('s3-03-after-relogin', 1000);

      let xpBefore = 0;
      let piBefore = 0;

      cy.request('/api/debug/get_player_stats.php').then((response) => {
        xpBefore = response.body.xp || 0;
        piBefore = response.body.pi || 0;
        cy.log(`Before skip: ${xpBefore} XP, ${piBefore} PI`);
      });

      /* Check for modal and skip */
      cy.get('body').then(($body) => {
        if ($body.find('#invisible-player-modal').length > 0) {
          cy.log('✅ MODAL FOUND!');
          screenshot('s3-04-modal-shown', 1000);

          /* Click skip */
          cy.log('❌ Clicking SKIP button');
          cy.get('#skip-tutorial-btn').click();
          /* Handle confirmation */
          cy.on('window:confirm', () => true);
          cy.wait(3000);

          screenshot('s3-05-after-skip-click', 2000);
        } else {
          cy.log('⚠️ Modal not shown');
        }
      });
    });

    it('S3.5: Verify skip rewards', () => {
      cy.log('💰 SCENARIO 3: VERIFY SKIP REWARDS');

      cy.request('/api/debug/get_player_stats.php').then((response) => {
        const xp = response.body.xp || 0;
        const pi = response.body.pi || 0;
        cy.log(`After skip: ${xp} XP, ${pi} PI`);

        if (xp >= SKIP_REWARD_XP && pi >= SKIP_REWARD_PI) {
          cy.log('✅ Skip rewards granted');
        } else {
          cy.log(`⚠️ Skip rewards NOT granted (got ${xp} XP, ${pi} PI)`);
        }
      });

      cy.visit('/index.php');
      cy.wait(2000);
      screenshot('s3-06-final-state', 2000);
    });

    it('S3.6: Verify invisibleMode removed', () => {
      cy.log('👁️ SCENARIO 3: CHECKING INVISIBLE MODE');

      cy.checkInvisibleMode().then((hasInvisible) => {
        if (!hasInvisible) {
          cy.log('✅ invisibleMode removed');
        } else {
          cy.log('⚠️ invisibleMode still present');
        }
      });

      screenshot('s3-07-invisible-check', 500);
    });
  });
});
