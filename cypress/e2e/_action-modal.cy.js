/* Throwaway — action result in a modal over the damier. */
describe('action result modal', () => {
    it('executes an action, result in modal, card untouched', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1000);

        /* Sélectionne SA case (image#current-player-avatar) : actions
         * self (repos, entraînement…), sans navigation missive. */
        cy.get('image#current-player-avatar').then(($p) => {
            const x = $p.attr('x');
            const y = $p.attr('y');
            cy.get('.case[x="' + x + '"][y="' + y + '"]').click({ force: true });
        });
        cy.wait(1200);
        cy.get('#hud-actions .action').should('exist');

        let cardTextBefore;
        cy.get('#ajax-data .card-text').then(($t) => { cardTextBefore = $t.text(); });

        /* Arme puis confirme la première action non-fermer */
        cy.get('#hud-actions .action:not(.close-card)').first().click();
        cy.wait(300);
        cy.get('#hud-actions .action:not(.close-card)').first().click();

        cy.get('#hud-action-modal', { timeout: 8000 }).should('be.visible');
        cy.wait(1200);
        cy.screenshot('action-modal', { capture: 'viewport', overwrite: true });

        /* La fiche n'a pas bougé */
        cy.get('#ajax-data .card-text').should(($t) => {
            expect($t.text()).to.eq(cardTextBefore);
        });

        /* Fermeture par la croix */
        cy.get('.hud-action-modal-close').click();
        cy.get('#hud-action-modal').should('not.be.visible');
    });
});
