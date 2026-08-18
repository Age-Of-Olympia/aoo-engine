/* Throwaway — colonne Action « Oublier » sur la page des sorts. */
describe('spells row actions', () => {
    it('forget buttons inline, confirm dialog, no mode page', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1000);

        cy.get('#show-spells').click({ force: true });
        cy.wait(1500);

        /* boutons par ligne, plus de lien « Oublier un sort » */
        cy.get('.hud-panel--open .item-actions .row-action.forget').should('have.length.greaterThan', 2);
        cy.get('.hud-panel--open a[href="upgrades.php?spells&forget"]').should('not.exist');
        cy.screenshot('spells-row-actions', { capture: 'viewport', overwrite: true });

        /* confirmation nominative, annulation sans dégât */
        cy.get('.hud-panel--open .row-action.forget').first().click();
        cy.wait(400);
        cy.get('.aoo-dialog').should('contain.text', 'Oublier');
        cy.screenshot('spells-forget-confirm', { capture: 'viewport', overwrite: true });
        cy.get('.aoo-dialog-cancel').click();
        cy.get('.hud-panel--open .row-action.forget').first().should('not.be.disabled');
    });
});
