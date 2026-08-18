/* Throwaway — Missives & Carte in sliding panels. */
describe('rail panels', () => {
    it('opens missives and carte panels (desktop)', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1000);

        /* Missives : liste dans le panneau */
        cy.get('#hud-rail a[href="forum.php?forum=Missives"]').click();
        cy.get('.hud-panel--open .hud-panel-content', { timeout: 10000 })
            .should('contain.text', 'Nouveau joueur dans la faction');
        cy.screenshot('panel-missives-list', { capture: 'viewport', overwrite: true });

        /* Fil : clic sur un sujet — reste dans le panneau */
        cy.get('.hud-panel--open .hud-panel-content a[href*="topic="]').first().click();
        cy.wait(1200);
        cy.get('.hud-panel--open .hud-panel-content')
            .should('contain.text', 'Bonjour');
        cy.url().should('include', 'index.php');
        cy.screenshot('panel-missives-thread', { capture: 'viewport', overwrite: true });

        /* Carte : panneau avec couches empilées */
        cy.get('#hud-rail a[href="map.php"]').click();
        cy.wait(1500);
        cy.get('.hud-panel--open .hud-panel-content .hud-map-stack img').should('exist');
        cy.url().should('include', 'index.php');
        cy.screenshot('panel-carte', { capture: 'viewport', overwrite: true });
    });

    it('opens carte panel on mobile', () => {
        cy.viewport(390, 844);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1000);

        cy.get('#hud-burger').click();
        cy.wait(400);
        cy.get('#hud-rail a[href="map.php"]').click({ force: true });
        cy.wait(1500);
        cy.get('.hud-panel--open .hud-panel-content .hud-map-stack img').should('exist');
        cy.screenshot('panel-carte-mobile', { capture: 'viewport', overwrite: true });
    });
});
