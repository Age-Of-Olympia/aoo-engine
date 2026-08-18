/* Throwaway — orange forum badge + forum home as a panel. */
describe('forum badge and home panel', () => {
    it('badge shows, home panel navigates, reading clears it', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1500);

        /* Badge orange sur le bouton Forum (1 sujet non lu, seedé) */
        cy.get('#forum-unread-badge').should('be.visible').and('contain', '1');
        cy.screenshot('forum-badge', { capture: 'viewport', overwrite: true });

        /* Accueil du forum en panneau */
        cy.get('#hud-topbar a[href="forum.php"]').click();
        cy.wait(1500);
        cy.get('.hud-panel--open .hud-panel-title').should('contain', 'Forum');
        cy.get('.hud-panel--open h1').should('contain', 'Forums');
        cy.screenshot('forum-home-panel', { capture: 'viewport', overwrite: true });

        /* Un forum → liste des sujets dans le panneau, titre = forum */
        cy.get('.hud-panel--open .forum[data-forum="La Caverne Bruyante"]').first().click();
        cy.wait(1500);
        cy.get('.hud-panel--open .hud-panel-back').should('be.visible');
        cy.url().should('not.contain', 'forum.php');
        cy.screenshot('forum-list-panel', { capture: 'viewport', overwrite: true });

        /* Lire le sujet non lu → le badge s'éteint au prochain rendu */
        cy.get('.hud-panel--open a[href*="forum.php?topic="]').first().click();
        cy.wait(1500);
        cy.visit('index.php');
        cy.wait(1500);
        cy.get('#forum-unread-badge').should('not.exist');
    });
});
