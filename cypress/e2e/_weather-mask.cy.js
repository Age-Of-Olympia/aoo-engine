/* Throwaway — weather masks aligned with the board in both views. */
describe('weather mask alignment', () => {
    function assertMaskAligned(w) {
        const svg = w.document.getElementById('svg-view');
        const mask = w.document.querySelector('#view .view-mask');
        const s = svg.getBoundingClientRect();
        const m = mask.getBoundingClientRect();
        const vb = svg.getAttribute('viewBox').split(/[ ,]+/).map(Number);
        const orig = (svg.dataset.origViewbox || svg.getAttribute('viewBox')).split(/[ ,]+/).map(Number);
        const scale = s.width / vb[2];
        const expLeft = s.left + (orig[0] - vb[0]) * scale;
        const expTop = s.top + (orig[1] - vb[1]) * scale;
        const expW = orig[2] * scale;
        expect(Math.abs(m.left - expLeft), 'left').to.be.lessThan(3);
        expect(Math.abs(m.top - expTop), 'top').to.be.lessThan(3);
        expect(Math.abs(m.width - expW), 'width').to.be.lessThan(3);
    }

    it('standard and theater', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1000);

        /* Injecte un masque météo synthétique puis force un refit */
        cy.window().then((w) => {
            const c = w.document.getElementById('svg-container');
            const d = w.document.createElement('div');
            d.className = 'view-mask';
            d.style.background = 'rgba(80,120,200,0.25)';
            c.appendChild(d);
        });
        cy.get('#hud-zoom-in').click();
        cy.get('#hud-zoom-out').click();
        cy.wait(400);
        cy.window().then(assertMaskAligned);
        cy.screenshot('mask-standard', { capture: 'viewport', overwrite: true });

        cy.get('#hud-theater-btn').click();
        cy.wait(600);
        cy.window().then(assertMaskAligned);
        cy.screenshot('mask-theater', { capture: 'viewport', overwrite: true });
    });
});
