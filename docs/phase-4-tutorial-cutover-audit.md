# Phase 4 tutorial cut-over — audit and sub-plan

**Preamble to Phase 4** of the `Classes\Player` dismantling roadmap
(`docs/player-dismantling-roadmap.md` §Phase 4). Read this before any
Phase 4 code MR.

## Why this audit

The roadmap describes Phase 4 in one paragraph: "make `TutorialManager`
and tutorial code operate on `TutorialPlayerEntity`". The hands-on
reality is that **two parallel tutorial-player representations exist
today**, with divergent APIs, and the entity's current methods have
bugs that would ship to prod if we swapped in the entity naively.
This doc maps the gap and proposes a safe sub-plan.

Risk rating per the roadmap: **Medium-high** — tutorial is
user-facing, fragile, and a dual-write window (legacy writes + entity
writes on the same row) can silently corrupt state.

## Current state — two tutorial-player classes

### `App\Tutorial\TutorialPlayer` (the service class)

- File: `src/Tutorial/TutorialPlayer.php`
- Hand-rolled persistence: constructor takes a `Connection`, static
  factory `create()` runs raw SQL, `load()` / `loadBySession()` hydrate
  from queries.
- Lifecycle: created by `TutorialResourceManager::createTutorialPlayer`
  during `TutorialManager::startTutorial`.
- Public API:
  - Properties (all public): `id`, `realPlayerId`, `tutorialSessionId`,
    `playerId`, `actualPlayerId` (alias for `playerId`), `name`,
    `isActive`.
  - `static create(Connection, int $realPlayerId, string $sessionId, ?int, ?string $race, string $templatePlan)` — creates map instance + players row + tutorial_players row. Spawns enemy via `TutorialResourceManager`.
  - `static load(Connection, int $id): self` — hydrate by tutorial_players.id.
  - `static loadBySession(Connection, string): ?self` — hydrate by session.
  - `getPosition(): array` — reads coords via SQL.
  - `delete(): void` — hard-delete the tutorial_players row (not the players row).
  - `transferRewardsToRealPlayer(int $xpEarned, int $piEarned): void` — UPDATE xp/pi on the real player.
  - `toArray(): array` — API response shape.

### `App\Entity\TutorialPlayerEntity` (the Doctrine entity)

- File: `src/Entity/TutorialPlayerEntity.php`
- Doctrine STI subclass of `PlayerEntity` (discriminator `player_type='tutorial'`).
- Two entity-specific columns: `tutorial_session_id`, `real_player_id_ref` (both fixed in !383 via `name:` attribute).
- Public API (beyond inherited PlayerEntity getters):
  - `getTutorialSessionId(): ?string` / `setTutorialSessionId(?string)`
  - `getRealPlayerIdRef(): ?int` / `setRealPlayerIdRef(?int)`
  - `isTemporary(): bool` (always true)
  - `isPubliclyVisible(): bool` (always false)
  - `transferRewardsToRealPlayer(Connection): void` — uses `$this->xp` and `$this->pi`.
  - `deleteWithRelatedData(Connection): void` — cascade delete + players row hard-delete.

### How they diverge

| Concern | `App\Tutorial\TutorialPlayer` | `App\Entity\TutorialPlayerEntity` | Gap |
|---|---|---|---|
| Creation | `static create()` → map instance + players row + tutorial_players row | No create helper — you'd `$em->persist(new TutorialPlayerEntity())` | Entity has no map-instance or enemy setup. Moving `TutorialManager::startTutorial` to the entity means re-homing `TutorialMapInstance` creation + `TutorialResourceManager::spawnTutorialEnemy` somewhere. |
| Reward transfer | `transferRewardsToRealPlayer(int $xpEarned, int $piEarned)` — caller passes rewards | `transferRewardsToRealPlayer(Connection $conn)` — uses `$this->xp`, `$this->pi` | **Incompatible**. Entity's method uses the tutorial player's accumulated XP/PI, but `TutorialManager::completeTutorial` awards the *earned* XP from `TutorialContext::getTutorialXP()` — a different number. Swapping in the entity's method silently changes the reward math. |
| Delete | `delete()` — removes `tutorial_players` row only | `deleteWithRelatedData(Connection)` — removes `tutorial_players` + `players` + FK cascade | Different scope. `TutorialResourceManager::deleteTutorialPlayer` already delegates to `TutorialPlayerCleanup` service (which covers FK cascade), so neither method matches the current flow exactly. |
| Position | `getPosition(): array` — raw SQL against coords | `PlayerEntity::getCoordsId()` + `getCoords(Connection)` (from Phase 3.4a) | Entity covers it. |
| Lifecycle | Constructor needs `Connection` injected; `create()` returns fully-wired instance | Doctrine hydrates via `$em->find()` or repository | Different idioms. |

## How `TutorialManager` uses these today

Stored as `private ?TutorialPlayer $tutorialPlayer` (line 29 area). The manager:

1. **`startTutorial`** — calls `$this->resourceManager->createTutorialPlayer(...)` → returns `TutorialPlayer`. Stores on `$this->tutorialPlayer`. Immediately constructs `new \Classes\Player($this->tutorialPlayer->actualPlayerId)` for downstream steps (line 95). So there's ALREADY a legacy `Classes\Player` instance coexisting with the service class.
2. **`resumeTutorial`** — calls `$this->resourceManager->getTutorialPlayer($sessionId)` to rehydrate. Then also `new Player($this->tutorialPlayer->actualPlayerId)` at line 145.
3. **`completeTutorial`** (line 443) — calls `$this->tutorialPlayer->transferRewardsToRealPlayer($xpEarned, $piEarned)` at line 460, then `$this->resourceManager->deleteTutorialPlayer($this->tutorialPlayer, $this->sessionId)` at line 488.
4. **Step validators** — go through `TutorialHelper::loadActivePlayer()` which returns `\Classes\Player`. The roadmap explicitly defers these.

## Findings

1. The **service class** (`App\Tutorial\TutorialPlayer`) is effectively a task-specific ORM. Its `create()` and `load*()` methods do real work the entity doesn't replicate (map-instance coordination, enemy spawn).
2. The **entity** (`TutorialPlayerEntity`) has methods with the same names as the service class's but **different semantics**. Swapping without fixing the API would regress reward math.
3. **Three phases of work** are needed, not one:
   - **4.1** Fix the entity's mismatched methods so swap-in is safe.
   - **4.2** Migrate `TutorialManager::completeTutorial` to use the entity (narrow, read + mutation already captured in Phase 3.2/3.4 infrastructure).
   - **4.3** Migrate `TutorialManager::startTutorial` and resume — requires re-homing map-instance and enemy-spawn calls.
4. **`App\Tutorial\TutorialPlayer`** should NOT be deleted in Phase 4 — it still owns create-time coordination. A later Phase 4.4+ can replace it once a repository/factory for `TutorialPlayerEntity` carries the same workflow.

## Proposed sub-plan

### Phase 4.0 — this doc

Docs-only. No code.

### Phase 4.1 — reconcile entity methods with their service-class counterparts

Small, focused changes on `TutorialPlayerEntity`:

- **`transferRewardsToRealPlayer(Connection, int $xpEarned, int $piEarned): void`** — add the int params to match the service signature. The entity's own `$this->xp` is the wrong thing to transfer. (The current signature is effectively dead code — no caller.)
- Add a **`Tests\Tutorial\TutorialPlayerEntityRewardTransferTest`** (DB-gated) pinning the new contract: given a seeded real + tutorial player, `transferRewardsToRealPlayer($conn, 100, 100)` increments the real player's `xp` and `pi` by exactly 100.

No caller migrations in this MR — just the entity API fix.

### Phase 4.2 — migrate `TutorialManager::completeTutorial` to entity

- Inside `completeTutorial`, load `TutorialPlayerEntity` via
  `EntityManagerFactory::getEntityManager()->find(TutorialPlayerEntity::class, $this->tutorialPlayer->actualPlayerId)`.
- Replace `$this->tutorialPlayer->transferRewardsToRealPlayer($xp, $pi)` with `$entity->transferRewardsToRealPlayer($conn, $xp, $pi)` (after 4.1's fix).
- Keep `$this->resourceManager->deleteTutorialPlayer(...)` unchanged for now (separate cleanup path through `TutorialPlayerCleanup` service, already covered by !376's integration tests).
- Feature flag via `TutorialFeatureFlag::isEnabledForPlayer($realPlayerId)` per roadmap — gate the entity path, fall back to legacy when the flag is off.

Snapshot test: run the full completion flow twice (feature-flag on / off), assert the DB state is byte-identical in both branches. Integration test already exists (`TutorialManagerCompletionFlowTest` from !376) — extend with a feature-flag branch.

### Phase 4.3 — migrate `startTutorial` and `resumeTutorial`

Biggest MR of the phase. Blocked on 4.1+4.2 landing cleanly and monitoring confirming no divergence.

- Re-home `TutorialMapInstance` creation: it can stay in `TutorialResourceManager` but take a `TutorialPlayerEntity` instead of constructing a `TutorialPlayer` service-class instance.
- `TutorialResourceManager::spawnTutorialEnemy` stays as-is (NPC spawn is orthogonal).
- `$this->tutorialPlayer` field becomes `TutorialPlayerEntity|null`.
- Downstream `new \Classes\Player($this->tutorialPlayer->actualPlayerId)` calls become `new \Classes\Player($this->tutorialPlayer->getId())` — minor syntactic change.

Feature-flag protected for one release before 4.4 retires the service class.

### Phase 4.4 — retire `App\Tutorial\TutorialPlayer` (future)

After 4.3 has baked in prod for a release. Move any remaining useful helpers (e.g. `toArray()`) onto the entity, then delete the service class. Out of scope for this MR series.

## Reuse from existing codebase

- `TutorialPlayerEntity` already hydrates cleanly post-!383 (`bonus_points` migration + `name:` attribute fixes).
- `TutorialPlayerCleanup` service (covered by `TutorialPlayerCleanupIntegrationTest` from !376) handles the cascade delete correctly — 4.3 doesn't need to re-invent this.
- `TutorialFeatureFlag::isEnabledForPlayer(int)` already gates tutorial rendering; reusing it for the cut-over is a natural fit.
- Phase 3.4a's `PlayerEntity::getCoords(Connection)` replaces `TutorialPlayer::getPosition()` once callers migrate.

## Verification

### Phase 4.0 (this MR)

Docs review only.

### Phase 4.1

- `./vendor/bin/phpunit --filter TutorialPlayerEntityRewardTransferTest` passes
- `make test` stays green
- No caller touches the new signature yet — no behaviour change in production

### Phase 4.2

- Feature flag OFF: existing `TutorialManagerCompletionFlowTest` still green.
- Feature flag ON: new test asserts DB state after `completeTutorial` matches flag-off state byte-for-byte.
- Cypress `tutorial-production-ready` passes with flag ON (pointed at a fresh test player).

### Phase 4.3

- Full Cypress tutorial flow (start → complete) with flag ON matches flag-off state.
- Manual smoke: login as new test player, run tutorial end-to-end, confirm no visible regression.
- Watch prod logs for one release before retiring the service class.
