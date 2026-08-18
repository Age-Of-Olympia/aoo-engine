/* Throwaway — mobile audit: screenshot every surface at 390px and dump
 * page-level horizontal overflow per state. */
describe('mobile tour', () => {
    const report = [];

    const overflow = (win, label) => {
        const doc = win.document;
        const vw = doc.documentElement.clientWidth;
        report.push(label + ': htmlScrollW=' + doc.documentElement.scrollWidth
            + ' bodyScrollW=' + doc.body.scrollWidth + ' vw=' + vw);
        /* éléments visibles dont la boîte dépasse à droite, hors damier
         * (défilement interne voulu) */
        doc.querySelectorAll('body *').forEach((el) => {
            if (el.closest('#game-map') || el.closest('svg')) { return; }
            const r = el.getBoundingClientRect();
            if (r.width > 0 && r.height > 0 && r.right > vw + 2 && r.left < vw
                && win.getComputedStyle(el).visibility !== 'hidden') {
                const id = (el.id ? '#' + el.id : '') + (el.className && typeof el.className === 'string' ? '.' + el.className.split(' ').join('.') : '');
                report.push('  OFF ' + label + ': ' + (id || el.tagName).slice(0, 80) + ' right=' + Math.round(r.right));
            }
        });
    };

    it('tours the surfaces', () => {
        cy.viewport(390, 844);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(2500);

        cy.window().then((w) => overflow(w, 'idle'));
        cy.screenshot('m-idle', { capture: 'viewport', overwrite: true });

        /* volet sélection : cliquer la case du joueur */
        cy.get('#hud-location').invoke('text').then((txt) => {
            const m = txt.match(/\((-?\d+), (-?\d+),/);
            cy.get('.case[data-coords="' + m[1] + ',' + m[2] + '"]').click({ force: true });
        });
        cy.wait(1500);
        cy.window().then((w) => overflow(w, 'selection'));
        cy.screenshot('m-selection', { capture: 'viewport', overwrite: true });

        /* volet actions (3e position du carrousel) */
        cy.get('.hud-dot[data-index="2"]').click();
        cy.wait(800);
        cy.window().then((w) => overflow(w, 'actions'));
        cy.screenshot('m-actions', { capture: 'viewport', overwrite: true });

        /* tiroir */
        cy.get('#hud-burger').click();
        cy.wait(600);
        cy.window().then((w) => overflow(w, 'drawer'));
        cy.screenshot('m-drawer', { capture: 'viewport', overwrite: true });

        /* panneau inventaire (bottom sheet) */
        cy.get('#show-inventory').click({ force: true });
        cy.wait(2000);
        cy.window().then((w) => overflow(w, 'panel-inventory'));
        cy.screenshot('m-panel-inventory', { capture: 'viewport', overwrite: true });

        /* panneau caractéristiques */
        cy.get('#hud-burger').click({ force: true });
        cy.wait(400);
        cy.get('#show-caracs').click({ force: true });
        cy.wait(2000);
        cy.window().then((w) => overflow(w, 'panel-caracs'));
        cy.screenshot('m-panel-caracs', { capture: 'viewport', overwrite: true });

        /* panneau classements (clone du tiroir) */
        cy.get('#hud-burger').click({ force: true });
        cy.wait(400);
        cy.get('#hud-rail a.hud-quick-clone[href="classements.php"]').click({ force: true });
        cy.wait(2000);
        cy.window().then((w) => overflow(w, 'panel-classements'));
        cy.screenshot('m-panel-classements', { capture: 'viewport', overwrite: true });

        /* modale de résultat + dialogue */
        cy.window().then((win) => {
            win.hudShowActionResult('<div>résultat de test</div>', false);
        });
        cy.wait(500);
        cy.window().then((w) => overflow(w, 'modal'));
        cy.screenshot('m-modal', { capture: 'viewport', overwrite: true });

        cy.then(() => {
            cy.writeFile('data_tests/mobile-overflow.txt', report.join('\n'));
        });
    });
});
