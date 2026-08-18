/* Throwaway — rail without events, spells icon, clean icon edges. */
describe('rail icons', () => {
    it('desktop rail and mobile drawer', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1200);
        cy.get('#hud-rail a[href^="logs.php"]').should('not.exist');
        cy.screenshot('rail-desktop', { capture: 'viewport', overwrite: true });
    });
    it('mobile drawer', () => {
        cy.viewport(390, 844);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1000);
        cy.get('#hud-burger').click();
        cy.wait(500);
        cy.screenshot('drawer-icons', { capture: 'viewport', overwrite: true });
    });
});
