/* Throwaway — le rafraîchissement du flux propage le mdj à la sélection. */
describe('mdj refresh propagation', () => {
    it('new mdj reaches the selection card after feed refresh', () => {
        const fresh = 'Message frais ' + Cypress._.random(1000, 9999);

        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1200);

        /* sélectionne sa propre case : la bulle montre le mdj actuel */
        cy.get('#game-map .case[data-coords="0,-3"]').click({ force: true });
        cy.wait(1500);
        cy.get('#ajax-data').should('not.contain.text', fresh);

        /* poste un nouveau mdj via le formulaire du panneau latéral */
        cy.get('#hud-mdj-form input[type="text"], #hud-mdj-form textarea, #hud-mdj-input')
            .first().type(fresh, { force: true });
        cy.get('#hud-mdj-form button').click({ force: true });
        cy.wait(1500);

        /* la sélection garde l'ancien texte… */
        cy.get('#ajax-data').should('not.contain.text', fresh);

        /* …jusqu'au bouton rafraîchir, qui propage partout */
        cy.get('#hud-feed-refresh').click();
        cy.wait(1500);
        cy.get('#ajax-data').should('contain.text', fresh);
        cy.screenshot('mdj-propagated', { capture: 'viewport', overwrite: true });
    });
});
