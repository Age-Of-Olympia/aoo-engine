/* Throwaway — mobile carousel with Discussions pane. */
describe('mobile carousel', () => {
    it('captures the three panes and desktop regression', () => {
        cy.viewport(390, 844);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1200);

        /* Pas de bulle flottante */
        cy.get('#hud-bubble').should('not.exist');

        /* Volet Discussions (point 0) */
        cy.get('.hud-dot[data-index="0"]').click();
        cy.wait(600);
        cy.get('#hud-carousel #hud-side').should('be.visible');
        cy.screenshot('pane-discussions', { capture: 'viewport', overwrite: true });

        /* Volet Sélection (point 1) — défaut */
        cy.get('.hud-dot[data-index="1"]').click();
        cy.wait(600);
        cy.screenshot('pane-selection', { capture: 'viewport', overwrite: true });
    });

    it('desktop layout unchanged', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1200);
        cy.get('#hud-side').should('be.visible');
        cy.get('#hud-minimap').should('be.visible');
        cy.screenshot('desktop-regression', { capture: 'viewport', overwrite: true });
    });
});
