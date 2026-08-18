/* Throwaway — achat de sort à l'école de guerre depuis le panneau HUD.
 * Prérequis (DB dev aoo4) : Dorna (id 2) a l'option isTrainer et est
 * adjacente à Cradek ; pas d'adrénaline sur les deux. */
describe('war school buy in HUD panel', () => {
    it('buys a spell from the sliding panel', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1000);

        /* Ouvre le panneau Sorts de l'école de guerre via un lien
         * plein-page : le routeur (hud.js) doit le réécrire en
         * fragment load_warschool.php. */
        cy.window().then(win => {
            win.$('#hud').append(
                '<a id="test-warschool-link" href="warschool.php?targetId=2&spells">ws</a>'
            );
        });
        cy.get('#test-warschool-link').click();
        cy.get('.hud-panel--open .hud-panel-content .buy-skill-btn', { timeout: 10000 })
            .should('exist');

        cy.wait(500);
        cy.screenshot('buy-spell-panel', { capture: 'viewport', overwrite: true });

        /* Achète le premier sort disponible */
        cy.get('.hud-panel--open .buy-skill-btn:not(:disabled)').first().click();

        /* Confirmation */
        cy.get('.aoo-dialog-text', { timeout: 5000 })
            .should('contain.text', 'Voulez-vous vraiment apprendre');
        cy.get('.aoo-dialog-ok').click();

        /* Succès : le message serveur, PAS le vidage de page
         * (#game-map{...} + script du menu) du bug d'origine. */
        cy.get('.aoo-dialog-text', { timeout: 10000 })
            .should('contain.text', 'appris')
            .should('not.contain.text', 'game-map')
            .should('not.contain.text', 'Réponse serveur');
        cy.screenshot('buy-spell-success', { capture: 'viewport', overwrite: true });
        cy.get('.aoo-dialog-ok').click();

        /* aooReload : le panneau se recharge sans navigation */
        cy.wait(1500);
        cy.url().should('include', 'index.php');
        cy.get('.hud-panel--open .hud-panel-content .buy-skill-btn')
            .should('exist');
        cy.screenshot('buy-spell-reloaded', { capture: 'viewport', overwrite: true });
    });
});
