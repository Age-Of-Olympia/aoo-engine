/* Throwaway — outside click restores idle location text (normal mode). */
describe('idle text returns', () => {
    it('click on empty paper clears selection', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1000);

        cy.get('image#current-player-avatar').then(($p) => {
            cy.get('.case[x="' + $p.attr('x') + '"][y="' + $p.attr('y') + '"]').click({ force: true });
        });
        cy.wait(1500);
        cy.get('#ajax-data .hud-sel').should('exist');

        /* Clic sur la marge de papier autour du damier */
        cy.get('#game-map').click(30, 300, { force: true });
        cy.wait(400);
        cy.get('#ajax-data .hud-sel').should('not.exist');
        cy.get('#ajax-data .hud-sel-idle').should('contain.text', 'Banque des Lutins');

        /* Et la sélection ne réapparaît pas au rechargement */
        cy.reload();
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1500);
        cy.get('#ajax-data .hud-sel').should('not.exist');
        cy.screenshot('idle-returned', { capture: 'viewport', overwrite: true });
    });
});
