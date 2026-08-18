/* Throwaway — Marchander depuis le plateau, dialogue → panneau, jauges intégrées. */
describe('merchant action button and dialog links', () => {
    it('Marchander opens the panel, dialog bank link stays in panel', () => {
        cy.viewport(1440, 900);
        cy.login('Dorna', 'test');
        cy.visit('index.php?hud=1');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1500);

        /* Dorna a un tutoriel en cours dans la base de dev : écarter
         * la modale de reprise si elle couvre la page. */
        cy.get('body').then(($b) => {
            const later = $b.find('#tutorial-resume-modal button:contains("Plus tard")');
            if (later.length) {
                cy.wrap(later.first()).click();
                cy.wait(400);
            }
        });

        /* Lien Marchander identique à celui d'observe.php : bouton
         * .action sans data-action dans un <a href> — il doit ouvrir
         * le panneau, pas POSTer action.php. */
        cy.window().then((win) => {
            /* ouvre directement le panneau comme le ferait le clic
             * routé sur le lien Marchander */
            win.$(win.document.body).append('<a id="_probe" href="merchant.php?targetId=1"><button class="action"><span class="action-name">Marchander</span></button></a>');
        });
        cy.get('#_probe').click({ force: true });
        cy.wait(1500);
        cy.url().should('include', 'index.php');
        cy.get('#hud-action-modal:visible').should('not.exist');
        cy.get('.hud-panel--open .hud-panel-content').should('contain.text', 'Saruta');
        cy.screenshot('fix2-marchander-panel', { capture: 'viewport', overwrite: true });

        /* Option de dialogue « Banque » : reste dans le panneau */
        cy.get('.hud-panel--open .node-option[data-go="banque"]').click();
        cy.wait(600);
        cy.get('.hud-panel--open .node-option[data-url*="&bank"]').click();
        cy.wait(1500);
        cy.url().should('include', 'index.php');
        cy.get('.hud-panel--open .hud-panel-content').should('contain.text', 'Banque');
        cy.screenshot('fix2-dialog-bank-panel', { capture: 'viewport', overwrite: true });
    });

    it('gauges are integrated tints (Cradek wounded)', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1000);
        cy.get('#hud-pill-pv.hud-pill--gauge')
            .invoke('attr', 'style').should('contain', '--hud-missing: 40%');
        cy.screenshot('fix2-gauges-integrated', { capture: 'viewport', overwrite: true });
    });
});
