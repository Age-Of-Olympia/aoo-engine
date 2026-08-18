/* Throwaway — effets actifs dans le bandeau haut (bureau), masqués mobile. */
describe('topbar effect chips', () => {
    it('shows effect chips on desktop', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(800);
        cy.get('#hud-effects .hud-pill--effect').should('have.length', 2)
            .first().should('be.visible');
        cy.screenshot('topbar-effects-desktop', { capture: 'viewport', overwrite: true });
    });

    it('hides effect chips on mobile', () => {
        cy.viewport(390, 844);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(800);
        cy.get('#hud-effects .hud-pill--effect').should('not.be.visible');
        cy.screenshot('topbar-effects-mobile-hidden', { capture: 'viewport', overwrite: true });
    });
});
