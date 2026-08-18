/* Throwaway audit — logs.php under paper and legacy themes. */
describe('logs paper theme', () => {
    it('captures paper logs (Cradek, newHud)', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('logs.php');
        cy.wait(1000);
        cy.screenshot('logs-paper', { capture: 'viewport', overwrite: true });
    });

    it('captures legacy logs (Dorna, no newHud)', () => {
        cy.viewport(1440, 900);
        cy.login('Dorna', 'test');
        cy.visit('logs.php');
        cy.wait(1000);
        cy.screenshot('logs-legacy', { capture: 'viewport', overwrite: true });
    });
});
