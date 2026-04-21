# Next steps — handoff for next context

**Authored**: 2026-04-19, end of the cypress-stabilization arc.

This is a self-contained restart guide. Pair it with `CLAUDE.md` (project conventions) and the docs it references below.

## Where we just landed

Cypress on `tutorial-refactoring` is now an auto-triggered, hard-fail CI gate after a 6-MR stabilization arc:

| MR | What it fixed |
|----|---------------|
| !361 | `TEST_DB_*` env-var aliases so `cy.task('queryDatabase')` reaches the CI mariadb service |
| !362 | Missing `tutorial_dialogs` CREATE TABLE in `db/init_noupdates.sql` |
| !363 | Force-enables `TUTORIAL_V2_ENABLED` in CI's generated `db_constants.php` so cypress-registered players (auto-IDs outside the dev whitelist `[1,2,3]`) hit the tutorial overlay path |
| !364 | Spec dismisses the `observe_tree` info card before clicking the tree tile (cypress actionability check was failing on the covered element) |
| !365 | Drops `when: manual` for `tutorial-refactoring` (auto-trigger) |
| !366 | Drops `allow_failure: true` (cypress is now a real signal) |

4 consecutive greens at 384–396 s. Re-running the full spec locally:

```bash
/var/www/html/scripts/testing/reset_test_database.sh && \
CYPRESS_CONTAINER=true TEST_DB_NAME=aoo4 xvfb-run --auto-servernum npx cypress run \
  --spec "cypress/e2e/tutorial-production-ready.cy.js" --browser electron
```

`TEST_DB_NAME=aoo4` is **required locally** because cypress.config.js's `queryDatabase` task defaults to `aoo4_test` while apache writes to `aoo4`. Without the override the spec fails at line 263 with `Cannot read properties of undefined (reading 'id')` — the SELECT against the wrong DB returns no rows.

## What's queued (in priority order)

### 1. PlayerFactory dismantling — Phase 1 (recommended next)

**Doc**: `docs/player-dismantling-roadmap.md`. Phase 0 (the factory itself) is delivered; Phase 1 is the mechanical migration of root controllers + `src/View/**` from `new Player($id)` to `PlayerFactory::legacy()` / `::active()`.

**Pre-flight (separate small MR first)**: characterization smoke test that constructs `new Player($id)` and `PlayerFactory::legacy($id)` for the same id and asserts identical public state for `->id`, `->data->X`, `->caracs->X`, `->coords->X`. Pins the equivalence the migration relies on.

**Then**: pure search-and-replace MR across the ~25 files using one of two patterns (documented in roadmap §Phase 1). Cypress now actively guards regressions — first refactor with real end-to-end coverage.

**LOC**: ~80 lines / ~25 files (per roadmap estimate).

### 2. D4 Phase A test-debt backfill (low-risk debt paydown)

**Doc**: `docs/tutorial-p0-deferred-design.md` §Test-debt baseline.

Backfill PHPUnit tests for already-merged P0 fixes (MRs #329–#333) that landed without coverage. Pure debt paydown; no behaviour change. Could happen in parallel with Phase 1 as breather work.

### 3. D4 Phase C continuation (DB-touching tutorial integration tests)

**Doc**: `docs/tutorial-p0-deferred-design.md` §D4.

The integration harness (`tests/Tutorial/Mock/TutorialIntegrationTestCase.php`) and smoke tests landed earlier this session. Remaining files:
- `TutorialPlayerCleanupIntegrationTest`
- `MovementStepDbBranchesTest`
- `TutorialManagerCompletionFlowTest`

Each follows the established skip-not-fail Doctrine pattern. These unblock Phase 4 of the dismantling roadmap (tutorial subsystem cut-over) — that phase has a hard prerequisite of D4 Phases A/B/C all landed first.

### 4. Archive `db/updates/` (housekeeping)

GitLab issue #213 — move `db/updates/` to `db/updates_archive/` once in-flight branches merge. Don't do early; the rebase-storm risk is real. Wait for the dust to settle on `tutorial-refactoring` → `staging`.

## Critical context to load on restart

These are the things easy to miss from code/git alone:

1. **TDD + KISS preference** — saved in auto-memory. Characterization tests before legacy refactors; smallest viable change per concern; no premature abstractions. The `aoo-legacy-modern-bridge` agent (in `~/.claude/agents/`, local-only) enforces this at review time.

2. **Local cypress requires `TEST_DB_NAME=aoo4`** — see "Where we just landed" above.

3. **Local `aoo4` accumulates `CypressTest*` rows** between runs (the spec uses an 8-letter random pool, so collisions happen after ~8 runs). When you hit `Ce nom de personnage est déjà pris`, run:
   ```bash
   mysql -h mariadb-aoo4 -u root -ppasswordRoot aoo4 \
     -e "DELETE FROM players_forum_missives WHERE player_id IN (SELECT id FROM players WHERE name LIKE 'CypressTest%'); \
         DELETE FROM players WHERE name LIKE 'CypressTest%';"
   ```
   FK constraint requires the `players_forum_missives` delete first. Worth fixing properly later by giving the spec a UUID-suffixed name.

4. **`when: manual` is intentional on `staging`/`saison-3`/`main`** — the auto-trigger flip in !365 only covered `tutorial-refactoring`. Each long-lived branch needs independent validation before its gate flips. See the comments in `.gitlab-ci.yml` lines 211–225.

5. **`db/updates/` is deprecated** as of 2026-04-19. All schema/data changes go through Doctrine migrations now. See `db/updates/README.md`.

6. **The four user-level review agents** in `~/.claude/agents/` (not committed):
   - `aoo-tutorial-reviewer`
   - `aoo-legacy-modern-bridge`
   - `aoo-security-reviewer`
   - `aoo-migrations-and-cache`

   Re-create them in the new context if they're missing — definitions aren't in the repo by user choice.

7. **Conventional commits, English, no AI mention** — applies to commits AND MR descriptions. The user reviews titles for tone.

8. **"Merge and go" pattern** — when the user says this, it means: queue auto-merge, pull latest, delete local branch, move on without summarizing. Used heavily during this session's MR train.

## Suggested first action on restart

If continuing the dismantling thread: open the Phase 1 pre-flight MR (the equivalence smoke test). One short PHPUnit file, no schema changes. Land it, then open the Phase 1 search-and-replace MR.

If something else has come up that needs attention first, the queues above survive being re-ordered.
