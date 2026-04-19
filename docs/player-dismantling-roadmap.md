# `Classes\Player` Dismantling Roadmap

Phased plan to gradually move responsibilities OUT of the legacy
`Classes\Player` god class and INTO the modern entity hierarchy
(`PlayerEntity` + `RealPlayer` / `TutorialPlayerEntity` / `NPCEntity`) plus
dedicated services.

**Authored**: 2026-04 (during `tutorial-refactoring` branch work).

## Reality check

- `Classes/Player.php` is **2,501 LOC** (≈110 KB), all raw SQL — no Doctrine. Still a god class by any reasonable measure: ~80 public methods, ~110 raw SQL call sites internally.
- Constructor is **lazy**: stores `$id`, instantiates 5 collaborator services (`PlayerService`, `PlayerPassiveService`, `PlayerEffectService`, `PlayerBonusService`, `ActionPassiveService`), does NOT load row data. Row data is loaded on first `get_data()` / `get_caracs()` / `getCoords()` call.
- 87 files reference `Classes\Player`; ~50+ use `new Player(...)`. Patterns: root controllers, `src/View/**`, `src/Service/**`, console commands, Tutorial code.
- The entity hierarchy `PlayerEntity` / `RealPlayer` / `TutorialPlayerEntity` / `NPCEntity` is fully **dormant** in production code. Only `tests/Player/PlayerIdSystemTest.php` references it. `TutorialManager` itself uses `Classes\Player`, NOT `TutorialPlayerEntity`.
- Major method clusters in `Player`: data load (`get_row`, `get_data`, `get_caracs`, `get_upgrades`), coords/movement (`getCoords`, `move_player`, `go`, `move_followers`), generic key-value tables (`have/add/end/get` over `players_options|effects|actions`), inventory/equip (`equip`, `applyEquip…Bonus`, `getEquipedItems`, `getMunition`, `drop`), combat (`death`, `getPush`, `put_kill`, `put_assist`, `distribute_xp`), progression (`put_xp`, `put_pr`, `put_pf`, `putBonus`, `put_upgrade`, `change_god`, `add_quest`), social (`add_follower`, `delete_follower`, `move_followers`, `get_new_mails`, `check_missive_permission`, `check_share_factions`), refresh hooks (`refresh_view/data/invent/kills/caracs`), static helpers (`refresh_list`, `get_player_list`, `clean_players_assists`).
- Caller usage is **property-heavy**: `$player->id`, `$player->data->X`, `$player->caracs->X`, `$player->coords->X`. Any future wrapper MUST preserve these public properties (or the call site must be rewritten first).

## Methodology: TDD + KISS, characterization tests gate every phase

The legacy class has zero test coverage today. Every extraction is therefore
behaviour-blind: reviewers cannot tell from the diff alone whether the new
service preserves what the old method actually did. To protect against
silent regressions:

1. **Characterization tests are a phase prerequisite.** Each phase that
   moves a responsibility OUT of `Classes\Player` (or any god class)
   starts with — or is preceded by — an MR that adds tests covering the
   CURRENT behaviour of the slice being extracted. The tests pass against
   the legacy code as-is. Then the extraction MR moves the code; the
   same tests still pass against the extracted service. Behaviour
   preservation is mechanical, not visual.

2. **TDD for net-new code** (factories, services, helpers). Failing test
   first (red), simplest implementation (green), refactor. The factory
   delivered in Phase 0 follows this template: `tests/Various/PlayerFactoryTest.php`
   was written alongside `src/Factory/PlayerFactory.php`.

3. **KISS per phase.** Smallest viable change per MR. No bundling
   multiple responsibility extractions; no premature abstractions
   ("we'll need this for Phase 6 so let's add the hook now"); no
   future-proofing unmentioned in the roadmap. Three similar lines
   beat a premature framework.

4. **The `aoo-legacy-modern-bridge` agent enforces all three rules** at
   review time. An MR without characterization tests for an extracted
   slice gets flagged.

## Phase 0: `PlayerFactory` (DELIVERED)

**Location**: `src/Factory/PlayerFactory.php`.

**API** (all static, matches existing `EntityManagerFactory` style — avoids DI plumbing in legacy controllers):

```php
PlayerFactory::legacy(int $id): \Classes\Player    // drop-in for `new Player($id)`
PlayerFactory::active(): \Classes\Player           // tutorial-aware
PlayerFactory::activeId(): int
PlayerFactory::entity(int $id): ?PlayerEntity     // Doctrine read-only path
PlayerFactory::activeEntity(): ?PlayerEntity
```

**Design rules**:

- **No wrapper object** mixing legacy + entity. Coexistence = two parallel objects sharing the same row, never wrapping each other. A wrapping object would tempt people to add behavior to it, and we'd grow a *third* god class.
- **Lazy `legacy()`** — same semantics as `new Player($id)`. Callers still call `get_data()` / `get_caracs()` themselves. Preserves drop-in equivalence.
- **`entity()` is READ-ONLY** for now. Used to start migrating cold reads (rankings, profile pages). Returns `null` on miss instead of `exit()` — kills the legacy `exit('error player id...')` antipattern at the boundary.
- **Old constructor stays callable.** 50+ call sites; we migrate gradually over many phases. Factory is purely additive in Phase 0. We do NOT mark `Classes\Player::__construct` deprecated yet (would create phpstan/IDE noise without payoff).

**Tests**: `tests/Various/PlayerFactoryTest.php` — DB-free smoke tests for `activeId()` session resolution + reflective contract check.

**Diff size**: ~120 LOC factory + ~80 LOC tests + this doc. **No callers migrated yet** — that starts in Phase 1.

## Phase 1: Mechanical migration of root controllers + view classes

**Pre-flight (separate small MR)**: a smoke test that constructs `new Player($id)` and `PlayerFactory::legacy($id)` for the same id and asserts the returned objects expose identical public state for a representative sample of the property-heavy callers (`->id`, `->data->X`, `->caracs->X`, `->coords->X`). One short PHPUnit file, no schema changes — pins the equivalence the migration relies on.

**Goal**: Replace `new Player($playerId)` in the ~12 root controllers and ~10 `src/View/**` classes with `PlayerFactory::legacy()` / `::active()`.

**Deliverable**: Pure search-and-replace PR. Two patterns:
- `$pid = TutorialHelper::getActivePlayerId(); $player = new Player($pid);` → `$player = PlayerFactory::active();`
- `new Player($x)` → `PlayerFactory::legacy($x)`

Behaviour identical.

**Risk**: **Low** — same object returned, same methods, same properties. PHPStan catches typos, `make test` covers regressions in tested paths. Manual smoke test on tutorial start, login, observe, go.

**LOC**: ~80 lines changed across ~25 files.

## Phase 2: Extract `PlayerOptionsService` (lowest-hanging fruit)

**Pre-flight (separate small MR)**: characterization tests for `Player::have_option`, `add_option`, `end_option` — each call combination (option exists, option missing, duplicate add, end on absent) against a `Tests\Player\Mock\TestDatabase` fixture. The tests pass against the current legacy methods. After the extraction lands, the same tests are re-pointed at the new `PlayerOptionsService` and must still pass.

**Goal**: Kill the `Player::have/add/end/get($table, $name)` god-method (lines 455–574 today) — it's a thin SQL wrapper with no business logic.

**Deliverable**: A `PlayerOptionsService` (and possibly `PlayerActionsService` if cleanly separable — they share table prefix but the `actions` branch has business logic about `ortType`/spell typing). Legacy `Player::have_option()` / `add_option()` / etc. become 1-line shims that delegate. Modern code can call the service directly. Add `PlayerEntity::hasOption(string $name): bool` so the entity becomes minimally useful for callers like `AdminAuthorizationService` (which today does `(new Player($id))->have_option('isAdmin')` just to check a flag).

**Risk**: **Low** — service is mechanical SQL extraction, unit-testable, legacy shims preserve every call site.

**LOC**: ~150 LOC service + ~80 LOC tests + ~30 LOC shim modifications.

## Phase 3: Read-path migration to entity layer for cold/safe surfaces

**Schema audit (delivered as Phase 3.0)**: see `docs/phase-3-schema-audit.md`. Enumerates the column gap between `PlayerEntity` and the live `players` table, identifies two blocking hydration defects (`bonus_points` missing from table; `emailBonus` vs `email_bonus` column-name drift), and defines the minimum fix set that unblocks caller migration. **Read this before starting any Phase 3 code MR.**

**Goal**: For pure-read use cases (rankings, profile display, admin listings), call `PlayerFactory::entity()` and use `RealPlayer` getters instead of `new Player($id)->data->X`.

**Sub-phase plan** (per `docs/phase-3-schema-audit.md` §"Phase 3 execution plan"):
- **3.0** — schema audit doc (delivered)
- **3.1** — schema alignment: add `bonus_points` migration, fix `emailBonus` `name:` attribute, add a DB-gated hydration smoke test
- **3.2** — domain methods for non-column dependencies (`isInactive`, `hasOption`, `getCoordsPlan`) so Phase 3.3's SAR can be mechanical
- **3.3** — read-path SAR across `BourrinsView`, `infos.php`, `ResetPasswordView::renderSendUniqueCode`, and `ScreenshotService::generateAutomaticScreenshot`, gated by snapshot tests

**Risk**: **Medium** — the audit identified the known hydration defects; Phase 3.1 clears them before any caller migrates. Each migrated read needs a matching getter (mostly already present per audit §C).

**LOC**: ~250 LOC entity + migration + domain methods, ~150 LOC migrated callers, ~400 LOC tests (hydration smoke + domain tests + snapshot tests).

## Phase 4: Tutorial subsystem cut-over

**Audit (delivered as Phase 4.0)**: see `docs/phase-4-tutorial-cutover-audit.md`. Maps the gap between `App\Tutorial\TutorialPlayer` (the service-class persistence layer) and `App\Entity\TutorialPlayerEntity` (the Doctrine entity), identifies two APIs with the same name but incompatible semantics, and splits Phase 4 into four sub-phases. **Read this before any Phase 4 code MR.**

**Pre-flight (landed)**: the D4 tutorial test workstream delivered earlier (Phase A + Phase C — MRs !376 and !377). `TutorialPlayerCleanup`, `MovementStep` DB branches, and `TutorialManagerCompletionFlowTest` are all covered.

**Goal**: Make `TutorialManager` and tutorial code operate on `TutorialPlayerEntity` instead of the service-class `App\Tutorial\TutorialPlayer`. Tutorial is the best pilot subsystem — relatively isolated, already has its own session/lifecycle, and the entity already hydrates cleanly post-!383.

**Sub-phase plan** (per `docs/phase-4-tutorial-cutover-audit.md`):
- **4.0** — audit doc (delivered)
- **4.1** — reconcile `TutorialPlayerEntity::transferRewardsToRealPlayer` signature with service-class counterpart (current entity signature is dead code, wrong semantics)
- **4.2** — migrate `TutorialManager::completeTutorial` to entity, feature-flagged
- **4.3** — migrate `startTutorial` / `resumeTutorial` (re-homes map-instance and enemy-spawn)
- **4.4** — retire `App\Tutorial\TutorialPlayer` service class after one release baked

Tutorial step validators continue to use `PlayerFactory::legacy()` (Phase 5+ concern — they hit too many legacy methods).

**Risk**: **Medium-high** — tutorial is user-facing, fragile, and the dual-write window (legacy reads + entity writes for the same row) needs careful flushing. Mitigation: feature-flag behind `TutorialFeatureFlag` for one release; monitor prod logs for divergence before retiring the service class.

**LOC**: audit estimated ~250 LOC code + ~150 LOC tests across 4.1+4.2+4.3.

## Phase 5: Inventory & combat (placeholder — needs sub-roadmaps)

**Pre-flight**: characterization-test workstream is the dominant cost here, not the extraction itself. Combat in particular has emergent behaviour (chained outcomes, status stacking, reduction passives) that no current test covers. The sub-roadmap design doc must enumerate the test scenarios before any code change is approved.


**Goal**: NOT a single phase — a meta-phase placeholder. Inventory (`equip`, `applyEquip*Bonus`, `getEquipedItems`, `getMunition`, `drop`) is intertwined with `Classes\Item` (also a god class) and could become an `InventoryService` taking a `PlayerEntity`. Combat (`death`, `put_kill`, `put_assist`, `distribute_xp`, `getPush`) requires its own design doc — too entangled with `ActorInterface`, `ActionExecutorService`, and game-balance constants to plan now.

**Deliverable**: One design doc per cluster BEFORE any code. Each cluster becomes its own 3–5 phase mini-roadmap.

**Risk**: **High** — touches game balance, player perception, write paths, and concurrent state.

**LOC**: N/A — design first.

## Top 3 risks across the roadmap

1. **Property-access leakage** — callers depend on `$player->data->X`, `->caracs->X`, `->coords->X`. Any "modern wrapper" that doesn't replicate these breaks dozens of files silently (PHPStan won't catch dynamic property access on `object`/`stdClass`). **Mitigation**: never replace `Classes\Player` with a wrapper — only ever replace it with the entity at the call site, AFTER that call site is rewritten to use getters.
2. **Dual-write incoherence** — once Phase 3+ writes through Doctrine while reads still go through legacy raw SQL (and vice versa), the in-process `$this->data` cache on `Classes\Player` and Doctrine's identity map can disagree. **Mitigation**: explicit cache invalidation hook in `Classes\Player::refresh_data()` that also calls `EntityManager::refresh()` if an entity is loaded for that ID.
3. **Tutorial regression in Phase 4** — rewrites the most fragile, least-tested user flow. A bug here is immediately user-visible. **Mitigation**: feature flag, parallel-run the old code path for one release, log divergence.

## Explicit non-goals

- We will **NOT** touch combat resolution (`death`, `put_kill`, `put_assist`, `distribute_xp`, `getPush`) in any phase here. Separate roadmap.
- We will **NOT** delete `Classes\Player` even at the end of Phase 4. Estimated lifespan: 12+ months minimum.
- We will **NOT** introduce a DI container (Symfony container, PHP-DI, etc.) as part of this work. Static factory is the pragmatic match for the existing `EntityManagerFactory` / `TutorialHelper` style.
- We will **NOT** migrate `Classes\Item`, `Classes\Forum`, `Classes\Log`, `Classes\View` — they are separate god classes deserving their own roadmaps.
- We will **NOT** change the database schema (`players` table). All work is at the application layer.
- We will **NOT** refactor `TutorialHelper::getActivePlayerId()` itself, even though it does a DB query inside what looks like a getter. Phase 0 just consumes it via `PlayerFactory::activeId()`.

## Coordination with `TutorialHelper`

`TutorialHelper` already exposes `loadActivePlayer()` and `loadPlayer()` — these are EAGER (call `get_data()` immediately) variants of what `PlayerFactory::active()` / `legacy()` do lazily. They predate the factory.

**Decision**: leave them in place for now. Phase 1 may opt to refactor them into `PlayerFactory::activeLoaded()` / `loaded()` — but only after the bulk migration validates the API is the right shape. Prematurely consolidating risks API churn.
