/* Throwaway — dés peints uniquement sur vrai jet, 600 ms requête comprise.
 * Ordre : attaque D'ABORD (repos consomme tous les points restants). */
describe('dice roll display', () => {
    it('dice held ~600ms on a real roll (attack), none without a roll (repos)', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1200);

        /* --- Attaque de Dorna : jet réel → dés visibles puis résultat --- */
        cy.get('#game-map .case[data-coords="-1,-2"]').click({ force: true });
        cy.wait(1500);
        let t0;
        cy.get('#hud-actions .action[data-action="attaquer"]').click();
        cy.wait(300);
        cy.get('#hud-actions .action[data-action="attaquer"]').click().then(() => {
            t0 = Date.now();
        });
        cy.get('#hud-action-modal .hud-dice-roll img', { timeout: 2000 }).should('be.visible');
        cy.screenshot('dice-on-attack', { capture: 'viewport', overwrite: true });
        cy.get('#hud-action-modal .hud-dice-roll', { timeout: 5000 }).should('not.exist').then(() => {
            expect(Date.now() - t0, 'durée minimale des dés').to.be.at.least(500);
        });
        cy.get('#hud-action-modal').should('contain.text', 'Jet');
        cy.screenshot('dice-attack-result', { capture: 'viewport', overwrite: true });
        cy.get('#hud-action-modal .hud-action-modal-close').click();
        cy.wait(400);

        /* --- Repos : aucune ligne « Jet » → jamais de dés --- */
        cy.get('#game-map .case[data-coords="0,-3"]').click({ force: true });
        cy.wait(1500);
        cy.get('#hud-actions .action[data-action="repos"]').click();
        cy.wait(300);
        cy.get('#hud-actions .action[data-action="repos"]').click();
        cy.get('#hud-action-modal', { timeout: 4000 }).should('contain.text', 'repos');
        cy.get('#hud-action-modal .hud-dice-roll img').should('not.exist');
        cy.screenshot('dice-none-for-repos', { capture: 'viewport', overwrite: true });
    });
});
