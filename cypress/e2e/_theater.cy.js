/* Throwaway — theater: flush wings + selection persists on reload. */
describe('theater v5', () => {
    it('wings flush, selection survives refresh', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1000);

        cy.get('#hud-theater-btn').click();
        cy.wait(400);
        cy.get('#hud-theater-chat-btn').click();
        cy.wait(400);

        cy.get('image#current-player-avatar').then(($p) => {
            cy.get('.case[x="' + $p.attr('x') + '"][y="' + $p.attr('y') + '"]').click({ force: true });
        });
        cy.wait(1500);
        cy.get('#ajax-data .hud-sel').should('exist');
        cy.screenshot('theater-flush', { capture: 'viewport', overwrite: true });

        /* Refresh : théâtre + chat + sélection tous restaurés */
        cy.reload();
        cy.get('#hud', { timeout: 10000 }).should('have.class', 'hud--theater');
        cy.get('#ajax-data .hud-sel', { timeout: 10000 }).should('exist');
        cy.get('#hud-side').should('be.visible');
        cy.screenshot('theater-restored', { capture: 'viewport', overwrite: true });
    });
});
