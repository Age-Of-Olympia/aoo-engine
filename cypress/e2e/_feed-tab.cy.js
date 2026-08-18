/* Throwaway — feed tab persists, washes discrete. */
describe('feed tab persistence', () => {
    it('events tab restored after reload', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.get('.hud-tab[data-tab="events"]').click();
        cy.wait(1200);
        cy.get('#hud-feed-events').should('be.visible');
        cy.reload();
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1500);
        cy.get('.hud-tab[data-tab="events"]').should('have.class', 'hud-tab--active');
        cy.get('#hud-feed-events').should('be.visible');
        cy.get('#hud-feed-mdj').should('not.be.visible');
        cy.screenshot('feed-tab-restored', { capture: 'viewport', overwrite: true });
    });
});
