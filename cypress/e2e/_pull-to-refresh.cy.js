/* Throwaway — tirer pour rafraîchir (mobile). */
describe('pull to refresh', () => {
    function touch(win, el, type, x, y) {
        const $el = win.$(el);
        const ev = win.$.Event(type);
        ev.originalEvent = null; /* touchesOf lit e.touches en repli */
        ev.touches = type === 'touchend' ? [] : [{ clientX: x, clientY: y }];
        ev.target = $el[0];
        $el.trigger(ev);
    }

    it('drag down past threshold reloads, small drag does not', () => {
        cy.viewport(390, 844);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1000);

        /* marqueur volatile : il disparaît si la page recharge */
        cy.window().then((win) => { win.__noReload = true; });

        /* petit tirage : indicateur visible mais pas armé, pas de reload */
        cy.window().then((win) => {
            touch(win, '#hud-topbar', 'touchstart', 200, 100);
            touch(win, '#hud-topbar', 'touchmove', 200, 140);
        });
        cy.get('#hud-ptr').should('have.class', 'hud-ptr--visible')
            .should('not.have.class', 'hud-ptr--armed');
        cy.screenshot('ptr-pulling', { capture: 'viewport', overwrite: true });
        cy.window().then((win) => {
            touch(win, '#hud-topbar', 'touchend', 200, 140);
        });
        cy.get('#hud-ptr').should('not.have.class', 'hud-ptr--visible');
        cy.window().its('__noReload').should('be.true');

        /* tirage au-delà du seuil : armé puis rechargement */
        cy.window().then((win) => {
            touch(win, '#hud-topbar', 'touchstart', 200, 100);
            touch(win, '#hud-topbar', 'touchmove', 200, 200);
        });
        cy.get('#hud-ptr').should('have.class', 'hud-ptr--armed');
        cy.screenshot('ptr-armed', { capture: 'viewport', overwrite: true });
        cy.window().then((win) => {
            touch(win, '#hud-topbar', 'touchend', 200, 200);
        });
        /* la page a rechargé : le marqueur volatile a disparu */
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.window().should((win) => {
            expect(win.__noReload).to.be.undefined;
        });
    });

    it('board drag does not trigger refresh', () => {
        cy.viewport(390, 844);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1000);

        cy.window().then((win) => {
            touch(win, '#game-map', 'touchstart', 200, 300);
            touch(win, '#game-map', 'touchmove', 200, 450);
        });
        cy.get('#hud-ptr').should('not.exist');
    });
});
