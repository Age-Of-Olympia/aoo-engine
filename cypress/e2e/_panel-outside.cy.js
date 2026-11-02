/* Throwaway — panel stays open on outside click, closes on Escape or ×. */
describe('panel outside click', () => {
    it('stays open on map click, closes on Escape and ×', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1000);

        cy.get('#show-inventory').click();
        cy.get('.hud-panel--open', { timeout: 8000 }).should('exist');
        cy.wait(800);

        /* Clic DANS le panneau : reste ouvert */
        cy.get('.hud-panel--open .hud-panel-content').click('center');
        cy.wait(300);
        cy.get('.hud-panel--open').should('exist');

        /* Clic sur le damier : reste ouvert */
        cy.get('#game-map').click(700, 300, { force: true });
        cy.wait(400);
        cy.get('.hud-panel--open').should('exist');

        /* Échap : se ferme */
        cy.get('body').type('{esc}');
        cy.get('.hud-panel--open').should('not.exist');

        /* Croix : se ferme */
        cy.get('#show-inventory').click();
        cy.get('.hud-panel--open', { timeout: 8000 }).should('exist');
        cy.get('.hud-panel--open .hud-panel-close').click();
        cy.get('.hud-panel--open').should('not.exist');
    });
});
