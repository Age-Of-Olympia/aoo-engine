/* Throwaway — mobile: modal + dialog stay within the viewport. */
describe('mobile overflow probe', () => {
    it('modal and dialog fit the screen', () => {
        cy.viewport(390, 844);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(2000);

        cy.window().then((win) => {
            win.hudShowActionResult('<div>résultat de test</div>', false);
            win.aooConfirm('Confirmation de test — largeur de boîte de dialogue ?');
        });
        cy.wait(400);
        cy.window().then((win) => {
            const vw = win.document.documentElement.clientWidth;
            ['.hud-action-modal-sheet', '.hud-action-modal-close', '.aoo-dialog', '.aoo-dialog-ok']
                .forEach((sel) => {
                    const el = win.document.querySelector(sel);
                    expect(el, sel + ' exists').to.not.equal(null);
                    const r = el.getBoundingClientRect();
                    expect(r.right, sel + ' right edge').to.be.at.most(vw + 1);
                    expect(r.left, sel + ' left edge').to.be.at.least(-1);
                });
        });
        cy.screenshot('mobile-fits', { capture: 'viewport', overwrite: true });
    });
});
