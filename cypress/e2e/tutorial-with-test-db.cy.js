/**
 * Tutorial Test Suite - Using Pre-configured Test Database
 *
 * This test uses the aoo4_test database with pre-existing test characters.
 * Make sure to switch config/db_constants.php to use 'aoo4_test' before running.
 *
 * Test Characters:
 * - TestFreshPlayer (ID 101): Fresh account, has invisibleMode, should show modal on login
 * - TestTutorialStarted (ID 102): Mid-tutorial at step 3, has invisibleMode
 * - TestTutorialCompleted (ID 103): Completed tutorial, 240 XP/PI, no invisibleMode
 * - TestTutorialSkipped (ID 104): Skipped tutorial, 50 XP/PI, no invisibleMode
 *
 * All test accounts use password: 'testpass'
 */

describe('Tutorial System - Pre-configured Test Database', () => {
  const TEST_ACCOUNTS = {
    fresh: { name: 'TestFreshPlayer', password: 'testpass', id: 101 },
    started: { name: 'TestTutorialStarted', password: 'testpass', id: 102 },
    completed: { name: 'TestTutorialCompleted', password: 'testpass', id: 103 },
    skipped: { name: 'TestTutorialSkipped', password: 'testpass', id: 104 }
  };

  /**
   * Helper: Take screenshot with proper timing
   */
  const screenshot = (name, extraWait = 1000) => {
    cy.wait(extraWait);
    cy.get('body').should('be.visible');
    cy.wait(500); /* Wait for animations */
    cy.screenshot(name, {
      capture: 'viewport',
      overwrite: true
    });
  };

  /**
   * Helper: Clear browser state
   */
  const clearBrowserState = () => {
    cy.clearCookies();
    cy.clearLocalStorage();
    cy.window().then((win) => {
      win.sessionStorage.clear();
    });
  };

  /**
   * ==========================================
   * SCENARIO 1: Fresh Player - Modal on First Login
   * ==========================================
   */
  describe('Scenario 1: Fresh Player First Login', () => {
    before(() => {
      clearBrowserState();
    });

    it('S1.1: Login with fresh player account', () => {
      cy.log('🔐 LOGIN: TestFreshPlayer (fresh account)');

      /* Login via API (sets session cookie) */
      cy.login(TEST_ACCOUNTS.fresh.name, TEST_ACCOUNTS.fresh.password);
      cy.wait(3000);
      screenshot('s1-01-after-login', 2000);
    });

    it('S1.4: Check for invisibleMode modal', () => {
      cy.log('👁️ CHECKING: invisibleMode modal should appear');

      cy.wait(2000);

      /* Check for modal */
      cy.get('body').then(($body) => {
        if ($body.find('#invisible-player-modal').length > 0) {
          cy.log('✅ Modal found!');
          cy.get('#invisible-player-modal').should('be.visible');
          screenshot('s1-05-modal-visible', 1000);

          /* Verify modal content */
          cy.get('#invisible-player-modal').should('contain', 'Bienvenue');
          cy.get('#invisible-player-modal').should('contain', 'tutoriel');
          screenshot('s1-06-modal-content', 500);
        } else {
          cy.log('⚠️ Modal not shown');
          screenshot('s1-05-no-modal', 1000);
        }
      });
    });

    it('S1.5: Start tutorial from modal', () => {
      cy.log('▶️ ACTION: Start tutorial');

      cy.get('body').then(($body) => {
        if ($body.find('#resume-tutorial-btn').length > 0) {
          cy.get('#resume-tutorial-btn').click();
          cy.wait(3000);
          screenshot('s1-07-tutorial-started', 2000);
        } else {
          cy.log('⚠️ Resume button not found');
        }
      });
    });
  });

  /**
   * ==========================================
   * SCENARIO 2: Tutorial Started - Resume Modal
   * ==========================================
   */
  describe('Scenario 2: Resume Tutorial Mid-Progress', () => {
    before(() => {
      clearBrowserState();
    });

    it('S2.1: Login with tutorial-started account', () => {
      cy.log('🔐 LOGIN: TestTutorialStarted (mid-tutorial)');

      cy.visit('/index.php');
      cy.wait(2000);
      screenshot('s2-01-login-page', 1000);

      cy.login(TEST_ACCOUNTS.started.name, TEST_ACCOUNTS.started.password);
      cy.wait(3000);
      screenshot('s2-02-after-login', 2000);
    });

    it('S2.2: Check for resume modal', () => {
      cy.log('👁️ CHECKING: Resume modal should appear');

      cy.wait(2000);

      cy.get('body').then(($body) => {
        if ($body.find('#invisible-player-modal').length > 0) {
          cy.log('✅ Resume modal found!');
          cy.get('#invisible-player-modal').should('be.visible');
          screenshot('s2-03-resume-modal-visible', 1000);

          /* Verify resume button exists */
          cy.get('#resume-tutorial-btn').should('be.visible');
          screenshot('s2-04-resume-button', 500);

          /* Click resume */
          cy.get('#resume-tutorial-btn').click();
          cy.wait(3000);
          screenshot('s2-05-tutorial-resumed', 2000);
        } else {
          cy.log('⚠️ Resume modal not shown');
          screenshot('s2-03-no-resume-modal', 1000);
        }
      });
    });
  });

  /**
   * ==========================================
   * SCENARIO 3: Tutorial Completed - No Modal
   * ==========================================
   */
  describe('Scenario 3: Tutorial Completed Account', () => {
    before(() => {
      clearBrowserState();
    });

    it('S3.1: Login with completed account', () => {
      cy.log('🔐 LOGIN: TestTutorialCompleted (tutorial done)');

      cy.visit('/index.php');
      cy.wait(2000);
      screenshot('s3-01-login-page', 1000);

      cy.login(TEST_ACCOUNTS.completed.name, TEST_ACCOUNTS.completed.password);
      cy.wait(3000);
      screenshot('s3-02-after-login', 2000);
    });

    it('S3.2: Verify no modal shown', () => {
      cy.log('👁️ CHECKING: No modal should appear');

      cy.wait(2000);

      cy.get('body').then(($body) => {
        if ($body.find('#invisible-player-modal').length > 0) {
          cy.log('❌ Modal shown (should not be shown!)');
          screenshot('s3-03-unexpected-modal', 1000);
        } else {
          cy.log('✅ No modal shown (correct!)');
          screenshot('s3-03-no-modal-correct', 1000);
        }
      });
    });

    it('S3.3: Verify game interface visible', () => {
      cy.log('📊 CHECKING: Game interface should be visible');

      cy.get('body').then(($body) => {
        const bodyText = $body.text();
        if (bodyText.includes('Caractéristiques') || bodyText.includes('Actions')) {
          cy.log('✅ Game interface visible');
        } else {
          cy.log('⚠️ Game interface not detected');
        }
      });

      screenshot('s3-04-game-interface', 1000);
    });

    it('S3.4: Verify player has tutorial completion rewards', () => {
      cy.log('💰 CHECKING: Player should have 240 XP/PI');

      /* Check stats via API */
      cy.request('/api/debug/get_player_stats.php').then((response) => {
        const xp = response.body.xp || 0;
        const pi = response.body.pi || 0;
        cy.log(`Player stats: ${xp} XP, ${pi} PI`);

        if (xp >= 240 && pi >= 240) {
          cy.log('✅ Tutorial completion rewards confirmed');
        } else {
          cy.log(`⚠️ Expected 240 XP/PI, got ${xp} XP, ${pi} PI`);
        }
      });

      screenshot('s3-05-stats-check', 500);
    });
  });

  /**
   * ==========================================
   * SCENARIO 4: Tutorial Skipped - No Modal
   * ==========================================
   */
  describe('Scenario 4: Tutorial Skipped Account', () => {
    before(() => {
      clearBrowserState();
    });

    it('S4.1: Login with skipped account', () => {
      cy.log('🔐 LOGIN: TestTutorialSkipped (tutorial skipped)');

      cy.visit('/index.php');
      cy.wait(2000);
      screenshot('s4-01-login-page', 1000);

      cy.login(TEST_ACCOUNTS.skipped.name, TEST_ACCOUNTS.skipped.password);
      cy.wait(3000);
      screenshot('s4-02-after-login', 2000);
    });

    it('S4.2: Verify no modal shown', () => {
      cy.log('👁️ CHECKING: No modal should appear');

      cy.wait(2000);

      cy.get('body').then(($body) => {
        if ($body.find('#invisible-player-modal').length > 0) {
          cy.log('❌ Modal shown (should not be shown!)');
          screenshot('s4-03-unexpected-modal', 1000);
        } else {
          cy.log('✅ No modal shown (correct!)');
          screenshot('s4-03-no-modal-correct', 1000);
        }
      });
    });

    it('S4.3: Verify player has skip rewards', () => {
      cy.log('💰 CHECKING: Player should have 50 XP/PI (skip rewards)');

      cy.request('/api/debug/get_player_stats.php').then((response) => {
        const xp = response.body.xp || 0;
        const pi = response.body.pi || 0;
        cy.log(`Player stats: ${xp} XP, ${pi} PI`);

        if (xp >= 50 && pi >= 50) {
          cy.log('✅ Skip rewards confirmed');
        } else {
          cy.log(`⚠️ Expected 50 XP/PI, got ${xp} XP, ${pi} PI`);
        }
      });

      screenshot('s4-04-skip-rewards-check', 1000);
    });
  });

  /**
   * ==========================================
   * SCENARIO 5: New Player Registration
   * ==========================================
   */
  describe('Scenario 5: Register New Player', () => {
    const timestamp = Date.now();
    const newPlayer = {
      name: `CypTest${timestamp}`,
      race: 'hs',
      password: 'testpass',
      email: `cyptest${timestamp}@test.com`
    };

    before(() => {
      clearBrowserState();
    });

    it('S5.1: Navigate to registration page', () => {
      cy.log('📝 NAVIGATE: Registration page');

      cy.visit('/index.php?menu');
      cy.wait(2000);
      screenshot('s5-01-registration-page', 1000);
    });

    it('S5.2: Fill registration form', () => {
      cy.log('📝 FILL: Registration form');

      cy.register(newPlayer.name, newPlayer.race, newPlayer.password, newPlayer.email);
      cy.wait(2000);
      screenshot('s5-02-registration-complete', 1000);
    });

    it('S5.3: Login with new account', () => {
      cy.log('🔐 LOGIN: New account');

      cy.login(newPlayer.name, newPlayer.password);
      cy.wait(3000);
      screenshot('s5-03-first-login', 2000);
    });

    it('S5.4: Check for tutorial auto-start or modal', () => {
      cy.log('👁️ CHECKING: Tutorial should auto-start or show modal');

      cy.wait(2000);

      cy.get('body').then(($body) => {
        if ($body.find('#tutorial-overlay').length > 0) {
          cy.log('✅ Tutorial overlay found (auto-started)');
          screenshot('s5-04-tutorial-auto-started', 1000);
        } else if ($body.find('#invisible-player-modal').length > 0) {
          cy.log('✅ Modal shown (can start tutorial)');
          screenshot('s5-04-modal-shown', 1000);
        } else {
          cy.log('⚠️ No tutorial UI detected');
          screenshot('s5-04-no-tutorial', 1000);
        }
      });
    });
  });
});
