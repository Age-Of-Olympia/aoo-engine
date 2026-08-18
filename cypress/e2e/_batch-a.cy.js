/* Throwaway — batch A: read-only pages as panels + craft row routing. */
describe('batch A panels', () => {
    beforeEach(() => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1500);
    });

    it('classements from the trophy, tabs stay in the panel', () => {
        cy.get('#hud-topbar a[href="classements.php"]').click();
        cy.wait(1500);
        cy.get('.hud-panel--open .hud-panel-title').should('contain', 'Classements');
        cy.get('.hud-panel--open a[href="classements.php?bourrins"]').click();
        cy.wait(1500);
        cy.get('.hud-panel--open .hud-panel-content').should('contain', 'Bourrins');
        cy.url().should('not.contain', 'classements');
        cy.screenshot('panel-classements', { capture: 'viewport', overwrite: true });
    });

    it('forum last posts from the Forum button', () => {
        cy.get('#hud-topbar a[href="forum.php?lastPosts"]').click();
        cy.wait(1500);
        cy.get('.hud-panel--open .hud-panel-title').should('contain', 'Forum');
        cy.get('.hud-panel--open .hud-panel-content').should('contain', 'Derniers Messages');
        cy.screenshot('panel-lastposts', { capture: 'viewport', overwrite: true });
    });

    it('pnjs from the avatar', () => {
        cy.get('#player-avatar a').click();
        cy.wait(1500);
        cy.get('.hud-panel--open .hud-panel-title').should('contain', 'Personnages');
        cy.get('.hud-panel--open .pnj').should('exist');
        cy.screenshot('panel-pnjs', { capture: 'viewport', overwrite: true });
    });

    it('events full page in a panel, tabs stay inside', () => {
        cy.get('#hud-feed-full').click();
        cy.wait(1500);
        cy.get('.hud-panel--open .hud-panel-title').should('contain', 'Évènements');
        cy.get('.hud-panel--open a[href="logs.php?mdj"]').click();
        cy.wait(1500);
        cy.url().should('not.contain', 'logs');
        cy.screenshot('panel-logs', { capture: 'viewport', overwrite: true });
    });

    it('reputation from the character sheet', () => {
        cy.get('#hud-chip-name').click();
        cy.wait(1500);
        cy.get('.hud-panel--open a[href*="reputation"]').first().click();
        cy.wait(1800);
        cy.get('.hud-panel--open #pr-wrapper').should('exist');
        cy.url().should('not.contain', 'reputation');
        cy.screenshot('panel-reputation', { capture: 'viewport', overwrite: true });
    });

    it('faction from the selection band medallion', () => {
        /* La case du joueur, lue dans le bandeau haut (il bouge au fil
         * des tests précédents). */
        cy.get('#hud-location').invoke('text').then((txt) => {
            const m = txt.match(/\((-?\d+), (-?\d+),/);
            cy.get('.case[data-coords="' + m[1] + ',' + m[2] + '"]').click({ force: true });
        });
        cy.wait(1500);
        cy.get('#ajax-data .card-faction a, #ajax-data .card-type a[href^="faction"]').first().click();
        cy.wait(1500);
        cy.get('.hud-panel--open .hud-panel-title').should('contain', 'Faction');
        cy.url().should('not.contain', 'faction');
        cy.screenshot('panel-faction', { capture: 'viewport', overwrite: true });
    });

    it('craft row button opens the craft panel pre-filtered', () => {
        cy.get('#show-inventory').click();
        cy.wait(2000);
        cy.get('.hud-panel .item-case[data-id="89"] .row-action[data-action="craft"]').click();
        cy.wait(2000);
        cy.get('.hud-panel--open .hud-panel-title').should('contain', 'Artisanat');
        cy.url().should('not.contain', 'craft');
        cy.screenshot('panel-craft-row', { capture: 'viewport', overwrite: true });
    });
});
