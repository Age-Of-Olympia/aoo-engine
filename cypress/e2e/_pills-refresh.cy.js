/* Throwaway — top pills refresh after an action. */
describe('pills refresh', () => {
    it('A pill drops after executing an action', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1000);

        cy.get('image#current-player-avatar').then(($p) => {
            cy.get('.case[x="' + $p.attr('x') + '"][y="' + $p.attr('y') + '"]').click({ force: true });
        });
        cy.wait(1500);

        let aBefore;
        cy.get('#hud-pill-a').then(($p) => { aBefore = $p.text().trim(); });

        /* Arme puis confirme la première action self */
        cy.get('#hud-actions .action:not(.close-card)').first().click();
        cy.wait(300);
        cy.get('#hud-actions .action:not(.close-card)').first().click({ force: true });
        cy.get('#hud-action-modal', { timeout: 8000 }).should('be.visible');
        cy.wait(2000);

        cy.get('#hud-pill-a').should(($p) => {
            expect($p.text().trim()).to.not.eq(aBefore);
        });
    });
});
