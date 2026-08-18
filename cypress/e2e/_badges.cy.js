/* Throwaway — mail badge placements. */
describe('mail badges', () => {
    it('desktop: red badge on rail missives icon', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(2500);
        cy.get('#missive-btn #current-characters-mails').should('be.visible');
        cy.screenshot('badge-desktop-rail', { capture: 'viewport', overwrite: true });
    });

    it('mobile: badge echoed on burger', () => {
        cy.viewport(390, 844);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(2500);
        cy.get('#hud-burger #hud-burger-mails').should('be.visible');
        cy.screenshot('badge-mobile-burger', { capture: 'viewport', overwrite: true });
    });
});
