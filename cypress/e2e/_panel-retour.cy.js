/* Throwaway — Retour hidden in panels. */
describe('panel retour', () => {
    it('hides retour in personnage and profil panels', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1000);

        cy.get('#hud-chip-name').click();
        cy.get('.hud-panel--open', { timeout: 8000 }).should('exist');
        cy.wait(600);
        cy.get('.hud-panel--open .hud-panel-content a[href="index.php"]').should('not.be.visible');
        cy.screenshot('personnage-no-retour', { capture: 'viewport', overwrite: true });

        cy.get('#hud-rail a[href="account.php"]').click({ force: true });
        cy.wait(1000);
        cy.get('.hud-panel--open .hud-panel-content a[href="index.php"]').should('not.be.visible');
    });
});
