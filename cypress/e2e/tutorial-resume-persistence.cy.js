/**
 * TUTORIAL RESUME & PERSISTENCE TEST
 *
 * Validates that the tutorial state is correctly persisted and
 * restored when a player navigates away mid-tutorial and comes back:
 *
 *   Register → Auto-start → Advance a few steps → Navigate away →
 *   Return to index → Resume → Step + session state preserved.
 *
 * Covers the exit/resume path specifically. The full tutorial flow
 * is exercised by tutorial-production-ready.cy.js — we do NOT
 * re-walk every step here (maintenance tax, duplicates production-
 * ready).
 *
 * CRITICAL: SINGLE it() block so Cypress doesn't reset the session
 * between registration and resume.
 */

describe('Tutorial System - Resume & Persistence Test', () => {
  /* Letters only — register.php's isValidName regex rejects digits. */
  const uniqueNames = ['Iota', 'Kappa', 'Lambda', 'Mu', 'Nu', 'Xi', 'Omicron', 'Pi'];
  const randomName = uniqueNames[Math.floor(Math.random() * uniqueNames.length)];
  const timestamp = Date.now();
  const timestampSuffix = timestamp.toString(36).replace(/[0-9]/g, '').slice(-6).padEnd(4, 'x');
  const TEST_ACCOUNT = {
    name: `ResumeTest${randomName}${timestampSuffix}`,
    password: 'testpass123',
    email: `resumetest${timestamp}@test.com`,
    race: 'nain',
    playerId: null,
  };

  /* Advance checkpoint: reach step_id='your_character' (the first Info
     step after welcome). That's enough to prove session state is
     persisted past the bootstrap; we don't need to walk the whole
     tutorial to validate resume. */
  const CHECKPOINT_STEP = 'your_character';

  const waitForStepRender = (stepId, timeout = 10000) => {
    cy.log(`⏳ Waiting for step: ${stepId}`);
    cy.window({ timeout }).then((win) => {
      if (!win.tutorialUI) {
        throw new Error('TutorialUI not initialized');
      }
      return new Cypress.Promise((resolve) => {
        const check = () => {
          if (win.tutorialUI.currentStep === stepId) resolve();
          else setTimeout(check, 200);
        };
        check();
      });
    });
  };

  before(() => {
    cy.clearCookies();
    cy.clearLocalStorage();
    cy.window().then((win) => win.sessionStorage.clear());
  });

  it('Resume flow: start → navigate away → return → step restored', () => {
    /* 0. Register */
    cy.register(TEST_ACCOUNT.name, TEST_ACCOUNT.race, TEST_ACCOUNT.password, TEST_ACCOUNT.email);
    cy.task('queryDatabase', {
      query: 'SELECT id FROM players WHERE name = ? ORDER BY id DESC LIMIT 1',
      params: [TEST_ACCOUNT.name],
    }).then((rows) => {
      TEST_ACCOUNT.playerId = rows[0].id;
      cy.log(`Registered playerId=${TEST_ACCOUNT.playerId}`);
    });

    /* 1. Login → auto-start */
    cy.login(TEST_ACCOUNT.name, TEST_ACCOUNT.password);
    cy.wait(3000);
    cy.get('#tutorial-overlay', { timeout: 10000 }).should('exist');

    /* 2. Advance past `welcome` to reach the checkpoint step. */
    waitForStepRender('welcome');
    cy.get('#tutorial-next').should('be.visible').click();
    cy.wait(500);
    waitForStepRender(CHECKPOINT_STEP);

    /* 3. Validate the DB recorded the progression. Wrap in cy.then()
       so TEST_ACCOUNT.playerId (set asynchronously in step 0) is
       definitely populated before validateTutorialState reads it. */
    let sessionIdBefore;
    cy.then(() => {
      cy.validateTutorialState(TEST_ACCOUNT.playerId, {
        shouldExist: true,
        currentStep: CHECKPOINT_STEP,
        completed: 0,
      }).then((state) => {
        sessionIdBefore = state.tutorial_session_id;
        cy.log(`Session ${sessionIdBefore} at step ${CHECKPOINT_STEP}`);
      });
    });

    /* 4. Navigate away (simulates browser close / tab switch / crash). */
    cy.visit('/forum.php');
    cy.wait(2000);

    /* Session and tutorial_player must survive the navigation. */
    cy.then(() => {
      cy.validateTutorialState(TEST_ACCOUNT.playerId, {
        shouldExist: true,
        currentStep: CHECKPOINT_STEP,
        completed: 0,
      });
    });

    /* 5. Return to index — tutorial should auto-resume or offer to. */
    cy.visit('/index.php');
    cy.wait(2000);
    cy.get('body').then(($body) => {
      if ($body.find('button:contains("Reprendre")').length > 0) {
        cy.get('button:contains("Reprendre")').click();
        cy.wait(2000);
      } else if ($body.find('a:contains("Continuer le tutoriel")').length > 0) {
        cy.get('a:contains("Continuer le tutoriel")').click();
        cy.wait(2000);
      }
    });
    cy.get('#tutorial-overlay', { timeout: 10000 }).should('exist');

    /* 6. The restored step must match what the DB recorded. */
    cy.window().then((win) => {
      expect(win.tutorialUI.currentStep).to.equal(CHECKPOINT_STEP);
    });

    /* 7. Session id must be unchanged (resume uses the same row, not a
       new one). */
    cy.then(() => {
      cy.validateTutorialState(TEST_ACCOUNT.playerId, {
        shouldExist: true,
        currentStep: CHECKPOINT_STEP,
        completed: 0,
      }).then((state) => {
        expect(state.tutorial_session_id).to.equal(sessionIdBefore);
      });
    });
  });
});
