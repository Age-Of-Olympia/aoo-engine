# Tutorial P0 — Deferred Items Needing Design

These are production-blocking risks surfaced by the multi-agent review of the
tutorial system that **were not fixed** in the rapid-fix MR series (#329-#338).
Each one needs design discussion before code, not a quick patch — getting them
wrong makes things worse than leaving them.

Surfaced here so they are not lost. Each section ends with a proposed approach
to seed the design conversation; none are prescriptive.

---

## D1. TOCTOU in `TutorialHelper::getActivePlayerId()`

**File**: `src/Tutorial/TutorialHelper.php:25-43`

**Problem**: The method checks `$_SESSION['in_tutorial']` and
`$_SESSION['tutorial_player_id']`, then calls `validateTutorialPlayer()` which
hits the DB. Between the session check and the validation, another concurrent
request from the same user (e.g., a stale AJAX retry) can mutate session state
or delete the tutorial player row. The two requests then disagree about which
player they are operating on.

**Why a quick fix is risky**: Adding a `LOCK TABLES` or row-level
`SELECT ... FOR UPDATE` blocks every page render that calls
`getActivePlayerId()` (which is most pages during a tutorial). A naive
mutex via APCu doesn't survive container restarts. A session-level lock via
`session_write_close()` discipline ripples across many caller files.

**Proposed approach**:
1. Quantify first — add a per-request log line that records `(session_id,
   tutorial_player_id, validation_result)`. Run for one week post-deploy. If
   the divergence rate is < 0.1%, the practical risk is below the cost of
   fixing.
2. If divergence is non-trivial: introduce a tiny per-player advisory lock
   keyed on `MD5("tutorial:{$_SESSION['playerId']}")` using MariaDB's
   `GET_LOCK()` / `RELEASE_LOCK()` (no infrastructure to add, ~10ms overhead).
   Wrap `getActivePlayerId` in `acquire → check → release`. Document the
   lock release on every exit path (including thrown exceptions).
3. Reject any solution that requires a new external service (Redis, etc.) —
   the project has no such dependency today.

**Effort estimate**: small to validate (1 day with telemetry), medium to fix
if needed (2-3 days).

---

## D2. Partial cleanup race in `TutorialResourceManager::createTutorialPlayer`

**File**: `src/Tutorial/TutorialResourceManager.php:46-85`

**Problem**: Player creation, map instance creation, and enemy spawning happen
sequentially without a wrapping transaction. If `spawnTutorialEnemy()` throws
after the player and map instance are already created, the catch block calls
`cleanupPrevious()` which searches by `real_player_id` — but the just-created
record might not be reachable that way (e.g., session_id mismatch). Result:
orphan rows in `tutorial_players` + `tutorial_map_instances` that the cleanup
cron eventually mops up but live for ≥24h in the meantime.

**Why a quick fix is risky**: Wrapping the three operations in a Doctrine
transaction is the right answer in principle, but `Classes\Db` (used inside
some of these calls) does NOT participate in Doctrine's transaction — they
use separate connections. A transaction around just the Doctrine layer leaves
the legacy SQL writes uncommitted, doubling the inconsistency window.

**Proposed approach**:
1. Audit which of the three operations use which connection. Either:
   - Migrate the legacy SQL to Doctrine (preferred but invasive — touches
     `TutorialPlayer::__construct`, `TutorialMapInstance::create`,
     `spawnTutorialEnemy`).
   - OR introduce a saga-style explicit compensation: track a list of
     "rollback steps" as you go (`createdPlayerId`, `createdMapPlan`,
     `createdEnemyIds`), and on any throw, walk that list in reverse to
     undo each in isolation.
2. The cleanup cron (`cleanup_orphans.php`, MR #337) is the safety net for
   whichever approach lands; lower the abandonment threshold to 1h once the
   transactional fix is in place.

**Effort estimate**: medium (3-5 days) for either approach. The saga approach
is faster but uglier; the Doctrine migration is cleaner but touches more code.

---

## D3. `sessionStorage` collision across tabs

**File**: `js/tutorial/TutorialInit.js:145-160`

**Problem**: `sessionStorage.tutorial_active` is a per-window flag. If the
player opens the game in two tabs, tab A sets the flag, tab B reads it 500ms
later in `checkForActiveTutorial()`, and **both** call `resume.php` for the
same `tutorial_session_id`. Server-side there is no per-session lock so the
state can corrupt or duplicate-advance.

**Why a quick fix is risky**: Naively coordinating via `localStorage` (with
the storage event) helps for tabs in the same browser but does nothing for
two browsers / two devices. The right primitive is a server-side claim on
the session — but the existing API surface doesn't expose claim semantics
and adding them carries refactor risk.

**Proposed approach**:
1. Server-side: add an optional `claimed_by` column to `tutorial_progress`
   with a short TTL (5-10 min, refreshed on each `advance.php`). `resume.php`
   refuses if `claimed_by` is set and not stale.
2. Client-side: on conflict response, surface "Tutorial open in another tab —
   close it or wait 10 min" modal instead of attempting resume.
3. localStorage event coordination is a *bonus* for same-browser case but not
   the canonical defense.
4. Telemetry first (same as D1) — log `tutorial_session_id` per resume call
   and count duplicates over 7 days before investing.

**Effort estimate**: medium-high (5-7 days) including schema migration,
client UX, telemetry, and a Cypress multi-tab test.

---

## D4. PHPUnit test scaffolding for the tutorial subsystem

**Scope**: 19 PHP classes in `src/Tutorial/`, **0** unit tests.

**Problem**: Every fix MR in the production-readiness backlog has had to rely
on manual smoke tests because there is no PHPUnit safety net. As the tutorial
gains complexity, regressions become inevitable.

**Why a quick fix is impossible**: This is a workstream, not an MR. Building
out coverage for `TutorialHelper`, `TutorialFeatureFlag`, the 5 step
validators, `TutorialSessionManager` cleanup paths, etc. is realistically
40-60 hours.

**Proposed approach** (incremental, by MR):
1. **Phase A** (small, immediate value): unit tests for `TutorialFeatureFlag`
   (env / constant / DB / default fallback chain) and
   `TutorialPlaceholderService` (placeholder substitution edge cases). No DB
   needed. ~1 day.
2. **Phase B**: tests for each `Steps/*` validator (`MovementStep`,
   `ActionStep`, `UIInteractionStep`, etc.) using a `Tests\Tutorial\Mock\`
   harness similar to the existing `tests/Player/Mock/TestDatabase.php`.
   ~3-5 days per cluster.
3. **Phase C**: integration tests for `TutorialManager::completeTutorial` and
   the cleanup chain (`TutorialPlayerCleanup`, `TutorialEnemyCleanup`)
   against a `aoo4_test` DB. ~5 days.
4. Wire the new test directory into `phpunit.xml` (`tests/Tutorial/` suite)
   and into the CI `test_job`.

**Effort estimate**: large (40-60 hours). Best done as a sustained
workstream over several sprints, not a single MR.

---

## Sequencing recommendation

1. **D4 Phase A** first — costs 1 day, gives partial coverage immediately,
   and establishes the testing patterns that subsequent Phase B/C MRs follow.
2. **D1 telemetry** in parallel with Phase A — cheap to add, gives data to
   decide if the fix is needed.
3. **D2 saga approach** if partial-cleanup-orphan rate stays high in
   monitoring (the new `cleanup_orphans.php` script also gives us the data
   to measure this).
4. **D3** is the lowest priority of the four — multi-tab collisions are
   real but the user-visible failure mode is "tutorial appears stuck" which
   the player can fix by closing one tab. The other three have silent
   data-corruption modes.

## Status

These four items are NOT addressed by MRs #329-#338 (the rapid-fix series).
Surfacing them here ensures they remain visible after this branch ships.
