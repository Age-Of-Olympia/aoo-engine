describe('mobile selection + actions pane', () => {
    it('shows card and pinned actions in one pane', () => {
        cy.viewport(390, 844);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1200);

        cy.get('#hud-dots .hud-seg').should('have.length', 2);
        cy.get('#hud-topbar #hud-location').should('be.visible').and('contain', '(');
        cy.screenshot('sel-idle', { capture: 'viewport', overwrite: true });

        cy.get('#current-player-avatar').invoke('attr', 'data-coords').then(function (coords) {
            cy.get('.case[data-coords="' + coords + '"]').click({ force: true });
        });
        cy.wait(1500);
        cy.get('#hud-sel-pane #hud-actions .action').should('have.length.greaterThan', 0);
        cy.screenshot('sel-actions', { capture: 'viewport', overwrite: true });

        cy.get('#hud-sel-pane #hud-actions .action[data-action]:not(.close-card)').first().click();
        cy.wait(300);
        cy.screenshot('sel-armed', { capture: 'viewport', overwrite: true });

        cy.get('#hud-sel-pane .hud-sel-tile').should('not.be.visible');
        cy.get('#hud-sel-pane .hud-sel-tile-btn').click();
        cy.wait(300);
        cy.get('#hud-action-modal #case-coords button').should('be.visible');
        cy.screenshot('sel-tile-sheet', { capture: 'viewport', overwrite: true });
        cy.get('#hud-action-modal .hud-action-modal-close').click();
        cy.get('#hud-sel-pane .hud-sel #case-coords').should('exist');
    });

    it('desktop grid unchanged', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1200);
        cy.get('#hud-side').should('be.visible');
        cy.get('#hud-actions').should('be.visible');
        cy.get('.hud-pill--effects-count').should('not.exist');
        cy.screenshot('desktop', { capture: 'viewport', overwrite: true });
    });
});
