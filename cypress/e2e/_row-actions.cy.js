/* Throwaway — per-row inventory buttons: Déséquiper via the row button
 * acts on that line's item, page reloads, state flips to Équiper. */
describe('inventory row actions', () => {
    it('unequips from the row button', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1500);

        cy.get('#show-inventory').click();
        cy.wait(2000);

        /* Ligne du bâton (item 8), équipé : bouton Déséquiper */
        cy.get('.hud-panel .item-case[data-id="8"] .row-action[data-action="use"]')
            .should('have.attr', 'title', 'Déséquiper')
            .click();

        /* L'action recharge la page ; le panneau persiste et rouvre */
        cy.wait(4000);
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.get('.hud-panel .item-case[data-id="8"] .row-action[data-action="use"]', { timeout: 10000 })
            .should('have.attr', 'title', 'Équiper');

        cy.screenshot('row-actions', { capture: 'viewport', overwrite: true });
    });
});
