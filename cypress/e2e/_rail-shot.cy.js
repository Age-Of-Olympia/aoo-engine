/* Throwaway — gros plan du rail pour contrôler le centrage des icônes. */
describe('rail closeup', () => {
    it('captures the rail', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(800);
        cy.get('#hud-rail').screenshot('rail-only', { overwrite: true });
    });
});
