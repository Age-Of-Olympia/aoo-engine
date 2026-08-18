/* Throwaway — mesure l'encre de chaque glyphe du rail pour calibrer le centrage. */
describe('rail glyph metrics', () => {
    it('measures ink offsets', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1200);

        cy.window().then((win) => {
            const doc = win.document;
            const canvas = doc.createElement('canvas');
            canvas.width = 100; canvas.height = 100;
            const ctx = canvas.getContext('2d');
            const out = [];
            doc.querySelectorAll('#hud-rail #menu .ra').forEach((el) => {
                const cls = [...el.classList].find(c => c.startsWith('ra-') && c !== 'ra');
                const content = win.getComputedStyle(el, '::before').content;
                const ch = content.replace(/"/g, '');
                const font = win.getComputedStyle(el, '::before').fontFamily;
                ctx.font = '22px ' + font;
                const m = ctx.measureText(ch);
                /* centre de l'encre vs centre de la boîte d'avance */
                const inkCx = (-m.actualBoundingBoxLeft + m.actualBoundingBoxRight) / 2;
                const advCx = m.width / 2;
                const inkCy = (-m.actualBoundingBoxAscent + m.actualBoundingBoxDescent) / 2;
                /* verticale : le flex centre la boîte d'em ; l'encre est
                 * centrée si son centre est à mi-hauteur d'x-height…
                 * approximons : centre d'em ≈ -(ascent-descent)/2 pour
                 * la police d'icônes (em carré). */
                out.push(cls + ' dx=' + (advCx - inkCx).toFixed(1)
                    + ' inkCy=' + inkCy.toFixed(1)
                    + ' asc=' + m.actualBoundingBoxAscent.toFixed(1)
                    + ' desc=' + m.actualBoundingBoxDescent.toFixed(1));
            });
            /* le rapport sort dans l'échec du test (stdout du run) */
            throw new Error('METRICS\n' + out.join('\n'));
        });
    });
});
