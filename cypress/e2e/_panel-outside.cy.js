/* Throwaway — panel closes on outside click. */
describe('panel outside click', () => {
    it('closes on map click, stays on inside click', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1000);

        /* Ouvre Inventaire depuis le rail — le clic d'ouverture ne doit pas s'auto-fermer */
        cy.get('#show-inventory').click();
        cy.get('.hud-panel--open', { timeout: 8000 }).should('exist');
        cy.wait(800);

        /* Clic DANS le panneau : reste ouvert */
        cy.get('.hud-panel--open .hud-panel-content').click('center');
        cy.wait(300);
        cy.get('.hud-panel--open').should('exist');

        /* Clic sur le damier : se ferme */
        cy.get('#game-map').click(700, 300, { force: true });
        cy.wait(400);
        cy.get('.hud-panel--open').should('not.exist');
    });
});
