/* Throwaway — gros plan du bouton Caractéristiques. */
describe('caracs button zoom', () => {
    it('captures the caracs button at 4x', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1200);
        cy.get('#show-caracs').invoke('css', 'zoom', '4');
        cy.wait(300);
        cy.get('#show-caracs').screenshot('caracs-btn-zoom', { overwrite: true });
    });
});
