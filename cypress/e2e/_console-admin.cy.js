/* Throwaway — admin console works inside the HUD. */
describe('admin console in HUD', () => {
    it('opens with backquote and executes a command', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1000);

        cy.get('body').trigger('keydown', { code: 'Backquote', key: '²' });
        cy.get('#console-wrapper', { timeout: 5000 }).should('be.visible');
        cy.screenshot('console-open', { capture: 'viewport', overwrite: true });

        cy.get('#input-line').type('option Dorna showBlockedTiles', { force: true });
        cy.get('body').trigger('keydown', { code: 'Enter', key: 'Enter' });
        cy.wait(1200);
        cy.get('#console').should('contain.text', 'showBlockedTiles');
        cy.screenshot('console-command', { capture: 'viewport', overwrite: true });

        /* Revert le toggle */
        cy.get('#input-line').type('option Dorna showBlockedTiles', { force: true });
        cy.get('body').trigger('keydown', { code: 'Enter', key: 'Enter' });
        cy.wait(800);
    });
});
