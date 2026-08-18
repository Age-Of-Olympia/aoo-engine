/* Throwaway — voile de sang : fiche (soi + perception) et personnages. */
describe('pv veil', () => {
    it('own fiche, perceived target fiche, pnjs page', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(800);

        /* Fiche de Cradek (blessé 30/50) : voile 40 % */
        cy.get('#hud-chip-name').click();
        cy.wait(1200);
        cy.get('.hud-panel--open .pv-veil').should('exist');
        cy.screenshot('veil-own-fiche', { capture: 'viewport', overwrite: true });

        /* Fiche de Dorna (39/50, à portée de perception) : voile aussi */
        cy.window().then((win) => {
            win.hudOpenPanel('load_infos.php?targetId=2', 'Personnage');
        });
        cy.wait(1200);
        cy.get('.hud-panel--open .pv-veil').should('exist');
        cy.screenshot('veil-perceived-fiche', { capture: 'viewport', overwrite: true });

        /* Page des personnages : le blessé se repère dans la liste */
        cy.window().then((win) => {
            win.hudOpenPanel('load_pnjs.php', 'Personnages');
        });
        cy.wait(1200);
        cy.get('.hud-panel--open .pv-veil').should('exist');
        cy.screenshot('veil-pnjs-page', { capture: 'viewport', overwrite: true });
    });
});
