/* Throwaway — outcome chips in the events feed. */
describe('feed outcome chips', () => {
    it('events show success chips', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.get('.hud-tab[data-tab="events"]').click();
        cy.wait(1500);
        cy.get('#hud-feed-events .hud-feed-item--ok').should('exist');
        cy.screenshot('feed-outcomes', { capture: 'viewport', overwrite: true });
    });
});
