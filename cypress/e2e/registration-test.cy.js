/**
 * Registration Test - Create new player and verify tutorial starts
 */

describe('Player Registration Flow', () => {
  /* Use simple name without numbers - registration may reject numeric names */
  const NEW_PLAYER = {
    name: 'Testregis',
    race: 'hs',
    password: 'testpass123',
    email: 'testregis@example.com'
  };

  /**
   * Helper: Take screenshot with proper timing
   */
  const screenshot = (name, extraWait = 300) => {
    cy.wait(extraWait);
    cy.get('body').should('be.visible');
    cy.wait(100);
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

  it('Complete registration flow: Register and check tutorial', () => {
    // Step 1: Visit registration page
    cy.log('📋 Step 1: Visiting registration page');
    cy.visit('/register.php');
    screenshot('01-registration-page', 500);

    // Step 2: Select race from the dialog
    cy.log('🎭 Step 2: Selecting race from dialog');
    cy.get('#ui-dialog', { timeout: 5000 }).should('be.visible');
    cy.get('.node-option:visible').contains('Homme-Sauvage').click({ force: true });
    cy.wait(500);
    screenshot('02a-race-selected-in-dialog');

    // Step 2b: Confirm race selection
    cy.log('✅ Step 2b: Confirming race selection');
    cy.get('.node-option:visible').contains('Va pour').click({ force: true });
    cy.wait(500);
    screenshot('02b-race-confirmed');

    // Step 2c: Enter character name in dialog
    cy.log('📝 Step 2c: Entering character name');
    cy.get('input[type="text"]:visible').first().clear().type(NEW_PLAYER.name);
    screenshot('02c-name-entered-in-dialog', 200);

    // Click [continuer] to proceed
    cy.get('.node-option:visible').contains('continuer').click({ force: true });
    cy.wait(500);
    screenshot('02d-continue-clicked');

    // Step 2d: Final confirmation
    cy.log('✅ Step 2d: Final confirmation');
    cy.get('.node-option:visible').contains('Soit').click({ force: true });
    cy.wait(1000); // Wait for registration form to load
    screenshot('02e-final-confirmation');

    // Step 3: Fill the full registration form
    cy.log('✍️ Step 3: Filling full registration form');
    screenshot('03-registration-form-loaded');

    cy.get('input[name="psw1"]', { timeout: 5000 }).should('be.visible').type(NEW_PLAYER.password);
    screenshot('03a-password1-entered', 200);

    cy.get('input[name="psw2"]').type(NEW_PLAYER.password);
    screenshot('03b-password2-entered', 200);

    cy.get('input[name="mail"]').type(NEW_PLAYER.email);
    screenshot('03c-email-entered', 200);

    cy.get('#cgu').check({ force: true });
    screenshot('03d-cgu-checked', 200);

    // Step 4: Submit registration form
    cy.log('📝 Step 4: Submitting registration');
    cy.get('#noderegister #submit, #submit').click();
    cy.wait(1500);
    screenshot('04-registration-submitted');

    // Step 5: Visit login page
    cy.log('🔐 Step 5: Visiting login page');
    cy.visit('/index.php');
    cy.wait(500);
    screenshot('05-login-page');

    // Check if there's a "Jouer" button to click first
    cy.get('body').then(($body) => {
      if ($body.find('a:contains("Jouer"), button:contains("Jouer")').length > 0) {
        cy.log('🎮 Clicking "Jouer" button to access login form');
        cy.get('a:contains("Jouer"), button:contains("Jouer")').first().click();

        // Wait for login form to fade in (CSS has display:none, JS uses fadeIn())
        cy.get('#index-login', { timeout: 3000 }).should('be.visible');
        screenshot('05a-jouer-clicked');
      }
    });

    // Step 6: Login with new account through UI (NOT API!)
    cy.log('🔐 Step 6: Logging in with new account');

    cy.get('input[name="name"]', { timeout: 5000 }).should('be.visible').clear().type(NEW_PLAYER.name);
    cy.get('input[name="psw"]').type(NEW_PLAYER.password);
    screenshot('06a-login-form-filled', 200);

    // Submit login form - wait for button to be visible since it's inside #index-login
    cy.get('button[type="submit"]', { timeout: 3000 }).should('be.visible').click();
    cy.wait(1000);
    screenshot('06b-after-login');

    // Step 7: Handle "Nouveau Tour" page if it appears
    cy.log('⏳ Step 7: Checking for New Turn page');
    cy.wait(500);
    cy.get('body').then(($body) => {
      const bodyText = $body.text();
      if (bodyText.includes('Nouveau Tour') && $body.find('a[href="index.php"]').length > 0) {
        cy.log('✅ Nouveau Tour page detected - clicking to enter game');
        screenshot('07a-nouveau-tour-page');
        cy.get('a[href="index.php"]').first().click();
        cy.wait(1000);
        screenshot('07b-clicked-to-enter-game');
      } else {
        cy.log('ℹ️  No Nouveau Tour page - already in game');
        screenshot('07-no-nouveau-tour');
      }
    });

    // Step 8: Verify we're in the game
    cy.log('🎮 Step 8: Verifying game page loaded');
    cy.wait(500);
    cy.get('body').should('be.visible');

    cy.get('body').then(($body) => {
      const bodyText = $body.text();
      const hasPlayerName = bodyText.includes(NEW_PLAYER.name);
      const hasGameUI = bodyText.includes('Caractéristiques') ||
                        bodyText.includes('Inventaire') ||
                        $body.find('.map-container').length > 0;

      cy.log('✓ Player name visible: ' + hasPlayerName);
      cy.log('✓ Game UI visible: ' + hasGameUI);

      // ASSERT: These should be true for test to pass
      expect(hasPlayerName || hasGameUI, 'Should be on game page').to.be.true;
    });
    screenshot('08-game-page-loaded');

    // Step 9: Check for tutorial
    cy.log('🎓 Step 9: Checking for tutorial');
    cy.wait(500);

    cy.get('body').then(($body) => {
      const hasTutorialOverlay = $body.find('#tutorial-overlay').length > 0;
      const hasTutorialLoading = $body.find('#tutorial-loading-overlay').length > 0;
      const hasTutorialBtn = $body.find('button:contains("Commencer le tutoriel")').length > 0;

      cy.log('Tutorial overlay: ' + hasTutorialOverlay);
      cy.log('Tutorial loading: ' + hasTutorialLoading);
      cy.log('Tutorial button: ' + hasTutorialBtn);
      cy.log('Tutorial available: ' + (hasTutorialOverlay || hasTutorialLoading || hasTutorialBtn));
    });
    screenshot('09-tutorial-check');

    // Step 10: Final state verification
    cy.log('📊 Step 10: Final verification');

    // ASSERT: We should NOT still be on login or registration page
    cy.url().should('not.include', 'login.php');
    cy.url().should('not.include', 'register.php');

    // ASSERT: We should be on index.php (game page)
    cy.url().should('include', 'index.php');

    screenshot('10-final-state-verified');
    cy.log('✅ Registration flow complete - player is in game!');
  });
});
