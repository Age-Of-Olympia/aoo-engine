/* Throwaway — 1280px: equip via row button (1 Ae), worn state differentiated,
 * Ae-grayed buttons with tooltip, top preview buttons hidden, band strip. */
describe('equipment final checks', () => {
    it('equips from the row, then all states are right', () => {
        cy.viewport(1280, 800);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1500);

        cy.get('#show-inventory').click();
        cy.wait(2000);

        /* Aperçu : boutons masqués (les lignes portent les actions) */
        cy.get('.hud-panel .preview-action').should('not.be.visible');

        /* Bâton non porté, 1 Ae dispo : bouton Équiper actif */
        cy.get('.hud-panel .item-case[data-id="8"] .row-action[data-action="use"]')
            .should('have.attr', 'title', 'Équiper (1 Ae)')
            .and('not.be.disabled')
            .click();

        /* L'équipement recharge la page ; le panneau rouvre seul */
        cy.wait(4000);
        cy.get('#hud', { timeout: 10000 }).should('exist');

        /* Porté : bouton plein, icône « rendre », infobulle Déséquiper */
        cy.get('.hud-panel .item-case[data-id="8"] .row-action[data-action="use"]', { timeout: 10000 })
            .should('have.attr', 'title', 'Déséquiper')
            .and('have.class', 'row-action--worn')
            .find('.ra-reverse').should('exist');

        /* Plus d'Ae : les autres Équiper sont grisés avec explication */
        cy.get('.hud-panel .item-case[data-id="19"] .row-action[data-action="use"]')
            .should('be.disabled')
            .invoke('attr', 'title')
            .should('contain', "plus d'Action d'Équipement ce tour");

        cy.screenshot('rows-final', { capture: 'viewport', overwrite: true });

        /* Bandeau de sélection à 1280px : l'équipement porté apparaît
         * (case du joueur lue dans le bandeau haut — il bouge au fil
         * des tests). */
        cy.get('#hud-location').invoke('text').then((txt) => {
            const m = txt.match(/\((-?\d+), (-?\d+),/);
            cy.get('.case[data-coords="' + m[1] + ',' + m[2] + '"]').click({ force: true });
        });
        cy.wait(1500);
        cy.get('#ajax-data .equip-strip').should('be.visible');
        cy.get('#ajax-data .equip-slot').should('have.length.at.least', 1);
        cy.screenshot('band-1280', { capture: 'viewport', overwrite: true });
    });
});
