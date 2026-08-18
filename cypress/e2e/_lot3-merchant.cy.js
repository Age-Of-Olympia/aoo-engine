/* Throwaway — panelisation marchand/école de guerre/récompenses + jauges. */
describe('merchant panels and pill gauges', () => {
    it('merchant panel, tabs stay in panel, gauges visible', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1000);

        /* Jauges des pilules : PV entamé → jauge partielle */
        cy.get('#hud-pill-pv .hud-pill-gauge-fill').should('exist')
            .invoke('attr', 'style').should('contain', '60%');
        cy.screenshot('lot3-pill-gauges', { capture: 'viewport', overwrite: true });

        /* Panneau marchand (ouvert comme le ferait un bouton d'action) */
        cy.window().then((win) => {
            win.hudOpenPanel('load_merchant.php?targetId=1', 'Marchand');
        });
        cy.wait(1500);
        cy.get('.hud-panel--open .hud-panel-content').should('contain.text', 'Saruta');
        cy.screenshot('lot3-merchant-dialog', { capture: 'viewport', overwrite: true });

        /* Onglet Offres de Vente : reste en panneau */
        cy.get('.hud-panel--open a[href="merchant.php?targetId=1&bids"]').click();
        cy.wait(1500);
        cy.url().should('include', 'index.php');
        cy.get('.hud-panel--open .hud-panel-content').should('contain.text', 'Offre');
        cy.screenshot('lot3-merchant-bids-panel', { capture: 'viewport', overwrite: true });

        /* Onglet Banque : reste en panneau */
        cy.get('.hud-panel--open a[href="merchant.php?targetId=1&bank"]').click();
        cy.wait(1500);
        cy.url().should('include', 'index.php');
        cy.screenshot('lot3-merchant-bank-panel', { capture: 'viewport', overwrite: true });

        /* Récompenses depuis la réputation : reste en panneau */
        cy.window().then((win) => {
            win.hudOpenPanel('load_infos.php?targetId=1&reputation', 'Réputation');
        });
        cy.wait(1500);
        cy.get('.hud-panel--open a[href="infos.php?targetId=1&rewards"]').click();
        cy.wait(1500);
        cy.url().should('include', 'index.php');
        cy.get('.hud-panel--open .hud-panel-content').should('contain.text', 'Collection de Cradek');
        cy.screenshot('lot3-rewards-panel', { capture: 'viewport', overwrite: true });
    });

    it('legacy full pages still render (regression)', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('merchant.php?targetId=1');
        cy.get('body').should('contain.text', 'Saruta');
        cy.screenshot('lot3-merchant-legacy-page', { capture: 'viewport', overwrite: true });
        cy.visit('infos.php?targetId=1&rewards');
        cy.get('body').should('contain.text', 'Collection de Cradek');
    });
});
