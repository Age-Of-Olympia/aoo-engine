// ***********************************************************
// This support file is loaded before all test files
// ***********************************************************

// Import commands
import './commands';

// Disable uncaught exception handling (useful for testing error states)
Cypress.on('uncaught:exception', (err, runnable) => {
  // Return false to prevent test failure on uncaught exceptions
  // This is useful for testing error states in the tutorial
  return false;
});

// Custom command to wait for AJAX
Cypress.Commands.add('waitForAjax', () => {
  cy.window().then((win) => {
    return new Cypress.Promise((resolve) => {
      if (win.jQuery && win.jQuery.active === 0) {
        resolve();
      } else {
        const checkAjax = setInterval(() => {
          if (win.jQuery && win.jQuery.active === 0) {
            clearInterval(checkAjax);
            resolve();
          }
        }, 100);
      }
    });
  });
});
