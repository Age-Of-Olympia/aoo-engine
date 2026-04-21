/**
 * Tutorial — production-readiness end-to-end test.
 *
 * Structure: each tutorial step is driven by a helper whose name encodes the
 * expected user interaction (info Next, tile click, UI-element click, move,
 * action double-click). Every helper:
 *
 *   1. Asserts we are on `fromStep` (hard fail if the previous step didn't
 *      land where expected — catches auto-skips and out-of-order advances).
 *   2. Performs EXACTLY the clicks the step is designed to need.
 *   3. Asserts we advanced to `toStep` within a short window (hard fail on
 *      miss-clicks — no more silent 10-second Cypress retries).
 *
 * Race-specific movement sequences (depleteMovesByRace, etc.) encode the
 * expected click count for every path, so the test is deterministic for
 * every race. If a tile isn't clickable the failure message points at the
 * exact coordinate that was expected.
 *
 * CRITICAL: single `it()` block — Cypress resets session between blocks.
 */

describe('Tutorial System - Production Readiness Test', () => {
  const uniqueNames = ['Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon', 'Zeta', 'Eta', 'Theta'];
  const randomName = uniqueNames[Math.floor(Math.random() * uniqueNames.length)];
  const randomSuffix = Array.from({length: 6}, () =>
    String.fromCharCode(97 + Math.floor(Math.random() * 26))
  ).join('');
  const timestamp = Date.now();

  let raceData = { mvt: 4, pa: 2 }; /* populated in before() */
  const TEST_ACCOUNT = {
    name: `CypressTest${randomName}${randomSuffix}`,
    password: 'testpass123',
    email: `cypresstest${timestamp}@test.com`,
    race: Cypress.env('race') || 'nain',
    playerId: null
  };

  const getMaxMvt = () => raceData.mvt;
  const getMaxPa = () => raceData.pa;

  /* ============================================================
   * Per-race click choreography.
   * All paths start after first_move (player at (-1,0)) and end at (0,0)
   * (adjacent to the tree at (0,1)). See comments on each entry for why.
   * ============================================================ */

  /* deplete_movements: consumes exactly raceMax MVT. Alternating W/E bounce
   * keeps the player local so walk_to_tree afterwards is predictable. */
  const depleteMovesByRace = {
    nain: ['-2,0', '-1,0', '-2,0', '-1,0'],                     /* 4 moves → end (-1,0) */
    elfe: ['-2,0', '-1,0', '-2,0', '-1,0', '0,0'],              /* 5 moves → end (0,0) */
    hs:   ['-2,0', '-1,0', '-2,0', '-1,0', '-2,0', '-1,0'],     /* 6 moves → end (-1,0) */
  };

  /* walk_to_tree: move to a tile adjacent to the tree at (0,1). (0,0) works
   * for Nain/HS who ended deplete at (-1,0). Elfe is already at (0,0). */
  const walkToTreeByRace = {
    nain: ['0,0'],
    elfe: [],
    hs:   ['0,0'],
  };

  /* walk_to_enemy: after fouiller the player is at (0,0). Target enemy at
   * (2,1); the direct path via (1,0) is blocked by Gaïa (NPC), so we detour
   * through y=-1 to reach (2,0) which is adjacent to (2,1). */
  const walkToEnemy = ['0,-1', '1,-1', '2,-1', '2,0'];

  /* ============================================================
   * Step-helpers (the "click contract" for each step type).
   * ============================================================ */

  const SHORT = 3000;   /* step transitions should happen within 3s of the action */
  const MEDIUM = 15000; /* accommodate entering a step (server + render) */

  /** Assert the tutorial is currently on the given step.
   * The 500ms settle window lets the step's JS-side setup (validation observers,
   * highlight render, tooltip placement) complete before the test acts on it.
   * Without it, clicks can happen before a MutationObserver is installed and
   * the resulting hide/open isn't detected — leading to false timeout. */
  const assertOnStep = (stepId) => {
    cy.window({ timeout: MEDIUM }).should((win) => {
      expect(win.tutorialUI?.currentStep, `expected currentStep=${stepId}`).to.eq(stepId);
    });
    cy.wait(500);
  };

  /** Assert the tutorial tooltip contains given text (tests placeholder substitutions). */
  const assertTooltipContains = (text) => {
    cy.get('.tutorial-tooltip', { timeout: 5000 }).should('be.visible').should('contain', text);
  };

  /** Info step: click #tutorial-next, advance to toStep. 1 click. */
  const advanceInfoStep = (fromStep, toStep) => {
    assertOnStep(fromStep);
    cy.get('#tutorial-next', { timeout: MEDIUM }).should('be.visible').click();
    cy.window({ timeout: SHORT }).should((win) => {
      expect(win.tutorialUI?.currentStep, `${fromStep} Next must advance to ${toStep}`).to.eq(toStep);
    });
  };

  /** Click a map tile by `data-coords`. 1 click. */
  const advanceTileClickStep = (fromStep, toStep, coords) => {
    assertOnStep(fromStep);
    cy.get(`.case[data-coords="${coords}"]`, { timeout: MEDIUM }).should('be.visible').click();
    cy.window({ timeout: SHORT }).should((win) => {
      expect(win.tutorialUI?.currentStep, `click on ${coords} must advance ${fromStep} → ${toStep}`).to.eq(toStep);
    });
  };

  /** Click an arbitrary UI element. 1 click. */
  const advanceUiClickStep = (fromStep, toStep, selector) => {
    assertOnStep(fromStep);
    cy.get(selector, { timeout: MEDIUM }).should('be.visible').click();
    cy.window({ timeout: SHORT }).should((win) => {
      expect(win.tutorialUI?.currentStep, `click on ${selector} must advance ${fromStep} → ${toStep}`).to.eq(toStep);
    });
  };

  /** Move one tile: click destination, click go indicator. 2 clicks per move.
   * .case/.go/#go-rect may be SVG elements (no native .click()), so we use
   * Cypress's force:true click to dispatch the event past any overlay. */
  const moveTo = (coords) => {
    cy.get(`.case[data-coords="${coords}"]`, { timeout: MEDIUM })
      .should('exist')
      .click({ force: true });
    cy.wait(500);
    cy.get('#go-rect, #go-img', { timeout: 5000 }).filter(':visible').first().click({ force: true });
    cy.wait(3500); /* server-side move + page rerender */
  };

  /** Movement step with a known destination: one move, then step advances. */
  const advanceOneMoveStep = (fromStep, toStep, destCoords) => {
    assertOnStep(fromStep);
    moveTo(destCoords);
    cy.window({ timeout: SHORT }).should((win) => {
      expect(win.tutorialUI?.currentStep, `move to ${destCoords} must advance ${fromStep} → ${toStep}`).to.eq(toStep);
    });
  };

  /** Movement step with a known sequence of destinations. */
  const advanceMoveSequence = (fromStep, toStep, destSeq) => {
    assertOnStep(fromStep);
    destSeq.forEach((coord) => moveTo(coord));
    cy.window({ timeout: SHORT }).should((win) => {
      expect(win.tutorialUI?.currentStep, `${destSeq.length}-move sequence must advance ${fromStep} → ${toStep}`).to.eq(toStep);
    });
  };

  /** Action step: expand button (click) + execute button (click). 2 clicks. */
  const advanceActionStep = (fromStep, toStep, actionName) => {
    assertOnStep(fromStep);
    cy.get(`.action[data-action="${actionName}"]`, { timeout: MEDIUM }).should('be.visible').then(($btn) => {
      $btn[0].click(); /* expand */
    });
    cy.wait(1000);
    cy.get(`.action[data-action="${actionName}"]`).then(($btn) => {
      $btn[0].click(); /* execute */
    });
    cy.wait(3000); /* action processing */
    cy.window({ timeout: SHORT }).should((win) => {
      expect(win.tutorialUI?.currentStep, `action ${actionName} must advance ${fromStep} → ${toStep}`).to.eq(toStep);
    });
  };


  /* =============================================================
   * before hooks
   * ============================================================= */

  before(() => {
    cy.request(`/api/races/get.php?name=${TEST_ACCOUNT.race}`).then((response) => {
      expect(response.status).to.eq(200);
      expect(response.body.success).to.be.true;
      raceData = response.body.race;
      cy.log(`🎭 Race: ${raceData.name}, Max MVT: ${raceData.mvt}, Max PA: ${raceData.pa}`);
    });
  });

  before(() => {
    cy.clearCookies();
    cy.clearLocalStorage();
    cy.window().then((win) => win.sessionStorage.clear());
  });

  it('Complete production validation: Fresh player through entire tutorial', () => {
    let tutorialSessionId;
    let tutorialPlayerId;

    /* =============================================================
     * PHASE 0: REGISTRATION
     * ============================================================= */
    cy.log(`═══ PHASE 0: REGISTER ${TEST_ACCOUNT.name} (race=${TEST_ACCOUNT.race}) ═══`);
    cy.register(TEST_ACCOUNT.name, TEST_ACCOUNT.race, TEST_ACCOUNT.password, TEST_ACCOUNT.email);
    cy.task('queryDatabase', {
      query: 'SELECT id FROM players WHERE name = ? ORDER BY id DESC LIMIT 1',
      params: [TEST_ACCOUNT.name]
    }).then((rows) => {
      TEST_ACCOUNT.playerId = rows[0].id;
      cy.log(`✓ Registered with player_id=${TEST_ACCOUNT.playerId}`);
    });

    /* =============================================================
     * PHASE 1: LOGIN, OVERLAY, TUTORIAL SESSION
     * ============================================================= */
    cy.log('═══ PHASE 1: LOGIN + TUTORIAL AUTO-START ═══');
    cy.login(TEST_ACCOUNT.name, TEST_ACCOUNT.password);
    cy.wait(2000); /* auto-start window */

    cy.get('#tutorial-overlay', { timeout: 10000 }).should('exist');

    /* Map tiles rendered (detection for "all-gray, no walls" bug).
     * The tree wall must be drawn at (0,1) before the test tries to click it. */
    cy.get('[data-table="walls"][data-coords="0,1"], image[data-table="walls"][data-coords="0,1"]', { timeout: 5000 })
      .should('exist');

    cy.then(() => {
      cy.validateTutorialState(TEST_ACCOUNT.playerId, {
        shouldExist: true,
        mode: 'first_time',
        completed: 0
      }).then((state) => {
        tutorialSessionId = state.tutorial_session_id;
        tutorialPlayerId = state.tutorial_player_id;
        cy.log(`✓ session=${tutorialSessionId}, tutorial_player_id=${tutorialPlayerId}`);
        cy.validatePlayerCoords(tutorialPlayerId, { plan: 'tut_*', x: 0, y: 0 });

        /* "Map always ready" contract: the session's tut_* plan must have
         * been fully copied from the tutorial template — 121 coords (11×11,
         * from −5 to +5 on each axis), the perimeter wall set (40) plus the
         * gatherable tree (1), and Gaïa. If any count is off, something in
         * TutorialMapInstance copying broke.
         *
         * NOTE: map_tiles is NOT asserted because the current test-DB seed
         * (db/init_test_from_dump.sh) does not insert grass tiles for the
         * tutorial template. If the template ever gets a grass carpet
         * (7×7 = 49 tiles on the interior), add an assertion here. */
        cy.task('queryDatabase', {
          query: `SELECT
                    (SELECT COUNT(*) FROM coords c WHERE c.plan LIKE 'tut_%' AND c.id IN (SELECT coords_id FROM players WHERE id = ?)) AS player_on_tut,
                    (SELECT COUNT(*) FROM coords c WHERE c.plan=(SELECT c2.plan FROM coords c2 JOIN players p ON p.coords_id=c2.id WHERE p.id=?)) AS plan_coords,
                    (SELECT COUNT(*) FROM map_walls mw JOIN coords c ON c.id=mw.coords_id WHERE c.plan=(SELECT c2.plan FROM coords c2 JOIN players p ON p.coords_id=c2.id WHERE p.id=?)) AS plan_walls,
                    (SELECT COUNT(*) FROM players p JOIN coords c ON c.id=p.coords_id WHERE p.id < 0 AND p.name='Gaïa' AND c.plan=(SELECT c2.plan FROM coords c2 JOIN players p2 ON p2.coords_id=c2.id WHERE p2.id=?)) AS plan_gaia`,
          params: [tutorialPlayerId, tutorialPlayerId, tutorialPlayerId, tutorialPlayerId]
        }).then((rows) => {
          const r = rows[0];
          cy.log(`Map state: coords=${r.plan_coords}, walls=${r.plan_walls}, gaia=${r.plan_gaia}`);
          expect(Number(r.plan_coords), 'new session plan must have 121 coords (11x11)').to.eq(121);
          expect(Number(r.plan_walls), 'new session plan must have 41 walls (40 perimeter + tree)').to.eq(41);
          expect(Number(r.plan_gaia), 'Gaïa NPC must be present on the session plan').to.eq(1);
        });
      });
    });

    cy.window().then((win) => {
      expect(win.tutorialUI).to.exist;
      expect(win.sessionStorage.getItem('tutorial_active')).to.equal('true');
    });

    /* =============================================================
     * PHASE 2: TUTORIAL STEP CHOREOGRAPHY
     * Each call is (fromStep, toStep[, arg]) with a known click count.
     * Comments show: <step#> <step_id> <click-count> <intent>
     * ============================================================= */
    cy.log('═══ PHASE 2: STEP CHOREOGRAPHY ═══');

    /*  1 welcome            → info Next (1 click) */
    advanceInfoStep('welcome', 'your_character');
    /*  2 your_character     → info Next (1 click) */
    advanceInfoStep('your_character', 'meet_gaia');
    /*  3 meet_gaia          → click Gaïa at (1,0) (1 click) */
    advanceTileClickStep('meet_gaia', 'close_card', '1,0');
    /*  4 close_card         → click close-X (1 click) */
    advanceUiClickStep('close_card', 'movement_intro', 'button.close-card');
    /*  5 movement_intro     → info Next (1 click) */
    advanceInfoStep('movement_intro', 'first_move');

    /*  6 first_move  any_movement, unlimited_mvt=1. Move to (-1,0). 2 clicks. */
    assertOnStep('first_move');
    moveTo('-1,0');
    assertOnStep('movement_limit_warning');

    /*  7 movement_limit_warning  info. Assert {max_mvt} substitution + Next (1 click) */
    assertOnStep('movement_limit_warning');
    assertTooltipContains(`${getMaxMvt()} mouvements`);
    cy.get('.tutorial-tooltip').should('not.contain', '{max_mvt}');
    /* MVT must NOT have decremented (first_move is free) — verify via DB.
     * cy.then defers the tutorialPlayerId read to execution time. */
    cy.then(() => {
      cy.getPlayerResources(tutorialPlayerId).then((r) => {
        expect(r.mvt, 'first_move unlimited_mvt=1 → MVT must equal race max').to.eq(getMaxMvt());
      });
    });
    advanceInfoStep('movement_limit_warning', 'show_characteristics');

    /*  8 show_characteristics  click #show-caracs (1 click).
     * Linger for ~2s so a human watching the video can actually read the panel
     * before the test moves on. Purely cosmetic — the DB state is already correct. */
    advanceUiClickStep('show_characteristics', 'deplete_movements', '#show-caracs');
    cy.get('#load-caracs', { timeout: 3000 }).should('be.visible');
    cy.wait(2000);

    /*  9 deplete_movements  race-specific move sequence (2 × raceMax clicks). */
    cy.get('#show-caracs').click(); /* close caracs panel so tiles are visible */
    cy.wait(500);
    assertTooltipContains(`${getMaxMvt()} mouvements`);
    const depleteSeq = depleteMovesByRace[TEST_ACCOUNT.race];
    expect(depleteSeq, `deplete sequence must be defined for race=${TEST_ACCOUNT.race}`).to.have.length(getMaxMvt());
    advanceMoveSequence('deplete_movements', 'movements_depleted_info', depleteSeq);
    /* MVT was consumed: now 0, then restored for the next step */
    cy.get('#mvt-counter').should('contain', '0');

    /* 10 movements_depleted_info  info Next (1 click) */
    advanceInfoStep('movements_depleted_info', 'actions_intro');

    /* 11 actions_intro  info Next (1 click) — resources auto-restored before render */
    assertOnStep('actions_intro');
    cy.then(() => {
      cy.getPlayerResources(tutorialPlayerId).then((r) => {
        expect(r.pa, `after auto_restore PA must equal race max (${getMaxPa()}) — not inflated`).to.eq(getMaxPa());
        expect(r.mvt, `after auto_restore MVT must equal race max (${getMaxMvt()})`).to.eq(getMaxMvt());
      });
    });
    advanceInfoStep('actions_intro', 'click_yourself');

    /* 12 click_yourself  click own avatar tile (1 click). Player position is
     * deterministic per race after deplete_movements, but easier to query DB than
     * encode per-race positions here. Avatar element x/y attrs are SVG pixels,
     * not game coords, so we can't derive tile coords from the DOM directly. */
    assertOnStep('click_yourself');
    cy.then(() => {
      cy.task('queryDatabase', {
        query: 'SELECT c.x, c.y FROM players p JOIN coords c ON c.id = p.coords_id WHERE p.id = ?',
        params: [tutorialPlayerId]
      }).then((rows) => {
        const coords = `${rows[0].x},${rows[0].y}`;
        cy.log(`👤 Clicking own tile at (${coords})`);
        cy.get(`.case[data-coords="${coords}"]`).should('be.visible').click();
      });
    });
    cy.window({ timeout: SHORT }).should((win) => {
      expect(win.tutorialUI?.currentStep, 'click own tile must advance to actions_panel_info').to.eq('actions_panel_info');
    });

    /* 13 actions_panel_info  info Next (1 click) */
    advanceInfoStep('actions_panel_info', 'close_card_for_tree');

    /* 14 close_card_for_tree  click close-X (1 click) */
    advanceUiClickStep('close_card_for_tree', 'walk_to_tree', 'button.close-card');

    /* 15 walk_to_tree  adjacent_to_position(0,1). Race-specific sequence. */
    const walkTreeSeq = walkToTreeByRace[TEST_ACCOUNT.race];
    if (walkTreeSeq.length === 0) {
      /* Elfe is already at (0,0) after the deplete sequence — step validates on entry */
      assertOnStep('walk_to_tree');
      cy.window({ timeout: SHORT }).should((win) => {
        expect(win.tutorialUI?.currentStep, 'already-adjacent entry must auto-advance walk_to_tree').to.not.eq('walk_to_tree');
      });
    } else {
      advanceMoveSequence('walk_to_tree', 'observe_tree', walkTreeSeq);
    }

    /* Verify we actually landed adjacent to the tree (issue 2 guard) */
    cy.then(() => {
      cy.task('queryDatabase', {
        query: 'SELECT c.x, c.y FROM players p JOIN coords c ON c.id = p.coords_id WHERE p.id = ?',
        params: [tutorialPlayerId]
      }).then((rows) => {
        const { x, y } = rows[0];
        const dist = Math.abs(x - 0) + Math.abs(y - 1);
        expect(dist, `after walk_to_tree player must be adjacent to tree (at (${x},${y}), dist=${dist})`).to.eq(1);
      });
    });

    /* 16 observe_tree  click tree tile (1 click). allow_manual_advance=0 — NO Next bypass. */
    advanceTileClickStep('observe_tree', 'tree_info', '0,1');

    /* 17 tree_info  info Next (1 click). Tooltip targets .resource-status — may
     * render off-screen, so use the tooltipless API call instead. */
    assertOnStep('tree_info');
    cy.wait(1000); /* let show_delay settle */
    cy.get('#tutorial-next').click({ force: true });
    cy.window({ timeout: SHORT }).should((win) => {
      expect(win.tutorialUI?.currentStep, 'tree_info Next must advance to use_fouiller').to.eq('use_fouiller');
    });

    /* 18 use_fouiller  2 clicks on .action[data-action="fouiller"] (expand + execute).
     * PA must decrement by exactly 1. Tree panel must already be open from
     * observe_tree's click — fouiller button lives inside #ui-card. */
    assertOnStep('use_fouiller');
    cy.then(() => {
      cy.getPlayerResources(tutorialPlayerId).then((before) => {
        advanceActionStep('use_fouiller', 'action_consumed', 'fouiller');
        cy.getPlayerResources(tutorialPlayerId).then((after) => {
          expect(after.pa, 'fouiller must consume exactly 1 PA').to.eq(before.pa - 1);
        });
      });
    });

    /* 20 action_consumed  info Next (1 click) */
    advanceInfoStep('action_consumed', 'open_inventory');

    /* 21 open_inventory  click #show-inventory (1 click) */
    advanceUiClickStep('open_inventory', 'inventory_wood', '#show-inventory');

    /* 22 inventory_wood  info Next (1 click). Assert wood is visually present. */
    assertOnStep('inventory_wood');
    cy.get('.item-case[data-name="Bois"]').should('be.visible');
    advanceInfoStep('inventory_wood', 'close_inventory');

    /* 23 close_inventory  click #back (1 click) */
    advanceUiClickStep('close_inventory', 'combat_intro', '#back');

    /* 24 combat_intro  info Next (1 click) */
    advanceInfoStep('combat_intro', 'enemy_spawned');

    /* 25 enemy_spawned  info Next (1 click) — user confirmed deterministic.
     * Title is "Votre adversaire", but the advance control is the standard tutorial Next. */
    advanceInfoStep('enemy_spawned', 'walk_to_enemy');

    /* 26 walk_to_enemy  adjacent_to_position(2,1). Detour via y=-1 because
     * Gaïa blocks (1,0). Ends at (2,0), adjacent to the enemy at (2,1). */
    advanceMoveSequence('walk_to_enemy', 'click_enemy', walkToEnemy);

    /* Verify adjacency to enemy */
    cy.then(() => {
      cy.task('queryDatabase', {
        query: 'SELECT c.x, c.y FROM players p JOIN coords c ON c.id = p.coords_id WHERE p.id = ?',
        params: [tutorialPlayerId]
      }).then((rows) => {
        const { x, y } = rows[0];
        const dist = Math.abs(x - 2) + Math.abs(y - 1);
        expect(dist, `after walk_to_enemy player must be adjacent to enemy (at (${x},${y}), dist=${dist})`).to.eq(1);
      });
    });

    /* 27 click_enemy  click enemy tile at (2,1) (1 click) */
    advanceTileClickStep('click_enemy', 'attack_enemy', '2,1');

    /* 28 attack_enemy  2 clicks on .action[data-action="attaquer"] (expand + execute).
     * PA must decrement by exactly 1. */
    assertOnStep('attack_enemy');
    cy.then(() => {
      cy.getPlayerResources(tutorialPlayerId).then((before) => {
        advanceActionStep('attack_enemy', 'attack_result', 'attaquer');
        cy.getPlayerResources(tutorialPlayerId).then((after) => {
          expect(after.pa, 'attack must consume exactly 1 PA').to.eq(before.pa - 1);
        });
      });
    });

    /* 29 attack_result  info Next (1 click) */
    advanceInfoStep('attack_result', 'tutorial_complete');

    /* 30 tutorial_complete  Next (1 click) triggers completion modal; modal
     * "Commencer l'aventure!" (1 click) fires complete.php and redirects. */
    assertOnStep('tutorial_complete');
    cy.get('#tutorial-next').should('be.visible').click();
    cy.get('#tutorial-complete-modal', { timeout: 5000 }).should('be.visible');
    cy.get('#tutorial-complete-continue').click();
    cy.wait(3000); /* let the API call + redirect complete */

    /* =============================================================
     * PHASE 3: COMPLETION GUARANTEES
     * ============================================================= */
    cy.log('═══ PHASE 3: COMPLETION VERIFICATION ═══');

    /* completed=1 + xp_earned == SUM(xp_reward) of active steps */
    cy.then(() => {
      cy.validateTutorialState(TEST_ACCOUNT.playerId, { shouldExist: true }).then((state) => {
        cy.log(`state: completed=${state.completed}, xp=${state.xp_earned}, step=${state.current_step}`);
        expect(state.completed, 'tutorial_progress.completed must be 1').to.eq(1);
        cy.task('queryDatabase', {
          query: `SELECT COALESCE(SUM(xp_reward), 0) AS total FROM tutorial_steps WHERE version = ? AND is_active = 1`,
          params: [state.tutorial_version || '1.0.0']
        }).then((rows) => {
          const advertised = Number(rows[0].total);
          expect(Number(state.xp_earned), `xp_earned must equal advertised total (${advertised})`).to.eq(advertised);
        });
      });
    });

    /* Real player must leave waiting_room (→ faction's respawnPlan) */
    cy.then(() => {
      cy.task('queryDatabase', {
        query: 'SELECT c.plan FROM players p JOIN coords c ON p.coords_id = c.id WHERE p.id = ?',
        params: [TEST_ACCOUNT.playerId]
      }).then((rows) => {
        expect(rows[0]?.plan, 'player must leave waiting_room after completion').to.not.eq('waiting_room');
      });
    });

    /* Tutorial player must be deactivated (isolation guardrail) */
    cy.then(() => {
      cy.task('queryDatabase', {
        query: 'SELECT is_active FROM tutorial_players WHERE player_id = ?',
        params: [tutorialPlayerId]
      }).then((rows) => {
        expect(rows.length, `tutorial_players row must exist for player_id=${tutorialPlayerId}`).to.be.greaterThan(0);
        expect(Number(rows[0].is_active), 'tutorial player must be deactivated after completion').to.eq(0);
      });
    });

    cy.log('🏁 Tutorial test passed — all step contracts honored');
  });
});
