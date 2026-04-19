# Phase 3 schema audit — `PlayerEntity` vs `players` table

**Preamble to Phase 3** of the `Classes\Player` dismantling roadmap
(`docs/player-dismantling-roadmap.md`). Read this before any Phase 3
code MR.

## Why this audit

Phase 3 migrates four pure-read call sites from
`new Player($id)->data->X` to `PlayerFactory::entity($id)` +
`PlayerEntity` getters. That path depends on Doctrine hydration
working, which depends on the entity's column declarations matching
the real `players` table.

`PlayerFactory::entity()` has **zero production callers today** (grep
across `src/**` and `tests/**` returned only `src/Factory/PlayerFactory.php`
and `tests/Various/PlayerEntityDisplayIdTest.php` — the latter uses
`new RealPlayer()` without hydration). Any schema drift is therefore
latent. The first caller migration would be the first time hydration
runs in prod. This audit finds the drift before the migration ships.

## Scope recap — what Phase 3 migrates

Four read-only call sites:

1. `src/View/Classement/BourrinsView.php` — rankings
2. `infos.php` — player profile display
3. `src/View/ResetPasswordView.php` — the LOOKUP paths only
   (`renderSendUniqueCode`); NOT the mutation path
   (`renderGenerateNewPassword`)
4. `src/Service/ScreenshotService.php` — `generateAutomaticScreenshot`
   (the roadmap's `buildContext` reference is stale — no such method
   in the current source)

## Findings

### A. Confirmed schema mismatches (BLOCKING for Phase 3)

These two defects mean `PlayerFactory::entity($id)` throws on
hydration today. Must fix before any caller migration.

| # | Issue | Entity (`src/Entity/PlayerEntity.php`) | Table (`db/init_noupdates.sql`) | Resolution |
|---|---|---|---|---|
| A1 | **`bonus_points` column declared on entity but missing from table** | L61–62: `#[ORM\Column(type: "integer", name: "bonus_points")] protected int $bonusPoints = 0;` | No `bonus_points` column in `CREATE TABLE players` | **Add the column via a Doctrine migration.** The entity's intent is load-bearing: `bonus_points` is reserved for storing "over-the-limit" XP earned on season change (future season-carry-over feature). Keep the entity as-is; bring the table forward with `ALTER TABLE players ADD COLUMN bonus_points INT(11) NOT NULL DEFAULT 0`. Also add the same to `db/init_noupdates.sql` so fresh devcontainer setups include it. |
| A2 | **`emailBonus` property maps to camelCase column; table has `email_bonus`** | L130–131: `#[ORM\Column(type: "boolean", nullable: true)] protected ?bool $emailBonus = false;` — no explicit `name:`, so Doctrine's `DefaultNamingStrategy` uses the property name `emailBonus` as the column name | Column is `email_bonus` | **Add `name: 'email_bonus'`** to the `ORM\Column` attribute. One-line fix. |
| A3 | **`tutorialSessionId` on `TutorialPlayerEntity` maps to camelCase; table has `tutorial_session_id`** | `src/Entity/TutorialPlayerEntity.php:22–23`: `#[ORM\Column(type: "string", length: 36, nullable: true)] protected ?string $tutorialSessionId = null;` — no explicit `name:` | Column is `tutorial_session_id` | **Add `name: 'tutorial_session_id'`**. Under STI, this column appears in every `SELECT` against `players` — hydrating even a `RealPlayer` fails if this is wrong. Found while running the Phase 3.1 hydration test — the audit missed it initially. |
| A4 | **`realPlayerIdRef` on `TutorialPlayerEntity` maps to camelCase; table has `real_player_id_ref`** | `src/Entity/TutorialPlayerEntity.php:25–26`: same pattern as A3 | Column is `real_player_id_ref` | **Add `name: 'real_player_id_ref'`**. Same STI-visibility reasoning as A3. |

Doctrine's `ORMSetup::createAttributeMetadataConfiguration()` uses
`DefaultNamingStrategy` (confirmed in
`src/Entity/EntityManagerFactory.php:43`) — **no auto-snake-case**.
Only columns with explicit `name:` attributes get renamed. The
inconsistency in this entity (10 camelCase columns matched implicitly
+ 5 snake_case columns matched explicitly via `name:`) is a
maintenance hazard separate from A2 — see §D.

### B. Table columns the entity doesn't map (NON-BLOCKING)

| Column | Used by entity? | Used by app code? | Action |
|---|---|---|---|
| `visible` (`varchar(255)` NULL) | No | No — grep returned zero non-test references | Leave as-is. Add a mapping later only if a caller needs it. |
| `tutorial_session_id`, `real_player_id_ref` | Only on `TutorialPlayerEntity` subclass — by design (STI) | Yes, inside tutorial code | Already correct — STI discriminator `player_type` keeps `RealPlayer` from seeing these. |

### C. Caller needs — accessed properties beyond `->data->X` getters

The target files' `->data->X` accesses all have matching getters on
`PlayerEntity` (`getName`, `getRace`, `getPr`, `getXp`, `getRank`,
`getFaction`, `getFactionRole`, `getSecretFaction`,
`getSecretFactionRole`, `getPortrait`, `getAvatar`, `getText`,
`getStory`, `getDisplayId`). But three classes of caller reach
**beyond `->data`**, and those need extra plumbing before Phase 3's
SAR can be mechanical:

| Access | Location | Needs on entity |
|---|---|---|
| `$target->data->isInactive` | `infos.php:131` | Runtime-computed in `Player::get_data()` (`Player.php:2418`): `$this->data->isInactive = $this->id > 0 ? $this->playerService->isInactive($this->data->lastLoginTime) : false;`. **Not a column.** Phase 3 needs a domain method like `RealPlayer::isInactive(PlayerService $svc): bool` — or the caller computes it from `getLastLoginTime()`. |
| `$target->coords->plan` | `ScreenshotService.php:117` | Coords are a separate table. Entity has `getCoordsId()` (FK) but no `Coords` relationship. Phase 3 option: (a) lazy-load `Coords` entity, (b) domain method `getCoordsPlan(Connection): ?string` that does its own query, (c) skip migrating the files that traverse `->coords` — keep them on legacy until a later mini-phase. |
| `$player->have_option('isAdmin')` | `infos.php:140` (admin gate on secret-faction reveal) | Not a column. `PlayerOptionsService` (from !371) is the modern path. Phase 3 option: domain method `RealPlayer::hasOption(PlayerOptionsService, string): bool`, or each caller constructs the service inline. |
| `$player->have_option('incognitoMode')` | `ScreenshotService.php:152` | Same as above. |
| `$player->get_options()` | `ScreenshotService.php:190` | Same — backed by `PlayerOptionsService::getOptions`. |
| `$player->row->mail` | `ResetPasswordView.php:71, 74` | The `->row` path loads from DB via `get_row()`, not `->data`. Entity's `getMail()` returns the same value. Replace `$player->row->mail` with `$entity->getMail()`. ✓ trivially supported. |

### D. Column-name inconsistency (cosmetic, worth noting)

The entity uses **two naming conventions** for SQL columns:

- **camelCase implicit** (property name = column name): `factionRole`,
  `secretFaction`, `secretFactionRole`, `nextTurnTime`, `registerTime`,
  `lastActionTime`, `lastLoginTime`, `antiBerserkTime`,
  `lastTravelTime`, `godId`.
- **snake_case explicit** (via `name:` attribute): `display_id`,
  `plain_mail`, `coords_id`, `bonus_points`.

Both conventions match what's in the current table, so no hydration
issue beyond A1/A2. But any future column addition that picks the
wrong convention breaks hydration. Consider a follow-up MR that
normalises to snake_case everywhere (plus a migration renaming the
camelCase columns to match) — out of scope for this audit.

## Required fixes to unblock Phase 3

Four edits across four files:

1. **New Doctrine migration**
   (`src/Migrations/VersionYYYYMMDDHHMMSS_AddBonusPointsToPlayers.php`)
   runs `ALTER TABLE players ADD COLUMN bonus_points INT(11) NOT NULL DEFAULT 0`.
2. **Update `db/init_noupdates.sql`** to include `bonus_points` in the
   `CREATE TABLE players` statement so fresh devcontainer/CI setups
   don't require the migration to be applied before tests can run.
3. **Edit `src/Entity/PlayerEntity.php`**: add `name: 'email_bonus'` to
   `$emailBonus`'s `#[ORM\Column(...)]` attribute (A2).
4. **Edit `src/Entity/TutorialPlayerEntity.php`**: add
   `name: 'tutorial_session_id'` to `$tutorialSessionId` and
   `name: 'real_player_id_ref'` to `$realPlayerIdRef` (A3, A4). Under
   STI, both columns appear in every SELECT against `players`, so
   hydrating even a `RealPlayer` fails if they're missing.

With those four fixes, Doctrine hydration of every `PlayerEntity`
subclass succeeds against the live schema.

## Phase 3 execution plan

Four MRs, ordered by dependency:

### Phase 3.0 — this doc

Docs-only MR that captures the audit. No code change.

### Phase 3.1 — Schema alignment fix

**Content**: the three fixes in §"Required fixes" above, plus a
pre-flight hydration smoke test.

**Pre-flight test**: `tests/Various/PlayerEntityHydrationTest.php` —
DB-gated (`markTestSkipped` when unreachable), constructs
`PlayerFactory::entity(1)`, asserts it's a `RealPlayer` with non-null
id, and exercises each getter once (including `getBonusPoints()` and
`getEmailBonus()`) to catch any residual column-name drift. Same
skip-cleanly pattern as `PlayerOptionsCharacterizationTest`.

**Files**: new migration, `db/init_noupdates.sql` (+1 line), entity
(+1 line), new test.

### Phase 3.2 — Domain-method additions for non-column dependencies

Add thin domain methods to the entity hierarchy so Phase 3.3's SAR
can be purely mechanical:

- `RealPlayer::isInactive(PlayerService $svc): bool` — wraps
  `$svc->isInactive($this->getLastLoginTime())`.
- `RealPlayer::hasOption(PlayerOptionsService $svc, string $name): bool`
  — wraps `$svc->hasOption($this->getId(), $name) > 0`.
- `PlayerEntity::getCoordsPlan(Connection $conn): ?string` — one
  SELECT against `coords` by `coords_id`, returns plan or null. Keeps
  `Coords` entity out of this phase's scope.

One DB-gated test per domain method.

### Phase 3.3 — Read-path SAR (narrowed scope) — DELIVERED (!385)

`src/View/ResetPasswordView::renderSendUniqueCode` — fully on the
entity layer. Both the id-lookup and name-lookup branches use
`PlayerFactory::entity()` / new `PlayerFactory::entityByName()`.
`$player->row->mail` replaced with `$player->getMail()`;
`$player->id` with `$player->getId()`.

### Phase 3.4a — additional domain methods — DELIVERED (!386)

Two more methods on `PlayerEntity` to unblock the remaining targets:

- `getCoords(Connection): ?object` — returns a stdClass with
  `x/y/z/plan` matching legacy `$player->coords`. Not a Doctrine
  relationship (documented design choice: all Phase 3 callers are
  read-only, a `Coords` entity is a larger mini-phase).
- `getOptions(PlayerOptionsService): array` — delegates to the Phase 2
  service's `getOptions`.

### Phase 3.4b — PlayerCaracsService + BourrinsView migration

New `App\Service\PlayerCaracsService` with
`computeNudeCaracs(int $playerId, string $race): object` that mirrors
the race-base + upgrade-count aggregation from legacy
`Player::get_caracs(nude: true)`. Entity-side domain method
`PlayerEntity::getNudeCaracs(PlayerCaracsService): object` delegates
to it.

BourrinsView migrated off legacy `PlayerFactory::legacy()` +
`get_caracs(nude: true)` onto the entity path. Every `$player->data->X`
replaced with the matching entity getter.

Characterization test proves the service output is identical to the
legacy nude caracs on a real seeded player.

**Out of scope** (still deferred): items / effects / turn bonuses.
Those remain on legacy `Player::get_caracs()` because they touch
`Item::get_equiped_list`, `ELE_BUFFS/DEBUFFS`, and filesystem JSON
caches — each needs its own design decision.

### Phase 3.4b+ — blocked by external dependencies

The remaining three audit targets (BourrinsView, infos.php,
ScreenshotService) cannot migrate cleanly without upstream changes
outside the Phase 3 scope. Each blocker documented so the next
mini-phase can address them deliberately:

| Target | Blocker | Kind of work needed |
|---|---|---|
| **`src/View/Classement/BourrinsView.php`** | `$player->caracs->$k` — racial stats + upgrades aggregation across `Player::get_caracs()`, `Player::get_upgrades()`, `CARACS` loop | Full caracs computation ported to entity layer, OR a `PlayerCaracsService` that takes a `PlayerEntity`. Larger design MR (akin to Phase 2's service extraction but for the computed view). |
| **`infos.php`** | (1) `$player->get_caracsJson()` / `$caracsJson->p` — perception stat needed for distance check. (2) `Item::get_equiped_list($target)` — untyped `$player` param that internally reads `$player->id` as a public property; entity's `$id` is protected. (3) `$target->id<0` — could be `$entity->isNPC()` but callers use raw `id` comparison. | A PlayerCaracsService (as above) + Item.php update to accept `PlayerEntity\|Player` (or access via `->getId()` only). Both are small-to-medium changes in separate MRs. |
| **`src/Service/ScreenshotService.php`** | (1) `$actor` param typed as `Classes\Player` — ActionExecutorService and `Classes\Player` itself pass legacy. (2) `$screenshotPlayer->move_player()` and `get_caracs()` — pure mutation paths not on entity. | Actor-only migration possible once the type hint is widened to accept both types, but the screenshot-PNJ mutation path stays on legacy permanently (entity is read-only by design). Low ROI for Phase 3 — consider keeping on legacy. |

**Recommendation**: before attempting Phase 3.4b, ship a
`PlayerCaracsService` extraction mini-phase (unblocks BourrinsView
and half of infos.php) and an `Item.php` type-hint relaxation (unblocks
the rest of infos.php). ScreenshotService should probably stay on
legacy — its mutation paths make it a poor entity-layer candidate.

**Test gate**: no snapshot tests were needed for the narrow 3.3 scope
— the `PlayerFactoryTest` suite gained three tests for `entityByName()`
(signature + hit + miss), and the Phase 3.1 hydration smoke test
guards getter correctness against schema drift. Broader snapshot
testing returns when the deferred callers land.

## Reuse from existing codebase

- `PlayerFactory::entity(int $id): ?PlayerEntity` (already exists,
  returns null on miss) — the entry point.
- `PlayerFactory::legacy()` / `::active()` — keep for mutation paths
  and files that touch `->coords` or `->have_option` beyond what
  Phase 3.2 ports.
- `PlayerOptionsService::hasOption()` (from !371) — backing store for
  the `hasOption()` domain method.
- `PlayerService::isInactive(int $lastLoginTime)` — backing store for
  the `isInactive()` domain method.
- `TutorialIntegrationTestCase` base class (already used by D4 Phase C
  tests) — template for the hydration smoke test's DB-gated skip
  pattern.

## Verification

Phase 3.0 (this MR): docs review only — no code or tests to run.

Phase 3.1:
- `./vendor/bin/phpunit --filter PlayerEntityHydrationTest` passes on
  a fresh `aoo4` DB
- `make test` stays green
- Manual smoke: log in as Cradek, profile renders unchanged (no
  caller migrated yet — this phase is schema + hydration only)

Phase 3.2:
- `./vendor/bin/phpunit --filter PlayerEntityDomainMethodsTest` passes
- `make test` stays green

Phase 3.3:
- Snapshot tests match byte-for-byte between legacy and migrated
  rendering
- Manual smoke: `BourrinsView` (rankings), `infos.php?targetId=1`,
  reset-password-by-mat lookup, ScreenshotService screenshot
  generation
- Cypress `tutorial-production-ready` still passes (CI stays manual
  per !370 — trigger once before the MR lands)
