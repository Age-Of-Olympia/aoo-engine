/* Throwaway — missive badges: red (current char, 52 unread) on the rail,
 * blue (other characters, 1 unread) on the avatar. */
describe('missive badges', () => {
    it('shows unread badges', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(2500);
        cy.get('#current-characters-mails').should('be.visible').and('contain', '52');
        cy.get('#other-characters-mails').should('be.visible').and('contain', '1');
        cy.window().then((win) => {
            const el = win.document.getElementById('other-characters-mails');
            const r = el.getBoundingClientRect();
            expect(r.width, 'blue badge width').to.be.greaterThan(5);
            expect(r.x, 'blue badge on-screen x').to.be.greaterThan(0);
            expect(r.y, 'blue badge on-screen y').to.be.at.least(0);
        });
        cy.screenshot('missive-badges', { capture: 'viewport', overwrite: true });
    });
});
