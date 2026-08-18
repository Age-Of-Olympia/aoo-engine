/* Throwaway — drawer badges inside the border; Profil board options
 * refresh the board like the layers popover. */
describe('drawer polish + profil board options', () => {
    it('drawer badges fit inside the drawer', () => {
        cy.viewport(390, 844);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(2500);
        cy.get('#hud-burger').click();
        cy.wait(600);
        cy.window().then((win) => {
            const rail = win.document.getElementById('hud-rail');
            const railRight = rail.getBoundingClientRect().right;
            ['forum-unread-badge', 'current-characters-mails'].forEach((id) => {
                const el = win.document.getElementById(id);
                if (el) {
                    const r = el.getBoundingClientRect();
                    expect(r.right, id + ' inside drawer').to.be.at.most(railRight - 2);
                }
            });
        });
        cy.screenshot('drawer-badges', { capture: 'viewport', overwrite: true });
    });

    it('hideGrid from the Profil panel reloads the board', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1500);
        /* grille visible au départ (option off) */
        cy.get('#svg-view image.case').should('exist');

        cy.get('#hud-rail a[href="account.php"]').click();
        cy.wait(1800);
        cy.get('.hud-panel--open .option[data-option="hideGrid"]').click();
        /* rechargement automatique : la grille disparaît, le popover
         * marque l'option active */
        cy.wait(3500);
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.get('#svg-view image.case').should('not.exist');
        cy.get('.hud-layer[data-option="hideGrid"]').should('have.class', 'hud-layer--on');
        cy.screenshot('profil-hidegrid', { capture: 'viewport', overwrite: true });

        /* retour à l'état initial via le popover (chemin inverse) */
        cy.get('#hud-layers-btn').click();
        cy.get('.hud-layer[data-option="hideGrid"]').click();
        cy.wait(3000);
        cy.get('#svg-view image.case').should('exist');
    });
});
