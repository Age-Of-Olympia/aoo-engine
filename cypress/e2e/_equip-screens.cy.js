/* Throwaway — current state: character panel equipment + wide bottom band. */
describe('equipment screens', () => {
    it('character panel', () => {
        cy.viewport(1920, 1080);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1500);
        cy.get('#hud-chip-name').click();
        cy.wait(1800);
        cy.screenshot('panel-infos-wide', { capture: 'viewport', overwrite: true });
    });
    it('bottom band on self selection', () => {
        cy.viewport(1920, 1080);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1500);
        cy.get('.case[data-coords="0,-3"]').click({ force: true });
        cy.wait(1500);
        cy.screenshot('band-self-wide', { capture: 'viewport', overwrite: true });
    });
});
