/* Throwaway — generalized panel back-stack. */
describe('panel back navigation', () => {
    beforeEach(() => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1500);
    });

    it('inventory -> craft row -> back to inventory', () => {
        cy.get('#show-inventory').click();
        cy.wait(2000);
        cy.get('.hud-panel--open .hud-panel-back').should('not.be.visible');
        cy.get('.hud-panel .item-case[data-id="89"] .row-action[data-action="craft"]').click();
        cy.wait(2000);
        cy.get('.hud-panel--open .hud-panel-title').should('contain', 'Artisanat');
        cy.get('.hud-panel--open .hud-panel-back').should('be.visible');
        cy.screenshot('back-craft', { capture: 'viewport', overwrite: true });
        cy.get('.hud-panel--open .hud-panel-back').click();
        cy.wait(2000);
        cy.get('.hud-panel--open .hud-panel-title').should('contain', 'Inventaire');
        cy.get('.hud-panel--open .item-case').should('exist');
        cy.get('.hud-panel--open .hud-panel-back').should('not.be.visible');
    });

    it('missives list -> topic -> back to list', () => {
        cy.get('#missive-btn').click();
        cy.wait(2000);
        cy.get('.hud-panel--open .hud-panel-title').should('contain', 'Missives');
        cy.get('.hud-panel--open a[href*="forum.php?topic="]').first().click();
        cy.wait(2000);
        cy.get('.hud-panel--open .hud-panel-back').should('be.visible').click();
        cy.wait(2000);
        cy.get('.hud-panel--open .hud-panel-title').should('contain', 'Missives');
        cy.screenshot('back-missives', { capture: 'viewport', overwrite: true });
    });

    it('closing the panel clears the history', () => {
        cy.get('#show-inventory').click();
        cy.wait(1500);
        cy.get('#show-craft').click();
        cy.wait(1500);
        cy.get('.hud-panel--open .hud-panel-back').should('be.visible');
        cy.get('.hud-panel--open .hud-panel-close').click();
        cy.wait(500);
        cy.get('#show-inventory').click();
        cy.wait(1500);
        cy.get('.hud-panel--open .hud-panel-back').should('not.be.visible');
    });
});
