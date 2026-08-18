/* Throwaway — coords pinned bottom-right of the selection band whatever
 * the content; inventory mini icons survive a panel reload. */
describe('coords pin + inventory icons', () => {
    it('coords stay bottom-right, icons load twice', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1500);

        const coordsPinned = (label) => {
            cy.window().then((win) => {
                const band = win.document.getElementById('ajax-data');
                const coords = win.document.getElementById('case-coords');
                expect(coords, label + ': #case-coords exists').to.not.equal(null);
                const b = band.getBoundingClientRect();
                const c = coords.getBoundingClientRect();
                expect(b.right - c.right, label + ': right gap').to.be.within(0, 30);
                expect(b.bottom - c.bottom, label + ': bottom gap').to.be.within(0, 30);
            });
        };

        /* Case avec personnage (soi-même) */
        cy.get('.case[data-coords="0,-3"]').click({ force: true });
        cy.wait(1200);
        coordsPinned('self tile');
        cy.screenshot('coords-self', { capture: 'viewport', overwrite: true });

        /* Case vide : composition différente, coords au même endroit */
        cy.get('.case[data-coords="1,-2"]').click({ force: true });
        cy.wait(1200);
        coordsPinned('empty tile');
        cy.screenshot('coords-empty', { capture: 'viewport', overwrite: true });

        /* Inventaire : ouvrir, fermer, rouvrir — les vignettes _mini
         * doivent charger aussi à la seconde ouverture. */
        cy.get('#show-inventory').click();
        cy.wait(1500);
        cy.get('#show-inventory').click();
        cy.wait(500);
        cy.get('#show-inventory').click();
        cy.wait(2000);
        cy.window().then((win) => {
            const imgs = win.document.querySelectorAll('.hud-panel .item-list img');
            expect(imgs.length, 'item images present').to.be.greaterThan(0);
            let loaded = 0;
            imgs.forEach((img) => {
                if (img.src.indexOf('_mini') !== -1 && img.naturalWidth > 0) {
                    loaded++;
                }
            });
            expect(loaded, 'mini icons loaded after reopen').to.be.greaterThan(0);
        });
        cy.screenshot('inventory-icons', { capture: 'viewport', overwrite: true });
    });
});
