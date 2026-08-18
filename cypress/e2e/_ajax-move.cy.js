/* Throwaway — AJAX movement keeps zoom, no page reload. */
describe('hud ajax move', () => {
    it('moves without reload, keeps zoom', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.intercept('POST', '**/go.php*').as('go');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.get('#svg-view').should('exist');
        cy.wait(800);

        cy.window().then((w) => { w.__noReload = 'alive'; });

        cy.get('#hud-zoom-in').click().click();
        cy.wait(300);

        let hashBefore;
        let widthBefore;
        cy.get('#game-map').then(($m) => { hashBefore = $m.attr('data-map-hash'); });
        cy.get('#svg-view').then(($s) => { widthBefore = $s[0].style.width; });

        cy.get('.case.go').first().click({ force: true });
        cy.get('#go-rect', { timeout: 5000 }).should('be.visible');
        cy.get('#go-rect').click({ force: true });

        cy.wait('@go').then((i) => {
            cy.writeFile('/tmp/ajax-move-go.txt',
                'status=' + i.response.statusCode
                + ' body=' + String(i.response.body || '').trim().slice(0, 300));
        });

        cy.wait(2500);
        cy.window().its('__noReload').should('eq', 'alive');
        cy.get('#game-map').should(($m) => {
            expect($m.attr('data-map-hash')).to.not.eq(hashBefore);
        });
        cy.get('#svg-view').should(($s) => {
            expect($s[0].style.width).to.eq(widthBefore);
        });
        cy.screenshot('after-move', { capture: 'viewport', overwrite: true });

        /* Tuiles recliquables (bindMapView re-lié après le swap) */
        cy.get('.case.go').first().click({ force: true });
        cy.get('#ajax-data', { timeout: 5000 }).should('not.be.empty');
        cy.screenshot('after-move-observe', { capture: 'viewport', overwrite: true });
    });
});
