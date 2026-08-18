/* Throwaway probe — landing fold with pinned first partner logo. */
describe('landing probe', () => {
    it('captures fold composition', () => {
        cy.viewport(1366, 768);
        cy.visit('/');
        cy.get('#index-menu', { timeout: 10000 }).should('be.visible');
        cy.wait(800);
        cy.get('#landing-scroll-hint').then(($el) => {
            const r = $el[0].getBoundingClientRect();
            const cs = window.getComputedStyle ? $el[0].ownerDocument.defaultView.getComputedStyle($el[0]) : null;
            cy.log(`rect ${Math.round(r.left)},${Math.round(r.top)} ${Math.round(r.width)}x${Math.round(r.height)}`);
            cy.log(`display=${cs.display} opacity=${cs.opacity} font-size=${cs.fontSize}`);
            cy.writeFile('/tmp/pill-debug.txt',
                JSON.stringify({rect: {l: r.left, t: r.top, w: r.width, h: r.height},
                    display: cs.display, opacity: cs.opacity, position: cs.position,
                    alignSelf: cs.alignSelf, margin: cs.margin, background: cs.backgroundColor}));
        });
        cy.screenshot('fold-768', { capture: 'viewport', overwrite: true });
    });
});
