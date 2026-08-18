/* Throwaway audit — standalone pages polish batch. */
describe('standalone polish batch', () => {
    it('captures paper pages (Cradek)', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');

        cy.visit('map.php?world');
        cy.wait(1000);
        cy.screenshot('map-switches', { capture: 'viewport', overwrite: true });

        cy.visit('minigame_morpion.php');
        cy.wait(600);
        cy.screenshot('morpion-clean', { capture: 'viewport', overwrite: true });

        cy.visit('forum.php?topic=1783207316084');
        cy.wait(1000);
        cy.screenshot('missive-thread', { capture: 'viewport', overwrite: true });

        cy.visit('forum.php');
        cy.wait(800);
        cy.screenshot('forum-index-links', { capture: 'viewport', overwrite: true });
    });

    it('captures legacy missive thread (Dorna)', () => {
        cy.viewport(1440, 900);
        cy.login('Dorna', 'test');
        cy.visit('logs.php');
        cy.wait(800);
        cy.screenshot('legacy-logs-check', { capture: 'viewport', overwrite: true });
    });
});
