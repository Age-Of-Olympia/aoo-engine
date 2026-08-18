/* Throwaway — mobile carousel: minimap pane audit. */
describe('mobile minimap pane', () => {
    it('opens the minimap pane and measures it', () => {
        cy.viewport(390, 844);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1200);

        /* Va au volet minimap (point 0) */
        cy.get('.hud-dot[data-index="0"]').click();
        cy.wait(800);
        cy.screenshot('minimap-pane', { capture: 'viewport', overwrite: true });

        cy.window().then((w) => {
            const box = w.document.getElementById('hud-minimap');
            const map = box ? box.querySelector('.hud-minimap-map') : null;
            const r = (el) => {
                if (!el) { return null; }
                const b = el.getBoundingClientRect();
                return { x: Math.round(b.x), y: Math.round(b.y), w: Math.round(b.width), h: Math.round(b.height) };
            };
            cy.writeFile('/tmp/minimap-diag.json', JSON.stringify({
                box: r(box),
                map: r(map),
                mapStyle: map ? map.getAttribute('style') : null,
                mapHtml: box ? box.innerHTML.slice(0, 300) : null,
            }, null, 2));
        });
    });
});
