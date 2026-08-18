/* Throwaway — pinch on the board zooms the board only (mobile). */
describe('board pinch zoom', () => {
    it('two-finger spread grows the svg, page untouched', () => {
        cy.viewport(390, 844);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1500);

        cy.window().then((win) => {
            const map = win.document.getElementById('game-map');
            const svg = win.document.getElementById('svg-view');
            const w0 = svg.getBoundingClientRect().width;

            const touch = (id, x, y) => new win.Touch({ identifier: id, target: map, clientX: x, clientY: y });
            const fire = (type, touches) => map.dispatchEvent(new win.TouchEvent(type, {
                touches: touches, cancelable: true, bubbles: true
            }));

            fire('touchstart', [touch(1, 150, 300), touch(2, 250, 300)]);
            fire('touchmove', [touch(1, 100, 300), touch(2, 300, 300)]); /* écart x2 */
            fire('touchend', []);

            return new win.Promise((res) => win.requestAnimationFrame(() => win.requestAnimationFrame(res)))
                .then(() => {
                    const w1 = svg.getBoundingClientRect().width;
                    expect(w1, 'svg width after pinch').to.be.greaterThan(w0 * 1.3);
                    expect(win.getComputedStyle(map).touchAction).to.contain('pan');
                });
        });
        cy.screenshot('pinch-zoomed', { capture: 'viewport', overwrite: true });
    });
});
