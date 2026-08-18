/* Throwaway — damier ornaments kept, page-corner ornaments gone. */
describe('ornaments', () => {
    it('board yes, body no', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1500);
        cy.window().then((win) => {
            const hud = win.getComputedStyle(win.document.querySelector('#hud'), '::after');
            expect(hud.backgroundImage, 'damier ornament').to.contain('ornament');
            const body = win.getComputedStyle(win.document.body, '::after');
            expect(body.content, 'body ornament removed').to.equal('none');
        });
        cy.screenshot('ornaments-final', { capture: 'viewport', overwrite: true });
    });
});
