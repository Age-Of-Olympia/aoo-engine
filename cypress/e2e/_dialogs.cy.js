/* Throwaway — mutualized modal dialogs replace native boxes. */
describe('aoo dialogs', () => {
    it('confirm on upgrade, alert override, prompt', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1000);

        /* alert() global → modale */
        cy.window().then((w) => { w.alert('Message de test'); });
        cy.get('.aoo-dialog').should('be.visible').and('contain.text', 'Message de test');
        cy.screenshot('dialog-alert', { capture: 'viewport', overwrite: true });
        cy.get('.aoo-dialog-ok').click();
        cy.get('.aoo-dialog').should('not.exist');

        /* confirm sur le +1 du panneau Caractéristiques : Annuler */
        cy.get('#show-caracs').click();
        cy.get('.hud-panel--open .upgrade:not(:disabled)', { timeout: 8000 }).first().click();
        cy.get('.aoo-dialog').should('be.visible').and('contain.text', 'Augmenter');
        cy.screenshot('dialog-confirm', { capture: 'viewport', overwrite: true });
        cy.get('.aoo-dialog-cancel').click();
        cy.get('.aoo-dialog').should('not.exist');
        cy.get('.hud-panel--open').should('exist');

        /* prompt : champ + annulation par Échap */
        cy.window().then((w) => { w.aooPrompt('Combien ?', 3); });
        cy.get('.aoo-dialog-input').should('be.visible').and('have.value', '3');
        cy.screenshot('dialog-prompt', { capture: 'viewport', overwrite: true });
        cy.get('body').type('{esc}');
        cy.get('.aoo-dialog').should('not.exist');
    });
});
