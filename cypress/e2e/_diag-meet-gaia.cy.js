/**
 * Harnais : rejoue le tutoriel entier dans un vrai navigateur, sur le HUD
 * recomposé, jusqu'à la modale de fin.
 *
 * Deux particularités d'exécution :
 *  - le volet Actions du HUD ARME au premier clic (capture sur
 *    #hud-actions) : tout clic d'action se fait en DEUX clics ;
 *  - un clic synthétique Cypress sur une case du damier est ignoré pendant
 *    les étapes à spotlight (les clics réels d'un joueur passent — vérifié
 *    à la main) : les cases se cliquent via trigger('click') de la page.
 */
describe('Tutorial HUD — le tutoriel de bout en bout', () => {
  before(() => {
    cy.clearCookies();
    cy.clearLocalStorage();
    cy.window().then((win) => win.sessionStorage.clear());
  });

  const step = (expected) => {
    cy.window({ timeout: 15000 }).should((win) => {
      expect(win.tutorialUI?.currentStep, `attendu: ${expected}`).to.eq(expected);
    });
    cy.wait(500);
  };

  const next = () => {
    cy.get('#tutorial-next', { timeout: 10000 }).should('be.visible').click();
  };

  /* Clic de case via le jQuery de la page (cf. en-tête), puis la flèche. */
  const moveTo = (coords) => {
    cy.window().then((win) => {
      win.jQuery(`.case[data-coords="${coords}"]`).trigger('click');
    });
    cy.wait(1200);
    cy.get('#go-rect', { timeout: 8000 }).should('be.visible').click({ force: true });
    cy.wait(3500);
  };

  /* Arm/confirm du HUD : premier clic arme, second exécute. */
  const clickAction = (selector) => {
    cy.get(selector, { timeout: 8000 }).should('exist').click({ force: true });
    cy.wait(600);
    cy.get(selector).click({ force: true });
  };

  it('welcome → deplete_movements', () => {
    cy.viewport(1400, 900);
    cy.login('Cradek', 'test');

    cy.request({ method: 'POST', url: '/api/tutorial/cancel.php', failOnStatusCode: false });
    cy.request('POST', '/api/tutorial/start.php').its('body.success').should('eq', true);

    cy.visit('/index.php?tutorial=resume');
    cy.window().then((win) => {
      win.__errs = [];
      win.addEventListener('error', (e) => win.__errs.push('err: ' + e.message));
      win.addEventListener('unhandledrejection', (e) => {
        win.__errs.push('rej: ' + (e.reason && (e.reason.stack || e.reason.message || String(e.reason))));
      });
    });
    cy.window().its('tutorialUI', { timeout: 15000 }).should('exist');

    step('welcome');
    next();
    step('your_character');
    next();

    /* 3 meet_gaia : cliquer Gaïa ouvre sa fiche recomposée. */
    step('meet_gaia');
    cy.window().then((win) => win.jQuery('.case[data-coords="1,0"]').trigger('click'));
    step('close_card');
    cy.screenshot('hud-03-gaia-card', { capture: 'viewport', overwrite: true });

    /* 4 close_card : le HUD n'a plus de croix — cliquer une case vide
     * change la sélection, et la fiche de Gaïa part avec elle. */
    cy.window().then((win) => win.jQuery('.case[data-coords="-1,0"]').trigger('click'));
    step('movement_intro');
    next();

    /* 6 first_move. */
    step('first_move');
    moveTo('-1,0');
    step('movement_limit_warning');
    next();

    /* 8 : les pilules du bandeau, plus aucun volet. */
    step('show_characteristics');
    cy.screenshot('hud-08-chips', { capture: 'viewport', overwrite: true });
    next();
    step('deplete_movements');

    /* 9 : chaque pas consomme — la pilule Mvt doit décroître. */
    const mvtPill = () => cy.get('#hud-pill-mvt .hud-pill-value', { timeout: 10000 });
    const depleteMoves = ['-2,0', '-1,0', '-2,0', '-1,0'];
    depleteMoves.forEach((coords, i) => {
      moveTo(coords);
      mvtPill().should(($v) => {
        expect($v.text().trim(), `pilule Mvt après le pas ${i + 1}`).to.eq(`${3 - i}/4`);
      });
    });
    cy.screenshot('hud-09-depleted', { capture: 'viewport', overwrite: true });
    step('movements_depleted_info');
    next();

    /* 11 : infobulle sous la pilule A. */
    step('actions_intro');
    cy.get('.tutorial-tooltip', { timeout: 8000 }).should('be.visible');
    cy.screenshot('hud-11-actions-intro', { capture: 'viewport', overwrite: true });
    next();

    /* 12 → 13 : cliquer son personnage, l'infobulle du volet vient de gauche. */
    step('click_yourself');
    cy.window().then((win) => win.jQuery('.case[data-coords="-1,0"]').trigger('click'));
    step('actions_panel_info');
    cy.get('.tutorial-tooltip', { timeout: 8000 }).should('be.visible');
    cy.wait(700);
    cy.screenshot('hud-13-actions-panel-info', { capture: 'viewport', overwrite: true });
    next();

    /* 14 : simple annonce de l'arbre — rien à fermer, la fiche se
     * referme d'elle-même (auto_close_card) en quittant l'étape. */
    step('close_card_for_tree');
    next();

    /* 15 : le nain fini son épuisement en (-1,0), DIAGONALEMENT voisin de
     * l'arbre (0,1) — la sonde d'entrée doit faire passer l'étape seule,
     * sans exiger un pas de plus. */
    cy.window({ timeout: 15000 }).should((win) => {
      expect(win.tutorialUI?.currentStep, 'déjà voisin de l\'arbre : walk_to_tree passe seul')
        .to.eq('observe_tree');
    });
    cy.screenshot('hud-15-auto-adjacent', { capture: 'viewport', overwrite: true });

    /* 16 : cliquer l'arbre — la pastille dit « Récoltable ». */
    cy.wait(800);
    cy.window().then((win) => win.jQuery('.case[data-coords="0,1"]').trigger('click'));
    cy.wait(3000);
    cy.window().then((win) => {
      const $ = win.jQuery;
      cy.writeFile('tmp/diag-observe.json', {
        currentStep: win.tutorialUI?.currentStep,
        loadedStepData: win.tutorialUI?.stepData?.step_id,
        panelObserver: !!win.tutorialUI?.panelObserver,
        isAdvancing: win.tutorialUI?.isAdvancing,
        tooltipText: ($('.tutorial-tooltip').text() || '').replace(/\s+/g, ' ').slice(0, 80),
        uiCardDisplayed: win.tutorialUI?.isElementDisplayed('#ui-card'),
        badge: ($('#ajax-data .building-status').text() || '').trim(),
        errors: (win.__errs || []).slice(0, 6),
      });
    });
    step('tree_info');
    cy.get('#ajax-data .building-status', { timeout: 8000 }).should('contain', 'Récoltable');
    cy.screenshot('hud-17-tree-recoltable', { capture: 'viewport', overwrite: true });
    next();

    /* 17.5 : revenir à sa propre fiche. */
    step('click_yourself_for_gather');
    cy.window().then((win) => win.jQuery('.case[data-coords="-1,0"]').trigger('click'));

    /* 18 : fouiller (arm/confirm du volet). */
    step('use_fouiller');
    clickAction('#hud-actions .action[data-action="fouiller"]');
    step('action_consumed');
    next();

    /* 21 : l'inventaire s'ouvre en PANNEAU coulissant, pas en pleine page. */
    step('open_inventory');
    cy.get('#show-inventory').click({ force: true });
    cy.location('pathname').should('not.contain', 'inventory.php');
    cy.get('.hud-panel .item-case[data-name="Bois"]', { timeout: 12000 }).should('be.visible');
    step('inventory_wood');
    cy.screenshot('hud-22-inventory-panel', { capture: 'viewport', overwrite: true });
    next();

    /* 23 : fermer par la croix du panneau. */
    step('close_inventory');
    cy.get('.hud-panel-close:visible', { timeout: 8000 }).first().click({ force: true });
    step('combat_intro');
    next();

    /* 25 : l'ennemi apparaît (spawn serveur + reload forcé). */
    cy.wait(4000);
    step('enemy_spawned');
    cy.screenshot('hud-25-enemy-spawned', { capture: 'viewport', overwrite: true });
    next();

    /* 26 : rejoindre l'ennemi — il est à (2,1) ABSOLU, quel que soit
     * l'endroit d'où le joueur a récolté. (1,1) en est voisin diagonal ;
     * après le reload du pas, la sonde d'entrée valide l'adjacence. */
    cy.wait(3000);
    step('walk_to_enemy');
    moveTo('0,0');
    moveTo('1,1');
    cy.window({ timeout: 15000 }).should((win) => {
      expect(win.tutorialUI?.currentStep, 'voisin de l\'ennemi').to.eq('click_enemy');
    });

    /* 27 : cibler l'ennemi. */
    cy.wait(800);
    cy.window().then((win) => win.jQuery('.case[data-coords="2,1"]').trigger('click'));
    step('attack_enemy');
    cy.screenshot('hud-28-attack', { capture: 'viewport', overwrite: true });

    /* 28 : Corps à corps (arm/confirm). */
    clickAction('#hud-actions .action[data-action="melee"]');
    step('attack_result');
    next();

    /* 30 : fin. */
    step('tutorial_complete');
    cy.get('#tutorial-next', { timeout: 8000 }).should('be.visible').click();
    cy.get('#tutorial-complete-modal', { timeout: 8000 }).should('be.visible');
    cy.screenshot('hud-30-complete', { capture: 'viewport', overwrite: true });
  });
});
