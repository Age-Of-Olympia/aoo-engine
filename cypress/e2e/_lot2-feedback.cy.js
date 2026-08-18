/* Throwaway — Lot 2 des retours joueurs : les flux restent dans les panneaux. */
describe('lot 2 panel escapes', () => {
    it('account flows stay in the panel', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1000);

        /* Profil : mdj et histoire absents, mot de passe présent */
        cy.get('#hud-rail a[href="account.php"]').first().click({ force: true });
        cy.wait(1200);
        cy.get('.hud-panel--open .hud-panel-content')
            .should('contain.text', 'Changer Mot de Passe')
            .should('not.contain.text', 'Modifier son MDJ')
            .should('not.contain.text', 'Modifier son Histoire');
        cy.screenshot('lot2-profil-options', { capture: 'viewport', overwrite: true });

        /* Changer de mot de passe : formulaire DANS le panneau */
        cy.get('.hud-panel--open a[href="account.php?changePsw"]').first().click();
        cy.wait(1200);
        cy.url().should('include', 'index.php');
        cy.get('.hud-panel--open .hud-panel-content').should('contain.text', 'Changement du mot de passe');
        cy.screenshot('lot2-changepsw-panel', { capture: 'viewport', overwrite: true });

        /* Soumission interceptée : mauvais ancien mdp → réponse dans le panneau */
        cy.get('.hud-panel--open input[name="old"]').type('wrongpass');
        cy.get('.hud-panel--open input[name="new"]').type('test2');
        cy.get('.hud-panel--open input[name="new2"]').type('test2');
        cy.get('.hud-panel--open #submit-button').click();
        cy.wait(1200);
        cy.url().should('include', 'index.php');
        cy.get('.hud-panel--open .hud-panel-content').should('contain.text', 'pas le bon');
        cy.screenshot('lot2-changepsw-error-in-panel', { capture: 'viewport', overwrite: true });

        /* Galerie de portraits dans le panneau (retour au Profil d'abord) */
        cy.get('.hud-panel--open .hud-panel-back').click();
        cy.wait(1200);
        cy.get('.hud-panel--open a[href="account.php?portraits"]').first().click();
        cy.wait(1500);
        cy.url().should('include', 'index.php');
        cy.get('.hud-panel--open .hud-panel-content img[data-img]').should('exist');
        cy.screenshot('lot2-portraits-panel', { capture: 'viewport', overwrite: true });

        /* Fiche : bouton Modifier de l'histoire → éditeur en panneau */
        cy.get('#hud-chip-name').click();
        cy.wait(1200);
        cy.get('.hud-panel--open .hud-panel-content a[href="account.php?story"]').first().click();
        cy.wait(1200);
        cy.url().should('include', 'index.php');
        cy.get('.hud-panel--open .hud-panel-content textarea').should('exist');
        cy.screenshot('lot2-story-editor-panel', { capture: 'viewport', overwrite: true });
    });

    it('inventory equip confirm + panel stays open', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1000);

        cy.get('#show-inventory').click({ force: true });
        cy.wait(1500);
        cy.get('.hud-panel--open .hud-panel-content .item-case').should('exist');
        cy.screenshot('lot2-inventory-icons', { capture: 'viewport', overwrite: true });

        /* Déséquiper le Bâton de marche : confirmation puis panneau
         * rechargé, toujours ouvert */
        cy.get('.hud-panel--open .row-action--worn').first().click();
        cy.wait(600);
        cy.get('.aoo-dialog').should('contain.text', 'Déséquiper');
        cy.screenshot('lot2-unequip-confirm', { capture: 'viewport', overwrite: true });
        cy.get('.aoo-dialog-ok').click();
        cy.wait(2000);
        cy.url().should('include', 'index.php');
        cy.get('.hud-panel--open .hud-panel-content .item-case').should('exist');
        cy.screenshot('lot2-after-unequip-panel-open', { capture: 'viewport', overwrite: true });
    });

    it('forum reply inside the panel and fullpage button', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait(1000);

        /* Ouvre un fil de missives puis répond, tout en panneau */
        cy.get('#hud-rail a[href="forum.php?forum=Missives"]').click({ force: true });
        cy.wait(1200);
        cy.get('.hud-panel--open .hud-panel-content a[href*="topic="]').first().click();
        cy.wait(1500);
        cy.get('.hud-panel--open button.reply').click();
        cy.wait(1500);
        cy.url().should('include', 'index.php');
        cy.get('.hud-panel--open .hud-panel-content textarea').should('exist');
        cy.screenshot('lot2-reply-panel', { capture: 'viewport', overwrite: true });

        cy.get('.hud-panel--open .hud-panel-content textarea').type('Réponse de test depuis le panneau.');
        cy.get('.hud-panel--open button.submit').click();
        cy.wait(2000);
        cy.url().should('include', 'index.php');
        cy.get('.hud-panel--open .hud-panel-content').should('contain.text', 'Réponse de test depuis le panneau.');
        cy.screenshot('lot2-reply-posted-in-panel', { capture: 'viewport', overwrite: true });

        /* Bouton pleine page : depuis le panneau fil → forum.php */
        cy.get('.hud-panel--open .hud-panel-fullpage').click();
        cy.wait(1500);
        cy.url().should('include', 'forum.php');
        cy.screenshot('lot2-fullpage-from-panel', { capture: 'viewport', overwrite: true });
    });
});
