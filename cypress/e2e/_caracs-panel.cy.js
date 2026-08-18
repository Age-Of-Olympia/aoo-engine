/* Throwaway — xp bar on upgrade panel + missive link stays in panel. */
describe('panel refinements', () => {
    it('shows xp progression and keeps missive navigation inside', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1000);

        cy.get('#show-caracs').click();
        cy.get('.hud-panel--open .hud-xp-progress', { timeout: 8000 }).should('be.visible');
        cy.screenshot('caracs-xp-panel', { capture: 'viewport', overwrite: true });

        /* Missives : cliquer un sujet doit OUVRIR le fil, pas fermer */
        cy.get('#hud-rail a[href="forum.php?forum=Missives"]').click({ force: true });
        cy.wait(1200);
        cy.get('.hud-panel--open .hud-panel-content a[href*="topic="]').first().click();
        cy.wait(1200);
        cy.get('.hud-panel--open').should('exist');
        cy.get('.hud-panel--open .hud-panel-content').should('contain.text', 'Répondre');
        cy.screenshot('missive-thread-stays', { capture: 'viewport', overwrite: true });

        /* Et le clic dehors ferme toujours */
        cy.get('#game-map').click(700, 300, { force: true });
        cy.wait(400);
        cy.get('.hud-panel--open').should('not.exist');
    });
});
