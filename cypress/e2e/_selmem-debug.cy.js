describe('selection memory debug', () => {
    it('inspects restore flow', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.intercept('POST', '**/observe.php').as('obs');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(800);
        cy.get('image#current-player-avatar').then(($p) => {
            cy.get('.case[x="' + $p.attr('x') + '"][y="' + $p.attr('y') + '"]').click({ force: true });
        });
        cy.wait('@obs');
        cy.wait(1000);
        cy.window().then((w) => {
            cy.log('stored=' + w.sessionStorage.getItem('hudSelCoords'));
        });
        cy.reload();
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(3000);
        cy.window().then((w) => {
            const d = w.document.getElementById('ajax-data');
            cy.writeFile('/tmp/selmem.json', JSON.stringify({
                stored: w.sessionStorage.getItem('hudSelCoords'),
                ajaxLen: d ? d.innerHTML.length : -1,
                ajaxHead: d ? d.innerHTML.slice(0, 150) : null,
            }, null, 1));
        });
    });
});
