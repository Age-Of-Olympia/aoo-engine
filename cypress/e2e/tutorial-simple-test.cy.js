/**
 * Simple Tutorial Test - Screenshots at Every Step
 * Testing with pre-existing test account: TestFreshPlayer
 */

describe('Tutorial Test - Fresh Player Login', () => {
  const TEST_ACCOUNT = {
    name: 'TestFreshPlayer',
    password: 'testpass'
  };

  /**
   * Helper: Take screenshot with proper timing
   */
  const screenshot = (name, extraWait = 1000) => {
    cy.wait(extraWait);
    cy.get('body').should('be.visible');
    cy.wait(500);
    cy.screenshot(name, {
      capture: 'viewport',
      overwrite: true
    });
  };

  /**
   * Clear browser state before test
   */
  before(() => {
    cy.clearCookies();
    cy.clearLocalStorage();
    cy.window().then((win) => {
      win.sessionStorage.clear();
    });
  });

  it('Complete test flow: Login and check game state', () => {
    // Step 1: Visit main page (before login)
    cy.log('📋 Step 1: Visiting main page');
    cy.visit('/index.php');
    screenshot('01-main-page', 2000);

    // Step 2: Login with test account
    cy.log('🔐 Step 2: Logging in with TestFreshPlayer');
    cy.login(TEST_ACCOUNT.name, TEST_ACCOUNT.password);
    screenshot('02-after-login', 2000);

    // Step 3: Check if Nouveau Tour screen appears
    cy.log('⏳ Step 3: Checking for New Turn screen');
    cy.get('body').then(($body) => {
      if ($body.text().includes('Nouveau Tour')) {
        cy.log('✓ New Turn screen detected');
        screenshot('03-new-turn-screen', 1000);

        // Step 4: Click link/button to proceed to game
        if ($body.find('a[href="index.php"]').length > 0) {
          cy.log('🎮 Step 4: Clicking link to enter game');
          cy.get('a[href="index.php"]').first().click();
          cy.wait(2000);
          screenshot('04-after-click', 1000);
        }
      } else {
        cy.log('✓ Already in game (no New Turn screen)');
        screenshot('03-already-in-game', 1000);
      }
    });

    // Step 5: Verify game interface loaded
    cy.log('👀 Step 5: Checking game interface');
    cy.get('body').then(($body) => {
      const bodyText = $body.text();
      cy.log('Page contains "TestFreshPlayer": ' + bodyText.includes('TestFreshPlayer'));
      cy.log('Page contains menu items: ' + (bodyText.includes('Caractéristiques') || bodyText.includes('Inventaire')));
    });
    screenshot('05-game-interface', 1000);

    // Step 6: Check for tutorial elements
    cy.log('🔍 Step 6: Looking for tutorial elements');
    cy.get('body').then(($body) => {
      const hasOverlay = $body.find('#tutorial-overlay').length > 0;
      const hasLoadingOverlay = $body.find('#tutorial-loading-overlay').length > 0;
      cy.log('Tutorial overlay found: ' + hasOverlay);
      cy.log('Tutorial loading overlay found: ' + hasLoadingOverlay);

      if (hasOverlay) {
        const isVisible = $body.find('#tutorial-overlay').is(':visible');
        cy.log('Tutorial overlay visible: ' + isVisible);
      }
    });
    screenshot('06-tutorial-check', 1000);

    // Step 7: Final state
    cy.log('📊 Step 7: Final state');
    cy.wait(2000);
    screenshot('07-final-state', 2000);
  });
});
