/* Throwaway — corrections retours lot 2 : oublier, avatar, nouveau sujet, agrandir. */
describe('lot 2 fixes round 2', () => {
    it('forget mode, new missive, wide panel stay in HUD', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1000);

        /* Oublier un sort : le lien de mode reste dans le panneau */
        cy.get('#show-spells').click({ force: true });
        cy.wait(1500);
        cy.get('.hud-panel--open .hud-panel-content a[href="upgrades.php?spells&forget"]').first().click();
        cy.wait(1500);
        cy.url().should('include', 'index.php');
        /* les boutons Oublier sont des <input value="Oublier"> */
        cy.get('.hud-panel--open .hud-panel-content input.forget').should('exist');
        cy.screenshot('fix-forget-mode-panel', { capture: 'viewport', overwrite: true });

        /* Nouveau sujet : formulaire dans le panneau, envoi → fil en panneau */
        cy.get('#hud-rail a[href="forum.php?forum=Missives"]').click({ force: true });
        cy.wait(1500);
        cy.get('.hud-panel--open .hud-panel-content a[href^="forum.php?newTopic="]').first().click();
        cy.wait(1500);
        cy.url().should('include', 'index.php');
        cy.get('.hud-panel--open .hud-panel-content textarea').should('exist');
        cy.screenshot('fix-newtopic-panel', { capture: 'viewport', overwrite: true });

        cy.get('.hud-panel--open input.name').clear().type('Sujet de test panneau');
        cy.get('.hud-panel--open .hud-panel-content textarea').clear().type('Premier message du sujet de test.');
        cy.get('.hud-panel--open button.submit').click();
        cy.wait(2000);
        cy.url().should('include', 'index.php');
        cy.get('.hud-panel--open .hud-panel-content').should('contain.text', 'Premier message du sujet de test.');
        cy.screenshot('fix-newtopic-posted', { capture: 'viewport', overwrite: true });

        /* Agrandir : le panneau s'étend, pas de navigation */
        cy.get('.hud-panel--open .hud-panel-fullpage').click();
        cy.wait(600);
        cy.url().should('include', 'index.php');
        cy.get('#hud').should('have.class', 'hud--panel-wide');
        cy.get('.hud-panel--open').invoke('outerWidth').should('be.greaterThan', 900);
        cy.screenshot('fix-wide-panel', { capture: 'viewport', overwrite: true });
        cy.get('.hud-panel--open .hud-panel-fullpage').click();
        cy.wait(600);
        cy.get('#hud').should('not.have.class', 'hud--panel-wide');
    });

    it('avatar change refreshes the topbar chip', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1000);

        cy.get('#player-avatar img').invoke('attr', 'src').then((before) => {

            cy.get('#hud-rail a[href="account.php"]').first().click({ force: true });
            cy.wait(1200);
            cy.get('.hud-panel--open a[href="account.php?avatars"]').first().click();
            cy.wait(2000);
            /* choisit un avatar différent de l'actuel */
            cy.get('.hud-panel--open .hud-panel-content img[data-img]').then(($imgs) => {
                const other = [...$imgs].find(el =>
                    (el.getAttribute('data-src') || el.getAttribute('src')) !== before);
                cy.wrap(other).scrollIntoView().click();
            });
            cy.wait(1500);
            cy.get('#player-avatar img').invoke('attr', 'src').should('not.eq', before);
            cy.get('.hud-panel--open .hud-panel-content').should('contain.text', 'Options du Profil');
            cy.screenshot('fix-avatar-chip-refresh', { capture: 'viewport', overwrite: true });
        });
    });
});
