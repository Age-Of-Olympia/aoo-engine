/* Throwaway — target sheet opens as panel from selection band. */
describe('fiche from selection', () => {
    it('opens infos panel instead of navigating', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1000);

        cy.get('image#current-player-avatar').then(($p) => {
            cy.get('.case[x="' + $p.attr('x') + '"][y="' + $p.attr('y') + '"]').click({ force: true });
        });
        cy.wait(1200);

        cy.get('#ajax-data a[href^="infos.php?targetId="]').first().click();
        cy.wait(1200);
        cy.url().should('include', 'index.php');
        cy.get('.hud-panel--open .hud-panel-content').should('not.be.empty');
        cy.screenshot('fiche-panel', { capture: 'viewport', overwrite: true });
    });
});
