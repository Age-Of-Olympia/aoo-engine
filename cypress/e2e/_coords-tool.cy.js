/* Throwaway — coordinate tool at click position, paper style. */
describe('coords tool', () => {
    it('appears near the right-click, papered', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1000);

        cy.get('.case').first().rightclick(400, 300, { force: true });
        cy.wait(400);
        cy.get('#admin-coords').should('not.be.empty');
        cy.window().then((w) => {
            const r = w.document.getElementById('admin-coords').getBoundingClientRect();
            /* près du clic (le clic viewport ~ (case offset), tolérance large) */
            expect(r.top).to.be.greaterThan(100);
            expect(r.left).to.be.greaterThan(100);
        });
        cy.get('#admin-coords button:contains(TP)').should('exist');
        cy.screenshot('coords-tool', { capture: 'viewport', overwrite: true });
        cy.get('#admin-coords-close').click();
        cy.get('#admin-coords').should('be.empty');
    });
});
