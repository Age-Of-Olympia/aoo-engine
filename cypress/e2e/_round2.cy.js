/* Throwaway — feed name opens character panel; board coords toggle;
 * mobile drawer exposes topbar shortcuts. */
describe('round 2 checks', () => {
    it('feed character name opens the panel (desktop)', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(2000);
        cy.get('.hud-tab[data-tab="events"]').click();
        cy.wait(1500);
        cy.get('#hud-feed-events .hud-feed-meta a').first().click();
        cy.wait(1500);
        cy.get('.hud-panel--open .hud-panel-title').should('contain', 'Personnage');
        cy.get('.hud-panel--open #infos-player').should('exist');
        cy.screenshot('feed-to-panel', { capture: 'viewport', overwrite: true });
    });

    it('board coords toggle hides and restores the rulers', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(2000);
        cy.get('#svg-view #hud-svg-coords').should('exist');
        cy.get('#hud-layers-btn').click();
        cy.get('.hud-layer[data-option="hideBoardCoords"]').click();
        cy.wait(1000);
        cy.get('#svg-view #hud-svg-coords').should('not.exist');
        cy.screenshot('coords-hidden', { capture: 'viewport', overwrite: true });
        /* et retour (l'option est persistée côté serveur) */
        cy.get('.hud-layer[data-option="hideBoardCoords"]').click();
        cy.wait(1000);
        cy.get('#svg-view #hud-svg-coords').should('exist');
    });

    it('mobile drawer exposes Forum and Menu principal', () => {
        cy.viewport(390, 844);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(2000);
        cy.get('#hud-burger').click();
        cy.wait(600);
        cy.get('#hud-rail a.hud-quick-clone[href="forum.php"]').should('be.visible');
        cy.get('#hud-rail a.hud-quick-clone[href="index.php?menu"]').should('be.visible');
        cy.get('#hud-rail a.hud-quick-clone[href="index.php?logout"]').should('be.visible');
        cy.screenshot('drawer-shortcuts', { capture: 'viewport', overwrite: true });
    });

    it('desktop rail has no clones', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1500);
        cy.get('#hud-rail .hud-quick-clone').should('not.be.visible');
    });
});
