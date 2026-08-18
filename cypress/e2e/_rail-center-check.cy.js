/* Throwaway — centre d'encre réel de chaque icône du rail vs centre du bouton. */
describe('rail centering check', () => {
    it('reports ink-center offsets', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1200);

        cy.window().then((win) => {
            const doc = win.document;
            const canvas = doc.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const out = [];
            doc.querySelectorAll('#hud-rail #menu > a > button').forEach((btn) => {
                const span = btn.querySelector('.ra');
                if (!span) { return; }
                const cls = [...span.classList].find(c => c.startsWith('ra-') && c !== 'ra');
                const st = win.getComputedStyle(span, '::before');
                const ch = st.content.replace(/"/g, '');
                ctx.font = st.fontSize + ' ' + st.fontFamily;
                const m = ctx.measureText(ch);
                const spanRect = span.getBoundingClientRect();
                const btnRect = btn.getBoundingClientRect();
                /* baseline du span : approx via fontBoundingBox si dispo */
                const asc = m.fontBoundingBoxAscent || 22;
                const desc = m.fontBoundingBoxDescent || 0;
                const lineH = spanRect.height;
                const baselineY = spanRect.top + (lineH - (asc + desc)) / 2 + asc;
                /* origine du stylo = bord gauche du span ; l'encre va de
                 * pen-actualLeft à pen+actualRight */
                const inkCx = spanRect.left + (m.actualBoundingBoxRight - m.actualBoundingBoxLeft) / 2;
                const inkCy = baselineY + (m.actualBoundingBoxDescent - m.actualBoundingBoxAscent) / 2;
                const dx = inkCx - (btnRect.left + btnRect.width / 2);
                const dy = inkCy - (btnRect.top + btnRect.height / 2);
                out.push(cls + ' dx=' + dx.toFixed(1) + ' dy=' + dy.toFixed(1));
            });
            throw new Error('CENTERS\n' + out.join('\n'));
        });
    });
});
