/* Spec jetable — captures du HUD pour la passe de design (non commité) */
describe('HUD design screenshots', () => {
  it('mobile + desktop captures', () => {
    /* ---- Accueil (déconnecté) ---- */
    cy.viewport(1440, 900);
    cy.visit('index.php');
    cy.wait(1200);
    cy.screenshot('landing-desktop', { capture: 'viewport' });
    cy.viewport(390, 844);
    cy.wait(600);
    cy.screenshot('landing-mobile', { capture: 'viewport' });

    /* ---- Mobile 390x844 (iPhone 12-ish) ---- */
    cy.viewport(390, 844);
    cy.login('Cradek', 'test');
    cy.visit('index.php?hud=1');
    cy.get('#hud', { timeout: 15000 }).should('exist');
    cy.wait(1500);
    cy.screenshot('mobile-01-initial', { capture: 'viewport' });

    /* Observer Dorna (case adjacente) → fiche de sélection */
    cy.get('.case[data-coords="-1,-2"]').first().click({ force: true });
    cy.wait(1500);
    /* Le carrousel est déjà en position sélection (snap auto) */
    cy.screenshot('mobile-02-selection-dorna', { capture: 'viewport' });

    /* Position actions du carrousel */
    cy.get('#hud-carousel').then(($c) => {
      $c[0].scrollTo({ left: $c[0].clientWidth * 2 });
    });
    cy.wait(800);
    cy.screenshot('mobile-03-actions', { capture: 'viewport' });

    /* Armement d'une action : bandeau de confirmation */
    cy.get('#hud-actions .action').first().click({ force: true });
    cy.wait(400);
    cy.screenshot('mobile-03b-actions-armed', { capture: 'viewport' });

    /* Position minimap */
    cy.get('#hud-carousel').then(($c) => {
      $c[0].scrollTo({ left: 0 });
    });
    cy.wait(800);
    cy.screenshot('mobile-04-minimap', { capture: 'viewport' });

    /* Tiroir de navigation */
    cy.get('#hud-burger').click({ force: true });
    cy.wait(600);
    cy.screenshot('mobile-05-drawer', { capture: 'viewport' });
    cy.get('#hud-backdrop').click({ force: true });

    /* Bulle chat → sheet */
    cy.get('#hud-bubble').click({ force: true });
    cy.wait(800);
    cy.screenshot('mobile-06-chat-sheet', { capture: 'viewport' });

    /* ---- Desktop 1440x900 ---- */
    cy.viewport(1440, 900);
    cy.visit('index.php?hud=1');
    cy.get('#hud', { timeout: 15000 }).should('exist');
    cy.wait(1500);
    cy.screenshot('desktop-00-idle', { capture: 'viewport' });

    /* Zoom du damier : deux crans puis retour */
    cy.get('#hud-zoom-in').click().click();
    cy.wait(400);
    cy.screenshot('desktop-00b-zoomed', { capture: 'viewport' });
    cy.get('#hud-zoom-out').click().click();
    cy.wait(300);

    /* Calques d'affichage : popover ouvert */
    cy.get('#hud-layers-btn').click();
    cy.wait(300);
    cy.screenshot('desktop-00c-layers', { capture: 'viewport' });
    cy.get('#hud-layers-btn').click();

    cy.get('.case[data-coords="-1,-2"]').first().click({ force: true });
    cy.wait(1500);
    cy.screenshot('desktop-01-selection-dorna', { capture: 'viewport' });
  });
});

/* Audit des panneaux : chaque entrée du rail en capture (desktop) */
describe('HUD panels audit', () => {
  it('opens each panel', () => {
    cy.viewport(1440, 900);
    cy.login('Cradek', 'test');
    cy.visit('index.php?hud=1');
    cy.get('#hud', { timeout: 15000 }).should('exist');
    cy.wait(1200);

    const panels = [
      ['#show-inventory', 'panel-inventaire'],
      ['#show-craft', 'panel-artisanat'],
      ['#show-bank', 'panel-banque'],
      ['#show-spells', 'panel-sorts'],
      ['a[href^="logs.php"]', 'panel-evenements'],
      ['a[href="account.php"]', 'panel-profil'],
    ];
    panels.forEach(([sel, name]) => {
      cy.get('#hud-rail ' + sel).first().click({ force: true });
      cy.wait(1500);
      cy.screenshot(name, { capture: 'viewport' });
    });

    /* Caracs : flyout dédié */
    cy.get('#show-caracs').first().click({ force: true });
    cy.wait(1200);
    cy.screenshot('panel-caracs', { capture: 'viewport' });

    /* Fiche perso via le nom du chip */
    cy.get('#hud-chip-name').click({ force: true });
    cy.wait(1500);
    cy.screenshot('panel-fiche', { capture: 'viewport' });
  });
});

/* Audit des pages autonomes (thème paper-app) */
describe('Standalone pages audit', () => {
  it('captures each page', () => {
    cy.viewport(1440, 900);
    cy.visit('register.php');
    cy.wait(1000);
    cy.screenshot('page-register', { capture: 'viewport' });

    cy.login('Cradek', 'test');
    const pages = [
      ['forum.php', 'page-forum'],
      ['map.php?world', 'page-map'],
      ['classements.php', 'page-classements'],
      ['logs.php', 'page-logs'],
    ];
    pages.forEach(([url, name]) => {
      cy.visit(url);
      cy.wait(1200);
      cy.screenshot(name, { capture: 'viewport' });
    });
  });
});
