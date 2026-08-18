/* Throwaway — per-forum badges + live decrement of the orange badge. */
describe('forum badges v2', () => {
    it('home shows per-forum counts, reading decrements live', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1500);

        cy.get('#forum-unread-badge').should('contain', '3');

        cy.get('#hud-topbar a[href="forum.php"]').click();
        cy.wait(1500);
        /* pastilles par forum et par catégorie */
        cy.get('.hud-panel--open .forum-unread-mini').should('have.length.at.least', 4);
        cy.screenshot('forum-home-badges', { capture: 'viewport', overwrite: true });

        /* lire le sujet de la Grande Assemblée */
        cy.get('.hud-panel--open .forum[data-forum="La Grande Assemblée"]').first().click();
        cy.wait(1500);
        cy.get('.hud-panel--open a[href*="forum.php?topic="]').first().click();
        cy.wait(2000);

        /* la pastille du bouton passe à 2 SANS rechargement */
        cy.get('#forum-unread-badge').should('contain', '2');
        cy.screenshot('forum-badge-live', { capture: 'viewport', overwrite: true });

        /* retour à l'accueil : la Grande Assemblée n'a plus de pastille */
        cy.get('.hud-panel--open .hud-panel-back').click();
        cy.wait(1200);
        cy.get('.hud-panel--open .hud-panel-back').click();
        cy.wait(1500);
        cy.get('.hud-panel--open').contains('h1', 'Forums');
        cy.get('.hud-panel--open td:contains("La Grande Assemblée") .forum-unread-mini')
            .should('not.exist');
        cy.screenshot('forum-home-after-read', { capture: 'viewport', overwrite: true });
    });
});
