# `tutorial-refactoring` → `staging` — Team Summary

**Branch**: `tutorial-refactoring`
**Base**: `staging`
**Period covered**: 2025-11-13 → 2026-04-21
**Commits**: 350
**Diffstat**: 299 files changed, +68 772 / −5 837 lines
**Authored for**: team-wide handover / review kickoff

> This document summarises *why* the branch exists and *what it changes* at a
> level useful for planning review, QA, and deploy. Detailed per-phase docs
> live under `docs/` (see index at the end).

---

## 1. Executive summary

Three overlapping initiatives landed on this branch:

1. **Tutorial system rebuild (the headline)** — from a 5-step hardcoded JS
   prototype to a data-driven, admin-configurable, race-adaptive, E2E-tested
   onboarding flow with a normalised DB schema, dedicated service layer,
   isolated tutorial map, and feature-flag rollout.
2. **`Classes\Player` dismantling — Phases 0–4.6** — a TDD-driven,
   characterisation-test-gated teardown of the 2.5k-LOC legacy god class.
   Introduces `PlayerFactory` + the `PlayerEntity` / `RealPlayer` /
   `TutorialPlayer` / `NonPlayerCharacter` Doctrine hierarchy (STI) and
   starts routing read paths + tutorial subsystem through it.
3. **Testing + CI + DevEx baseline** — Cypress E2E brought online as a hard
   CI gate, PHPUnit suite expanded by ~30 new test files (~6k LOC of tests),
   `db/updates/` deprecated in favour of Doctrine migrations, Dockerfile
   upgraded to PHP 8.4, test DB lifecycle scripted.

Commit breakdown: `fix` 130 · `feat` 84 · `refactor` 37 · `test` 30 ·
`docs` 23 · `chore` 17 · `ci` 14 · others 15.

---

## 2. Headline numbers

| Area | Before | After |
|---|---|---|
| Tutorial steps | 5 hardcoded in JS | DB-driven, 40+ configurable steps, normalised across 9 tables |
| Tutorial automated tests | 0 | 1 Cypress production spec (required CI gate) + ~15 PHPUnit tutorial test files |
| `Classes\Player` characterisation tests | 0 | 12 PHPUnit files pinning hydration, options, actions, caracs, coords, display-id, factory equivalence |
| Doctrine migrations added | — | 8 new migrations (tutorial schema, player STI, bonus_points, FK guardrails, etc.) |
| Modern entities | `Player` only (legacy mixed) | `PlayerEntity` + STI subclasses: `RealPlayer`, `TutorialPlayer`, `NonPlayerCharacter` |
| Cypress on tutorial-refactoring | n/a | auto-triggered, `allow_failure: false`, ~6 min runtime |
| `db/updates/*.sql` | active mechanism | deprecated (README in place); all further schema goes through Doctrine |

---

## 3. Initiative 1 — Tutorial system rebuild

### 3.1 New backend architecture (`src/Tutorial/`)

A full service layer, replacing the legacy `Classes/Dialog.php` + hardcoded
JS:

- **Orchestration**: `TutorialManager`, `TutorialSessionManager`,
  `TutorialProgressManager`
- **Data access**: `TutorialStepRepository` (1 query with JOINs across the
  9 tables, N+1-eliminated), `TutorialCatalogService` (version management)
- **Lifecycle**: `TutorialPlayerFactory`, `TutorialPlayerCleanup`,
  `TutorialEnemyCleanup`, `TutorialResourceManager`
- **Step model**: `AbstractStep` + `ActionStep`, `MovementStep`,
  `UIInteractionStep`, `DialogStep`, `GenericStep`
- **Support**: `TutorialContext`, `TutorialContextKeys`,
  `TutorialPlaceholderService`, `TutorialFeatureFlag` (TTL-cached),
  `TutorialOptions`, `TutorialTemplates`, `TutorialConstants`,
  `TutorialMapInstance`
- **Typed exceptions**: `TutorialException`,
  `TutorialValidationException`, `TutorialSessionException`,
  `TutorialStepException`, `TutorialMapInstanceException`

### 3.2 Normalised DB schema

Introduced (migration
`Version20251127000000_CreateCompleteTutorialSystem`):

- `tutorial_steps` (core), plus 1:1 tables `tutorial_step_ui`,
  `tutorial_step_validation`, `tutorial_step_prerequisites`,
  `tutorial_step_features`
- 1:N tables `tutorial_step_highlights`, `tutorial_step_interactions`,
  `tutorial_step_context_changes`, `tutorial_step_next_preparation`
- Session/state: `tutorial_progress`, `tutorial_players`,
  `tutorial_enemies`, `tutorial_dialogs`, `tutorial_catalog`,
  `tutorial_settings`

Additional migrations: `AddCraftingTutorial` (2026-01-02),
`TutorialMapWallsAndGaiaAvatar` (2026-04-20),
`TutorialStepCoherenceFixes` (2026-04-20).

### 3.3 Frontend (`js/tutorial/`, `css/tutorial/`)

Replaced single-file `js/tutorial.js` with a component stack:

- `TutorialUI.js` (controller, 2k LOC),
  `TutorialTooltip.js` (parchment-themed, draggable, 8 positions
  including `center-top` / `center-bottom`),
  `TutorialHighlighter.js` (spotlight overlay, pulse),
  `TutorialStepNavigator.js`,
  `TutorialPositionManager.js` (shared positioning),
  `TutorialGameIntegration.js`,
  `TutorialInit.js`
- New `css/tutorial/tutorial.css` (1.7k LOC; medieval/parchment styling,
  full mobile responsive layout, ultra-compact mobile controls)

### 3.4 API surface (`api/tutorial/`)

11 endpoints: `start`, `advance`, `resume`, `skip`, `cancel`,
`complete`, `check_session`, `check_tutorial_character`, `exit_tutorial_mode`,
`get-step`, `jump-to-step`. All use the service layer; admin-only endpoints
require admin authorisation.

### 3.5 Admin panel (`admin/`)

- `admin/tutorial.php` — main dashboard (1.1k LOC)
- `admin/tutorial-step-editor.php` — full step CRUD (1.5k LOC, all 33 DB
  fields covered; previous impl only covered 19/33)
- `admin/tutorial-settings.php` — feature flag UI
- `admin/tutorial-catalog.php` — version/catalogue management (multiple
  tutorials)
- `admin/tutorial-launcher.php` — admin session spawner
- `admin/tutorial-sessions-api.php` — diagnostic / forced-cleanup API
- `admin/helpers.php` (shared), `admin/css/tutorial-admin.css`
- Bulk export/import endpoints for step configurations

### 3.6 Gameplay changes shipped with the rebuild

- **Isolated tutorial map** — `plan='tutorial'`, `player_visibility:false`
  in plan JSON, three-layer isolation (map render, movement, observe)
  documented in CLAUDE.md
- **Race-adaptive movement** — `{max_mvt}` placeholder,
  `mvt_required: -1` sentinel for race max; nain/elfe/hs = 4/5/6 MVT;
  new `/api/races/get.php`
- **Movement consumption control** — infinite-by-default tutorial, opt-in
  per step; fixes the long-standing "unlimited movement bug"
- **New Ame race** — used for tutorial enemies/mannequins;
  `datas_standalone/public/races/ame.json`
- **Invisible-mode new players** — new accounts spawn invisible until the
  tutorial is completed, skipped, or cancelled (new `invisibleMode` option)
- **Auto-start** from the NewTurn page for eligible players; replay path
  via URL parameter + feature flag
- **Rewards** — XP *and* PI on completion, configurable skip vs. complete
  amounts, replay-abuse guard
- **Resume flow** — `sessionStorage.tutorial_active`, dedicated resume
  modal (z-index fixed above tutorial overlay), combat-step resume opens
  the enemy card

### 3.7 Validation types supported

`any_movement`, `movements_depleted`, `specific_count`, `position`,
`adjacent_to_position`, `ui_panel_opened`, `ui_button_clicked`,
`ui_interaction`, `ui_element_visible`, `action_used`, `action_available`,
combat validations.

---

## 4. Initiative 2 — `Classes\Player` dismantling

Tracked in `docs/player-dismantling-roadmap.md`, with per-phase audits
under `docs/phase-*.md`. Methodology: **characterisation tests gate every
phase** (required pre-flight MR before any extraction).

### 4.1 Phase 0 — `PlayerFactory` (delivered)

`src/Factory/PlayerFactory.php` — static API:
`legacy()`, `active()`, `activeId()`, `entity()`, `activeEntity()`,
`legacyByName()`. Deliberately **no wrapper object** — legacy and entity
paths stay parallel.

### 4.2 Phase 1 — mechanical migration (delivered)

Root controllers + `src/View/**` migrated to `PlayerFactory::legacy()` /
`::active()`. Equivalence smoke test
(`PlayerFactoryLegacyEquivalenceTest`) pinned the contract first.

### 4.3 Phase 2 — generic key-value extractions (delivered)

- `PlayerOptionsService` — extracted `have/add/end/get` for
  `players_options` (+ characterisation test)
- `PlayerActionsService` — same pattern for `players_actions`
- Dead branches folded out of the legacy god-method; 4 `optioncmd` / 1
  `actioncmd` callers routed to shims

### 4.4 Phase 3 — read-path migration to `PlayerEntity` (delivered)

- Schema audit (`docs/phase-3-schema-audit.md`) — 4 blocking mismatches
  found **before** any migration shipped, fixed in MR !383 (entity
  `name:` attrs on `email_bonus`, `tutorial_session_id`,
  `real_player_id_ref`; `bonus_points` column added)
- 3.3: `reset-password` lookup migrated
- 3.4a: `PlayerEntity::getCoords()` / `getOptions()` domain methods added
- 3.4b: `PlayerCaracsService` extracted, `BourrinsView` migrated

### 4.5 Phase 4 — tutorial subsystem cut-over (delivered)

Audit: `docs/phase-4-tutorial-cutover-audit.md`. Shipped in sub-phases
to avoid a dual-write window:

- **4.1** — entity reward-transfer signature reconciled
- **4.2** — `completeTutorial` reward transfer routed through entity
- **4.3b/c/d** — `cancel.php`, `ScreenshotService`, `infos.php` migrated
- **4.3** — `TutorialManager` migrated to `TutorialPlayerEntity`
- **4.4** — `App\Tutorial\TutorialPlayer` service class retired; tutorial
  player loading routed through `PlayerFactory`
- **4.5** — dual `real↔tutorial` link columns collapsed into one
  (audit in `docs/phase-4-5-link-column-collapse-audit.md`)
- **4.6** — FK guardrail restored on `players.real_player_id_ref`

### 4.6 Entity hierarchy + STI

- `src/Entity/PlayerEntity.php` (base)
- `src/Entity/RealPlayer.php`, `TutorialPlayer.php`,
  `NonPlayerCharacter.php` (`Entity` suffix dropped; was `NPCEntity`)
- `player_type` discriminator column added (migration)
- **Display IDs**: `mat.X` format now used across all player views
  (`Affiche le display_id court...`)
- **Entity ID ranges**: real/tutorial/NPC live in distinct numeric ranges
  (`db/add_player_type_and_display_id.sql` +
  `docs/player-type-inheritance-migration.md`)

---

## 5. Initiative 3 — testing, CI, DevEx

### 5.1 Cypress E2E (new)

- `cypress/e2e/tutorial-production-ready.cy.js` — full start-to-finish
  tutorial run (the CI gate)
- 7 other specs covering resume, workflows, test-DB paths, registration
- `cypress/support/commands.js` custom commands, `cypress.config.js`,
  `package.json` / `package-lock.json`
- Works headlessly via `xvfb-run` inside the devcontainer and in GitLab
- Race-parameterised runs (`--env race=elfe`)

### 5.2 CI pipeline changes (`.gitlab-ci.yml`)

- New `cypress_tutorial_job` — MariaDB service + `php -S`, doctrine
  migrations applied, race fixtures probed
- Auto-triggered on `tutorial-refactoring`, `allow_failure: false`;
  still `when: manual` on `staging` / `saison-3` / `main` (each
  long-lived branch needs its own validation flip)
- Docker layer cache enabled, image pinned to digest, PHP 8.4
- `doctrine-migrations migrate --no-all-or-nothing`; pre-snapshot
  migration version seed

### 5.3 PHPUnit expansion

~30 new test files under `tests/Tutorial/`, `tests/Player/`,
`tests/Various/`. Highlights:

- `TutorialIsolationInvariantsTest` — pins STI isolation against
  regression
- `TutorialManagerCompletionFlowTest`,
  `TutorialPlayerCleanupIntegrationTest`,
  `TutorialPlayerRewardTransferTest`,
  `TutorialLinkColumnCollapseTest`,
  `TutorialRealPlayerFkTest` — integration tests for Phase 4 moves
- `PlayerFactoryLegacyEquivalenceTest` — Phase 1 pre-flight contract
- `PlayerEntityHydrationTest`, `PlayerEntityDomainMethodsTest`,
  `PlayerEntityDisplayIdTest` — entity hydration & contract
- `PlayerOptionsCharacterizationTest`,
  `PlayerActionsCharacterizationTest`,
  `PlayerCaracsServiceCharacterizationTest` — Phase 2 / 3.4b gates
- `TutorialP0BackfillTest` — backfills coverage for already-merged P0
  fixes

### 5.4 DB / migrations housekeeping

- `db/updates/` **deprecated** (see `db/updates/README.md`); all future
  schema changes land via Doctrine migrations.
- `db/init_noupdates.sql` reconciled with every in-flight migration
  (including the 4 Phase-4 migrations folded into init) — unblocks fresh
  devcontainer setups.
- `db/init_test_complete.sql` + `db/init_test_from_dump.sh` — test DB
  scripting.
- UTF-8mb4 enforced on migration connections.

### 5.5 DevEx

- Devcontainer installs `glab` CLI, updated postCreate
- `scripts/testing/` — `reset_test_database.sh`,
  `create_test_database.sh`, `switch_to_{prod,test}_db.sh`
- `scripts/tutorial/cleanup_orphans.php` — cron-friendly cleanup of
  abandoned tutorial sessions (+ `CleanupOrphansScriptTest`)
- `scripts/populate_tutorial_production.php` — production-ready seed
- `CLAUDE.md` substantially expanded with tutorial patterns, UTF-8
  pitfalls, minification `//` comment gotcha, port config clarification,
  cache-busting rule, Apache log-read warning

---

## 6. Security + correctness fixes of note

- Admin authorisation enforced on tutorial launcher + sessions API
- CSRF protection service (`CsrfProtectionService`) + admin pages
  migrated (local_maps, screenshots, world_map)
- Tutorial skip/complete XP guarded against replay abuse
- UUID format validation on session IDs
- Null-pointer validation post-hydration
- Sensitive debug info stripped from error responses
- SQL injection fix in one tutorial migration (parameter binding)
- Division-by-zero guards in combat display + `observe.php` PV render
- Foreign-key cleanup order corrected (enemies → players → coords)
- `players_bonus` added to enemy cleanup FK list

---

## 7. Risks / things to review before merge

1. **Migrations apply in order and are non-transactional where required**
   (`CreateCompleteTutorialSystem` is explicitly marked). Rehearse on a
   staging DB snapshot.
2. **`db/init_noupdates.sql` divergence** — the file was heavily rewritten
   (±5k / −5k). If any parallel branch also edits it, expect conflicts.
3. **STI discriminator column + new FKs on `players`** — deploy ordering
   matters: the migration chain must run *before* any app code that uses
   `PlayerFactory::entity()` / `TutorialPlayerEntity`.
4. **Cypress is now a hard gate on `tutorial-refactoring` only**. When we
   open MRs targeting `staging` going forward, decide whether to promote
   the gate there too (currently `when: manual`).
5. **Cache busting** — new tutorial JS/CSS have their own version
   params; audit any inline-JS page you touch before ship.
6. **Feature flags** — `TUTORIAL_V2_ENABLED` must be ON in prod
   `db_constants.php` for the new overlay to reach non-whitelisted
   players. CI currently force-enables it; prod must match.

---

## 8. Deploy checklist (short form)

Full version: `docs/tutorial-deployment-checklist.md` +
`docs/CRAFTING_TUTORIAL_DEPLOYMENT.md`.

1. Take a DB snapshot.
2. `doctrine-migrations migrate --no-all-or-nothing`.
3. Run `scripts/populate_tutorial_production.php` if seeding fresh.
4. Clear `datas/private/plans/*` and PHP caches.
5. Bump JS/CSS cache-bust versions on deploy server if not already
   bumped by the branch.
6. Set `TUTORIAL_V2_ENABLED` in prod config.
7. Smoke: register a new account, verify auto-invisible state, run the
   tutorial start → complete, verify rewards and `invisibleMode`
   cleared.
8. Monitor `tutorial_progress` + orphan-cleanup cron (`cleanup_orphans.php`)
   for the first 24h.

---

## 9. Documentation index (in `docs/`)

**Roadmaps + audits**
- `player-dismantling-roadmap.md`
- `phase-3-schema-audit.md`
- `phase-4-tutorial-cutover-audit.md`
- `phase-4-5-link-column-collapse-audit.md`
- `player-type-inheritance-migration.md`
- `legacy-migration-tracker.md`

**Tutorial — design + reference**
- `tutorial-system-overview.md` (start here)
- `TUTORIAL_REFACTORING_QUICKSTART.md`
- `tutorial-step-configuration-guide.md`
- `tutorial-interaction-system-summary.md`
- `tutorial-movement-consumption.md`
- `tutorial-xp-pi-integration.md`
- `tutorial-security-model.md`
- `tutorial-p0-deferred-design.md`

**Testing**
- `cypress-testing-guide.md`
- `cypress-gui-setup.md`
- `tutorial-testing-guide.md`

**Crafting tutorial (secondary flow)**
- `CRAFTING_TUTORIAL_CHECKLIST.md`
- `CRAFTING_TUTORIAL_DEPLOYMENT.md`

**Operational**
- `tutorial-deployment-checklist.md`
- `next-steps.md` (handoff / what's queued)

---

## 10. What is intentionally **not** in this branch

Listed so reviewers don't chase them:

- Phase 5+ of the dismantling roadmap (inventory/equip, combat, XP/PR/PF
  mutations). Deferred pending Phase 4 bake-in.
- Retirement of `Classes\Player` itself — still the central legacy
  class. Factory + STI + Phase-1/2/3/4 moves pave the way; removal is
  a future branch.
- Archival of `db/updates/` folder. Deprecated in-place; physical move
  waits until in-flight branches merge (issue #213).
- `when: manual` → auto flip on `staging` / `saison-3` / `main` Cypress
  job.
