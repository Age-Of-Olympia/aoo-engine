/* Throwaway — Lot 1 des retours joueurs (doc juillet 2026). */
describe('lot 1 feedback fixes', () => {
    it('verifies the seven lot-1 fixes (desktop)', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1000);

        /* 1.4 — Classement : re-clic sur l'onglet actif recharge au
         * lieu de fermer le panneau */
        cy.get('#hud-topbar a[href="classements.php"]').first().click();
        cy.wait(1200);
        cy.get('.hud-panel--open .hud-panel-content').should('contain.text', 'Bourrins');
        cy.get('.hud-panel--open .hud-panel-content a[href*="classements.php?bourrins"]').first().click();
        cy.wait(1200);
        cy.get('.hud-panel--open').should('exist');
        /* re-clic sur le même onglet : le panneau doit RESTER ouvert */
        cy.get('.hud-panel--open .hud-panel-content a[href*="classements.php?bourrins"]').first().click();
        cy.wait(1200);
        cy.get('.hud-panel--open').should('exist');
        cy.screenshot('lot1-classement-reclick', { capture: 'viewport', overwrite: true });

        /* 1.5 — Forum : bouton « Tout marquer comme lu » sur l'accueil */
        cy.get('#hud-topbar a[href="forum.php"]').first().click();
        cy.wait(1200);
        cy.get('.hud-panel--open #forum-mark-all-read').should('exist');
        cy.screenshot('lot1-forum-home-button', { capture: 'viewport', overwrite: true });
        cy.get('.hud-panel--open #forum-mark-all-read').click();
        cy.wait(1500);
        /* le panneau recharge l'accueil du forum, pas de navigation */
        cy.url().should('include', 'index.php');
        cy.get('.hud-panel--open .hud-panel-content').should('contain.text', 'Derniers messages');
        cy.screenshot('lot1-forum-marked-read', { capture: 'viewport', overwrite: true });

        /* 1.7 — Fiche : matricule visible */
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(800);
        cy.get('#hud-topbar a[href^="infos.php?targetId="]').first().click();
        cy.wait(1200);
        cy.get('.hud-panel--open .hud-panel-content').should('contain.text', 'mat.');
        cy.screenshot('lot1-fiche-matricule', { capture: 'viewport', overwrite: true });

        /* 1.2 — Réputation lisible sur papier */
        cy.get('.hud-panel--open .hud-panel-content a[href*="reputation"]').first().click();
        cy.wait(1500);
        cy.get('.hud-panel--open #pr-wrapper').should('exist').then(($el) => {
            const color = getComputedStyle($el[0]).color;
            expect(color, 'encre pleine, plus de texte transparent').not.to.match(/rgba\(.*,\s*0\)/);
        });
        cy.screenshot('lot1-reputation', { capture: 'viewport', overwrite: true });

        /* 1.3 — Barre d'Xp encadrée (panneau Caractéristiques) */
        cy.get('#show-caracs').click({ force: true });
        cy.wait(1200);
        cy.get('.hud-panel--open .hud-xp-progress .progress-bar').should('exist').then(($el) => {
            const bw = getComputedStyle($el[0]).borderTopWidth;
            expect(parseFloat(bw), 'cadre visible').to.be.greaterThan(0);
        });
        cy.screenshot('lot1-xp-bar', { capture: 'viewport', overwrite: true });

        /* 1.6 — Quêtes : visible pour l'admin Cradek dans la page des
         * événements (le bouton a déménagé sur la ligne admin) */
        cy.get('#hud-feed-full').click({ force: true });
        cy.wait(1200);
        cy.get('.hud-panel--open .hud-panel-content a[href="logs.php?quests"]').should('exist');
        cy.screenshot('lot1-logs-admin-quests', { capture: 'viewport', overwrite: true });
    });

    it('hides quests from regular players (Thyrias)', () => {
        cy.viewport(1440, 900);
        /* Thyrias : seul perso sans isAdmin dans la base de dev */
        cy.login('Thyrias', 'test');
        /* pas d'option newHud : forçage par paramètre */
        cy.visit('index.php?hud=1');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1000);
        cy.get('#hud-feed-full').click({ force: true });
        cy.wait(1200);
        cy.get('.hud-panel--open .hud-panel-content').should('contain.text', 'Perception');
        cy.get('.hud-panel--open .hud-panel-content a[href="logs.php?quests"]').should('not.exist');
        cy.screenshot('lot1-logs-player-noquests', { capture: 'viewport', overwrite: true });
    });
});
