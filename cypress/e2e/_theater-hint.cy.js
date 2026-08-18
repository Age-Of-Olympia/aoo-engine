/* Throwaway — arming must not move buttons nor overlap them. */
describe('theater armed hint v2', () => {
    it('buttons static, hint in reserved strip', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1000);
        cy.get('#hud-theater-btn').click();
        cy.wait(400);
        cy.get('image#current-player-avatar').then(($p) => {
            cy.get('.case[x="' + $p.attr('x') + '"][y="' + $p.attr('y') + '"]').click({ force: true });
        });
        cy.wait(1500);

        let before;
        cy.window().then((w) => {
            before = [...w.document.querySelectorAll('#hud-actions .action')]
                .map((b) => { const r = b.getBoundingClientRect(); return r.left + ',' + r.top; });
        });

        cy.get('#hud-actions .action:not(.close-card)').last().click();
        cy.wait(400);
        cy.get('#hud-action-hint').should('be.visible');

        cy.window().then((w) => {
            /* Le bouton armé porte un scale(1.12) volontaire (feedback,
             * transform sans reflow) : on vérifie que LES AUTRES ne
             * bougent pas d'un pixel. */
            const after = [...w.document.querySelectorAll('#hud-actions .action')]
                .map((b) => b.classList.contains('hud-action--armed')
                    ? 'armed'
                    : (() => { const r = b.getBoundingClientRect(); return r.left + ',' + r.top; })());
            const beforeFiltered = before.filter((v, i) => after[i] !== 'armed');
            const afterFiltered = after.filter((v) => v !== 'armed');
            expect(afterFiltered.join('|')).to.eq(beforeFiltered.join('|'));

            const hint = w.document.getElementById('hud-action-hint').getBoundingClientRect();
            const rects = [...w.document.querySelectorAll('#hud-actions .action')].map((b) => {
                const r = b.getBoundingClientRect();
                return { cls: b.className, top: Math.round(r.top), bottom: Math.round(r.bottom) };
            });
            cy.writeFile('/tmp/hint-rects.json', JSON.stringify({ hint: { top: Math.round(hint.top), bottom: Math.round(hint.bottom) }, rects: rects }, null, 1));
            const overlap = rects.some((r) => r.bottom > hint.top && r.top < hint.bottom);
            expect(overlap, 'hint overlaps a button').to.be.false;

        });
        cy.screenshot('theater-hint-v2', { capture: 'viewport', overwrite: true });
    });
});
