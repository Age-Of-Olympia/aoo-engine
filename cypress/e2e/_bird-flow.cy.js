/* Throwaway — full bird flow: switch, TP tool, console. */
describe('bird flow', () => {
    it('switch to bird then use admin powers', () => {
        cy.viewport(1440, 900);
        cy.login('Thyrias', 'test');

        /* Page PNJs : clic sur l'oiseau */
        cy.visit('pnjs.php');
        cy.wait(800);
        cy.screenshot('bird-roster', { capture: 'viewport', overwrite: true });
        cy.get('article.pnj[data-id="-1000023"]').click();
        cy.wait(1500);

        /* Retour au jeu : on est l'oiseau */
        cy.url().should('include', 'index.php');
        cy.wait(800);
        const diag = {};
        cy.window().then((w) => {
            diag.isAdmin = w.isAdmin;
            diag.hud = !!w.document.getElementById('hud');
        });

        /* Clic droit sur une case : le bouton TP doit apparaître */
        cy.get('.case').first().rightclick({ force: true });
        cy.wait(500);
        cy.get('#admin-coords').then(($c) => {
            diag.adminCoordsHtml = $c.html();
            cy.writeFile('/tmp/bird-diag.json', JSON.stringify(diag, null, 1));
        });
        cy.screenshot('bird-rightclick', { capture: 'viewport', overwrite: true });

        /* Console admin */
        cy.get('body').trigger('keydown', { code: 'Backquote', key: '²' });
        cy.get('#console-wrapper', { timeout: 5000 }).should('be.visible');
        cy.get('#input-line').type('option Dorna showBlockedTiles', { force: true });
        cy.get('body').trigger('keydown', { code: 'Enter', key: 'Enter' });
        cy.wait(1200);
        cy.get('#console').should('contain.text', 'showBlockedTiles');
        cy.screenshot('bird-console', { capture: 'viewport', overwrite: true });
        cy.get('#input-line').clear({ force: true }).type('option Dorna showBlockedTiles', { force: true });
        cy.get('body').trigger('keydown', { code: 'Enter', key: 'Enter' });
        cy.wait(800);
    });
});
