/* Throwaway — rail zoomé x2 pour juger le centrage à l'œil. */
describe('rail zoom', () => {
    it('captures the rail at 2x', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1200);
        cy.get('#hud-rail').invoke('css', 'zoom', '2.5');
        cy.wait(300);
        cy.get('#hud-rail').screenshot('rail-zoom', { overwrite: true });
    });
});
