/**
 * Tutorial — production-readiness end-to-end test, on the HUD.
 *
 * A fresh account registers, logs in, and plays every step with the clicks
 * a player makes. Clicks are HUMAN clicks: at the centre of a visible
 * element, on whatever is drawn on top there — never `force: true`, never a
 * jQuery trigger on a hidden node. A step whose target is hidden or covered
 * by the tutorial's own tooltip fails here, as it would block a player.
 *
 * Every step helper asserts the step it starts on and the step it must land
 * on, so an auto-skip or a click that does nothing fails at once.
 *
 * Movement is position-driven (positions read in the database), so the same
 * choreography works for every race whatever its movement points.
 *
 * Run from the host (the devcontainer has no display):
 *   TEST_DB_HOST=<mariadb container ip> TEST_DB_NAME=aoo4 \
 *   ./node_modules/.bin/cypress run --spec cypress/e2e/tutorial-production-ready.cy.js \
 *     --browser electron --config baseUrl=http://localhost:9000 [--env race=elfe]
 * TEST_DB_NAME must be the database the web app uses.
 *
 * CRITICAL: single `it()` block — Cypress resets the session between blocks.
 */

describe('Tutorial System - Production Readiness Test', () => {
  const randomLetters = (n) => Array.from({ length: n }, () =>
    String.fromCharCode(97 + Math.floor(Math.random() * 26))
  ).join('');

  let raceData = { mvt: 4, pa: 2 }; /* populated in before() */
  const TEST_ACCOUNT = {
    name: `Cypresstest${randomLetters(8)}`,
    password: 'testpass123',
    email: `cypresstest${Date.now()}@test.com`,
    race: Cypress.env('race') || 'nain',
    playerId: null
  };

  const SHORT = 6000;   /* a step must advance within this after its gesture */
  const MEDIUM = 15000; /* entering a step: server round-trip + render */

  /* Board obstacles around the arena centre: Gaïa, the tree, the enemy. */
  const TREE = { x: 0, y: 1 };
  const ENEMY = { x: 2, y: 1 };
  const BLOCKED = new Set(['1,0', '0,1', '2,1']);

  let tutorialPlayerId;

  /* ============================================================
   * Human gestures
   * ============================================================ */

  const TUTORIAL_CHROME = '.tutorial-tooltip, #tutorial-overlay, #tutorial-controls, .tutorial-modal-overlay';

  /** Click the centre of the first visible match, on what is drawn there.
   * Board tiles are usually covered by their sprite (tree, character, the
   * move arrow): the click then lands on the sprite, as a player's does —
   * only the tutorial's own chrome on top is a failure. */
  const humanClick = (selector) => {
    cy.get(selector, { timeout: MEDIUM }).filter(':visible').first().should('be.visible').then(($el) => {
      const r = $el[0].getBoundingClientRect();
      const x = r.left + r.width / 2;
      const y = r.top + r.height / 2;
      cy.document().then((doc) => {
        const top = doc.elementFromPoint(x, y);
        expect(top, `${selector}: something must be drawn at its centre`).to.exist;
        if (!$el[0].contains(top)) {
          expect(Cypress.$(top).closest(TUTORIAL_CHROME).length, `${selector} must not be under the tutorial tooltip`).to.eq(0);
        }
        const body = doc.body.getBoundingClientRect();
        cy.get('body').click(x - body.left, y - body.top);
      });
    });
  };

  const tile = (x, y) => `.case[data-coords="${x},${y}"]`;

  /* Lazy (inside cy.then): tutorialPlayerId is only known once the
   * session started, after these commands were queued. */
  const playerPos = () => cy.then(() => cy.task('queryDatabase', {
    query: 'SELECT c.x, c.y FROM players p JOIN coords c ON c.id = p.coords_id WHERE p.id = ?',
    params: [tutorialPlayerId]
  })).then((rows) => ({ x: Number(rows[0].x), y: Number(rows[0].y) }));

  const resources = () => cy.then(() => cy.getPlayerResources(tutorialPlayerId));

  const currentStep = () => cy.window().then((win) => win.tutorialUI?.currentStep);

  /** One move: click the destination, then the move arrow. */
  const moveTo = (x, y) => {
    humanClick(tile(x, y));
    cy.get('#go-rect, #go-img', { timeout: 5000 }).filter(':visible').should('have.length.greaterThan', 0);
    humanClick('#go-rect, #go-img');
    cy.wait(2500); /* server move + board refresh */
  };

  /* ============================================================
   * Step contracts
   * ============================================================ */

  /** The 500 ms settle lets the step install its observers and place its
   * tooltip before the test acts — a player reads it first anyway. */
  const assertOnStep = (stepId) => {
    cy.window({ timeout: MEDIUM }).should((win) => {
      expect(win.tutorialUI?.currentStep, `expected currentStep=${stepId}`).to.eq(stepId);
    });
    cy.wait(800);
  };

  const assertAdvanced = (fromStep, toStep, what) => {
    cy.window({ timeout: SHORT }).should((win) => {
      expect(win.tutorialUI?.currentStep, `${what} must advance ${fromStep} → ${toStep}`).to.eq(toStep);
    });
  };

  const assertTooltipContains = (text) => {
    cy.get('.tutorial-tooltip', { timeout: 5000 }).should('be.visible').should('contain', text);
  };

  /** Info step: "Suivant". */
  const infoStep = (fromStep, toStep) => {
    assertOnStep(fromStep);
    humanClick('#tutorial-next');
    assertAdvanced(fromStep, toStep, 'Suivant');
  };

  /** Click a board tile (a character, the tree, an empty tile). */
  const tileStep = (fromStep, toStep, x, y) => {
    assertOnStep(fromStep);
    humanClick(tile(x, y));
    assertAdvanced(fromStep, toStep, `click on (${x},${y})`);
  };

  /** Click your own character, wherever it stands. */
  const selfStep = (fromStep, toStep) => {
    assertOnStep(fromStep);
    playerPos().then(({ x, y }) => humanClick(tile(x, y)));
    assertAdvanced(fromStep, toStep, 'click on own character');
  };

  const uiStep = (fromStep, toStep, selector) => {
    assertOnStep(fromStep);
    humanClick(selector);
    assertAdvanced(fromStep, toStep, `click on ${selector}`);
  };

  /** HUD action buttons arm on the first click and run on the second. */
  const actionStep = (fromStep, toStep, actionName) => {
    const button = `#hud-actions .action[data-action="${actionName}"]`;
    assertOnStep(fromStep);
    humanClick(button);
    cy.wait(600);
    currentStep().then((step) => {
      if (step === fromStep) {
        humanClick(button);
      }
    });
    cy.wait(2000); /* action round-trip */
    assertAdvanced(fromStep, toStep, `action ${actionName}`);
  };

  /** Walk until the step changes, along a shortest path to a tile beside
   * (Chebyshev 1, as the server checks) the target. An entry already beside
   * it advances on its own. */
  const walkBeside = (fromStep, toStep, target, budget = 6) => {
    currentStep().then((step) => {
      if (step !== fromStep || budget === 0) {
        return;
      }
      playerPos().then((p) => {
        const beside = (x, y) => Math.max(Math.abs(x - target.x), Math.abs(y - target.y)) === 1;
        if (beside(p.x, p.y)) {
          return;
        }
        /* BFS over the 8 neighbours inside the arena walls (-3..3). */
        const key = (x, y) => `${x},${y}`;
        const prev = new Map([[key(p.x, p.y), null]]);
        const queue = [[p.x, p.y]];
        let goal = null;
        while (queue.length && !goal) {
          const [x, y] = queue.shift();
          for (let dx = -1; dx <= 1 && !goal; dx++) {
            for (let dy = -1; dy <= 1 && !goal; dy++) {
              const nx = x + dx;
              const ny = y + dy;
              const k = key(nx, ny);
              if ((!dx && !dy) || prev.has(k) || BLOCKED.has(k) || Math.abs(nx) > 3 || Math.abs(ny) > 3) {
                continue;
              }
              prev.set(k, key(x, y));
              if (beside(nx, ny)) {
                goal = k;
              }
              queue.push([nx, ny]);
            }
          }
        }
        expect(goal, `a path beside (${target.x},${target.y})`).to.not.eq(null);
        let first = goal;
        while (prev.get(first) !== key(p.x, p.y)) {
          first = prev.get(first);
        }
        const [fx, fy] = first.split(',').map(Number);
        moveTo(fx, fy);
        walkBeside(fromStep, toStep, target, budget - 1);
      });
    });
  };

  const walkStep = (fromStep, toStep, target) => {
    cy.window({ timeout: MEDIUM }).should((win) => {
      expect([fromStep, toStep], `expected ${fromStep}`).to.include(win.tutorialUI?.currentStep);
    });
    cy.wait(800);
    walkBeside(fromStep, toStep, target);
    assertAdvanced(fromStep, toStep, `walking beside (${target.x},${target.y})`);
    playerPos().then(({ x, y }) => {
      const d = Math.max(Math.abs(x - target.x), Math.abs(y - target.y));
      expect(d, `player at (${x},${y}) must stand beside (${target.x},${target.y})`).to.eq(1);
    });
  };

  /* ============================================================
   * Hooks
   * ============================================================ */

  before(() => {
    cy.request(`/api/races/get.php?name=${TEST_ACCOUNT.race}`).then((response) => {
      expect(response.status).to.eq(200);
      expect(response.body.success).to.be.true;
      raceData = response.body.race;
      cy.log(`Race: ${raceData.name}, Max MVT: ${raceData.mvt}, Max PA: ${raceData.pa}`);
    });
  });

  before(() => {
    cy.clearCookies();
    cy.clearLocalStorage();
    cy.window().then((win) => win.sessionStorage.clear());
    /* Failed logins of earlier runs lock the account form for 5 minutes. */
    cy.task('queryDatabase', { query: 'DELETE FROM players_ips' });
  });

  it('Complete production validation: Fresh player through entire tutorial', () => {
    cy.viewport(1400, 900);

    /* ============================================================
     * PHASE 0: REGISTRATION
     * ============================================================ */
    cy.log(`═══ PHASE 0: REGISTER ${TEST_ACCOUNT.name} (race=${TEST_ACCOUNT.race}) ═══`);
    cy.register(TEST_ACCOUNT.name, TEST_ACCOUNT.race, TEST_ACCOUNT.password, TEST_ACCOUNT.email);
    cy.task('queryDatabase', {
      query: 'SELECT id FROM players WHERE name = ? ORDER BY id DESC LIMIT 1',
      params: [TEST_ACCOUNT.name]
    }).then((rows) => {
      expect(rows, 'the account must exist after registration').to.have.length(1);
      TEST_ACCOUNT.playerId = rows[0].id;
    });

    /* ============================================================
     * PHASE 1: LOGIN THROUGH THE LANDING FORM, TUTORIAL AUTO-START
     * ============================================================ */
    cy.log('═══ PHASE 1: LOGIN + TUTORIAL AUTO-START ═══');
    cy.visit('/index.php');
    cy.get('#index-button-play').click();
    cy.get('#name-input').type(TEST_ACCOUNT.name);
    cy.get('#psw-input').type(TEST_ACCOUNT.password);
    cy.get('#index-button-login').click();

    cy.window({ timeout: 30000 }).its('tutorialUI.currentStep').should('eq', 'welcome');
    cy.get('#hud', { timeout: MEDIUM }).should('exist');
    cy.get(`image[data-table="players"][data-coords="${TREE.x},${TREE.y}"]`, { timeout: MEDIUM }).should('exist');

    cy.then(() => {
      cy.validateTutorialState(TEST_ACCOUNT.playerId, {
        shouldExist: true,
        mode: 'first_time',
        completed: 0
      }).then((state) => {
        tutorialPlayerId = state.tutorial_player_id;
        cy.validatePlayerCoords(tutorialPlayerId, { plan: 'tut_*', x: 0, y: 0 });

        /* The session plan is a full copy of the template: same cells,
         * one entity per occupied template cell, the tree, Gaïa — and
         * Gaïa sits on the board's cell index, or every distance to her
         * reads as "too far". */
        cy.task('queryDatabase', {
          query: `SELECT
                    (SELECT COUNT(*) FROM coords WHERE plan = s.plan) AS plan_coords,
                    (SELECT COUNT(*) FROM coords WHERE plan = 'tutorial') AS template_coords,
                    (SELECT COUNT(DISTINCT c.x, c.y, c.z) FROM players p JOIN coords c ON c.id = p.coords_id
                      WHERE p.player_type IN ('building', 'resource') AND c.plan = 'tutorial') AS template_cells,
                    (SELECT COUNT(*) FROM players p JOIN coords c ON c.id = p.coords_id
                      WHERE p.player_type IN ('building', 'resource') AND c.plan = s.plan) AS instance_cells,
                    (SELECT COUNT(*) FROM players p JOIN coords c ON c.id = p.coords_id
                      WHERE p.player_type = 'resource' AND c.x = 0 AND c.y = 1 AND c.plan = s.plan) AS instance_tree,
                    (SELECT COUNT(*) FROM players p JOIN coords c ON c.id = p.coords_id
                      JOIN entity_cells ec ON ec.player_id = p.id
                      WHERE p.id < 0 AND p.name = 'Gaïa' AND c.plan = s.plan) AS gaia_cells
                  FROM (SELECT c.plan FROM players p JOIN coords c ON c.id = p.coords_id WHERE p.id = ?) AS s`,
          params: [tutorialPlayerId]
        }).then((rows) => {
          const r = rows[0];
          expect(Number(r.plan_coords), 'the session plan copies every template cell').to.eq(Number(r.template_coords));
          expect(Number(r.template_cells), 'the template must carry structures to copy').to.be.greaterThan(0);
          expect(Number(r.instance_cells), 'one structure per occupied template cell').to.eq(Number(r.template_cells));
          expect(Number(r.instance_tree), 'the gatherable tree stands at (0,1)').to.eq(1);
          expect(Number(r.gaia_cells), 'Gaïa is on the session plan, with her board cell').to.eq(1);
        });
      });
    });

    cy.window().then((win) => {
      expect(win.sessionStorage.getItem('tutorial_active')).to.equal('true');
    });

    /* ============================================================
     * PHASE 2: STEP CHOREOGRAPHY
     * ============================================================ */
    cy.log('═══ PHASE 2: STEP CHOREOGRAPHY ═══');

    infoStep('welcome', 'your_character');
    infoStep('your_character', 'meet_gaia');

    tileStep('meet_gaia', 'close_card', 1, 0);
    /* Next to Gaïa, her card shows her message, not "too far". */
    cy.get('#ajax-data').should('not.contain', 'trop éloigné');

    /* The card follows the selection: an empty tile replaces it. */
    tileStep('close_card', 'movement_intro', -1, -1);

    infoStep('movement_intro', 'first_move');

    assertOnStep('first_move');
    moveTo(-1, 0);
    assertAdvanced('first_move', 'movement_limit_warning', 'first move');

    assertTooltipContains(`${raceData.mvt} mouvements`);
    cy.get('.tutorial-tooltip').should('not.contain', '{max_mvt}');
    resources().then((r) => {
      expect(r.mvt, 'first_move does not consume: MVT still at race max').to.eq(raceData.mvt);
    });
    infoStep('movement_limit_warning', 'show_characteristics');
    infoStep('show_characteristics', 'deplete_movements');

    /* Bounce between (-1,0) and (-2,0) until the counter is empty. */
    assertOnStep('deplete_movements');
    assertTooltipContains(`${raceData.mvt} mouvements`);
    for (let i = 0; i < raceData.mvt; i++) {
      playerPos().then(({ x }) => moveTo(x === -1 ? -2 : -1, 0));
    }
    assertAdvanced('deplete_movements', 'movements_depleted_info', `${raceData.mvt} moves`);
    resources().then((r) => {
      expect(r.mvt, 'deplete_movements leaves 0 MVT').to.eq(0);
    });

    infoStep('movements_depleted_info', 'actions_intro');
    resources().then((r) => {
      expect(r.pa, `auto_restore gives back the race PA (${raceData.pa}), not more`).to.eq(raceData.pa);
      expect(r.mvt, `auto_restore gives back the race MVT (${raceData.mvt})`).to.eq(raceData.mvt);
    });
    infoStep('actions_intro', 'click_yourself');

    selfStep('click_yourself', 'actions_panel_info');
    cy.get('#hud-actions .action[data-action="fouiller"]').should('exist');
    infoStep('actions_panel_info', 'close_card_for_tree');
    infoStep('close_card_for_tree', 'walk_to_tree');

    walkStep('walk_to_tree', 'observe_tree', TREE);

    tileStep('observe_tree', 'tree_info', TREE.x, TREE.y);
    cy.get('#ajax-data .building-status').should('contain', 'Récoltable');
    infoStep('tree_info', 'click_yourself_for_gather');

    /* The tree's card is still open: only a fresh click on oneself counts. */
    assertOnStep('click_yourself_for_gather');
    cy.wait(1000);
    currentStep().should('eq', 'click_yourself_for_gather');
    selfStep('click_yourself_for_gather', 'use_fouiller');

    resources().then((before) => {
      actionStep('use_fouiller', 'action_consumed', 'fouiller');
      resources().then((after) => {
        expect(after.pa, 'fouiller costs exactly 1 PA').to.eq(before.pa - 1);
      });
    });

    infoStep('action_consumed', 'open_inventory');

    uiStep('open_inventory', 'inventory_wood', '#show-inventory');
    cy.location('pathname').should('not.contain', 'inventory.php');
    /* Lit through the spotlight: Cypress counts the blocking overlay as a cover. */
    cy.get('.hud-panel .item-case[data-name="Bois"]', { timeout: MEDIUM }).should(($row) => {
      expect($row[0].getBoundingClientRect().height, 'the wood row is laid out in the panel').to.be.greaterThan(0);
    });
    infoStep('inventory_wood', 'close_inventory');

    uiStep('close_inventory', 'combat_intro', '.hud-panel-close');

    infoStep('combat_intro', 'enemy_spawned');
    /* The enemy spawn reloads the page; the step resumes on its own. */
    infoStep('enemy_spawned', 'walk_to_enemy');

    walkStep('walk_to_enemy', 'click_enemy', ENEMY);

    tileStep('click_enemy', 'attack_enemy', ENEMY.x, ENEMY.y);

    resources().then((before) => {
      actionStep('attack_enemy', 'attack_result', 'melee');
      resources().then((after) => {
        expect(after.pa, 'the attack costs exactly 1 PA').to.eq(before.pa - 1);
      });
    });

    /* The wound shows on the enemy's portrait (the step points at it). */
    cy.get('#ajax-data #red-filter', { timeout: MEDIUM }).should('exist');
    infoStep('attack_result', 'tutorial_complete');

    assertOnStep('tutorial_complete');
    humanClick('#tutorial-next');
    cy.get('#tutorial-complete-modal', { timeout: MEDIUM }).should('be.visible');
    humanClick('#tutorial-complete-continue');

    /* ============================================================
     * PHASE 3: COMPLETION
     * ============================================================ */
    cy.log('═══ PHASE 3: COMPLETION VERIFICATION ═══');

    cy.then(() => {
      cy.validateTutorialState(TEST_ACCOUNT.playerId, { shouldExist: true }).then((state) => {
        expect(state.completed, 'tutorial_progress.completed').to.eq(1);
        cy.task('queryDatabase', {
          query: 'SELECT COALESCE(SUM(xp_reward), 0) AS total FROM tutorial_steps WHERE version = ? AND is_active = 1',
          params: [state.tutorial_version || '1.0.0']
        }).then((rows) => {
          expect(Number(state.xp_earned), 'xp_earned equals the advertised total').to.eq(Number(rows[0].total));
        });
      });
    });

    cy.then(() => cy.task('queryDatabase', {
      query: 'SELECT c.plan FROM players p JOIN coords c ON p.coords_id = c.id WHERE p.id = ?',
      params: [TEST_ACCOUNT.playerId]
    })).then((rows) => {
      expect(rows, 'the account still exists').to.have.length(1);
      expect(rows[0]?.plan, 'the player leaves waiting_room').to.not.eq('waiting_room');
    });

    /* First completion grants the starter pack (the walking stick is the
     * item no other path gives). */
    cy.then(() => cy.task('queryDatabase', {
      query: `SELECT COALESCE(SUM(pi.n), 0) AS n FROM players_items pi JOIN items i ON i.id = pi.item_id
              WHERE pi.player_id = ? AND i.name = 'baton_marche'`,
      params: [TEST_ACCOUNT.playerId]
    })).then((rows) => {
      expect(Number(rows[0].n), 'first completion grants ONE walking stick, once').to.eq(1);
    });

    cy.then(() => cy.task('queryDatabase', {
      query: 'SELECT is_active FROM tutorial_players WHERE player_id = ? ORDER BY id DESC',
      params: [tutorialPlayerId]
    })).then((rows) => {
      expect(rows.length, 'tutorial_players row').to.be.greaterThan(0);
      expect(Number(rows[0].is_active), 'the tutorial character is deactivated').to.eq(0);
    });
  });
});
