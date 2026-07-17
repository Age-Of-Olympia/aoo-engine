# Design Plan — Buildings & Unique Objects as Game Entities

**Status**: draft for iteration
**Date**: 2026-07-04
**Goal**: introduce buildings and unique map objects that have health points (PV),
mutualizing as much as possible with the existing player/combat machinery.

---

## 1. Problem Statement

We want to add to the game world:

- **Buildings** (towers, palisades, warehouses…): placed on the map, owned by a
  player or faction, attackable, destructible.
- **Unique objects** (magic crystal, gate, artifact on the map…): attackable or
  interactable, with health points.

Both share properties with players: a position, a name, health points, the
ability to be targeted by actions. The question is how to model that sharing.

---

## 2. Current State (what exists today)

### 2.1 The `players` table is already a multi-type entity table

- `players.player_type` discriminator column (`real`, `tutorial`, `npc`) with
  Doctrine **single-table inheritance** rooted at `src/Entity/PlayerEntity.php`:
  - `RealPlayer`, `TutorialPlayer`, `NonPlayerCharacter`
  - Same STI pattern as the `Action` hierarchy (`src/Entity/Action.php`)
- NPCs are the existing proof that "non-player things that live on the map and
  can be attacked" work as `players` rows (negative IDs, filtered out of lists,
  no login, no turns).

### 2.2 Health points are not a column — they are computed

- Max PV = race base stats (`races` table) + upgrades + items + effects,
  computed by `Classes/Player::get_caracs()` / `PlayerCaracsService`.
- Wounds/damage live as rows in **`players_bonus`** (`player_id`, `name='pv'`, `n`).
- Current PV read via `$player->getRemaining('pv')`, damage applied via
  `$player->putBonus(['pv' => -X])`.

### 2.3 The combat pipeline is typed on `Player`

- `ActionExecutorService::__construct(Action $action, Player $actor, Player $target, …)`
- Conditions (distance, obstacles…), outcomes (`LifeLossOutcomeInstruction`),
  XP, and logs all operate on legacy `Classes\Player` instances resolved by id.

### 2.4 Satellite tables all FK to `players.id`

`players_bonus`, `players_effects`, `players_items`, `players_logs`,
`players_actions`, `players_options`, `map_walls.player_id` (owner)… This is,
de facto, a **single shared ID space** for every entity in the game.

### 2.5 `map_walls` is a second, simpler damageable system

Walls have `damages` (with `-1` = gatherable, `-2` = depleted) and an owner
(`player_id`). They block movement and are used for resources. They do **not**
go through the combat pipeline.

---

## 3. Greenfield Ideal (the north star)

If we were designing from scratch, we would **not** use inheritance as the
primary tool. Capabilities do not form a tree:

- a warehouse has HP + inventory, but no movement;
- an NPC moves and fights, but has no account;
- a siege engine is a "building" that moves;
- a chest has HP + inventory but cannot act.

Single inheritance slices the world along one axis; "what can this thing do"
is multi-axis. The classic outcome of forcing it is a god base class — which
is exactly how the legacy `Classes/Player.php` happened.

The ideal is **entity + components** (composition over inheritance):

```
entities        (id, kind, name, position_id)        ← one ID space for everything
health          (entity_id, current, max)
combat_stats    (entity_id, attack, defense, dodge…)
mobility        (entity_id, movement_points)
inventory       (entity_id → items)
ownership       (entity_id, owner_entity_id)
actor           (entity_id, action_points, next_turn) ← things that take turns
account         (entity_id, email, password)          ← real players only
effects, logs   (entity_id, …)                        ← reference the ONE id space
```

An entity's type is its component combination. Behavior lives in services
typed against **capability interfaces**, never concrete types:

```php
interface Damageable { public function health(): Health; }
interface Combatant extends Damageable { public function combatStats(): CombatStats; }

final class CombatService {
    public function attack(Combatant $attacker, Damageable $target): AttackResult;
}
```

Key greenfield principles:

1. **Inherit identity, compose behavior.** One thin `Entity` root (id, name,
   position) is fine; everything else is a component.
2. **Separate account from character.** Login/email/password are not properties
   of a damageable map thing (today NPCs carry password hashes).
3. **One shared ID space** so cross-cutting systems (effects, logs, wounds)
   reference `entity_id` without polymorphic keys.
4. Rules ("what may target what", "what happens on destruction") live in
   services keyed on components, not in the type tree.

### 3.1 How far is AoO from this?

Closer than it looks. Squinting at the schema: `players` is the entity table,
and `players_bonus` / `players_effects` / `players_items` / `players_options`
are component tables keyed on the shared ID. The game accidentally half-built
an entity-component system. Its gaps vs. the ideal:

- the entity table is wide (account data baked in);
- type is an id-sign convention + discriminator;
- behavior is fused into one legacy class instead of capability-typed services.

**Every step below is chosen to move toward this ideal, never away from it.**

---

## 4. Chosen Design (pragmatic, incremental)

### 4.1 Decision summary

| Decision | Choice | Rejected alternative |
|---|---|---|
| Storage | New discriminator values in the existing `players` STI | Class-table inheritance / separate sibling tables |
| Class hierarchy | Shallow: `GameEntity` → `Character` / `Structure` | Deep type trees (max 2 levels, ever) |
| Building-specific data | Satellite 1:1 table (component pattern) | New columns on `players` |
| Max PV source | Non-playable "races" per structure archetype | Bypassing the caracs system |
| New code contracts | Capability interfaces (`Damageable`, …) | Typing against concrete classes |

### 4.2 Why not sibling tables under a common parent?

A parent class *above* Player with buildings/objects as siblings is the right
conceptual shape — but only at the **class** level. At the **table** level it
breaks the mutualization we're doing this for:

- Every satellite system FKs to `players.id`. A building with an ID from
  another sequence cannot have wounds (`players_bonus`), effects, or logs
  without duplicating those tables or migrating them to polymorphic keys
  (`entity_type` + `entity_id`) — a migration touching the hottest tables.
- Runtime behavior lives in legacy `Classes/Player` + `ActionExecutorService`,
  which resolve by player id. Share the table → buildings are attackable
  today. Separate tables → refactor the whole legacy surface first.
- Doctrine CTI adds a join to every query and requires migrating existing PKs
  into a new root table.

The codebase has already accepted "one wide table + discriminator" as its
inheritance idiom twice (`Action`, `PlayerEntity`). Unused columns on a
building row (`psw`, `faction`, …) are the cheap side of that trade.

### 4.3 Entity class hierarchy

Doctrine STI: the root owns the table; intermediate abstract classes are free
(no discriminator entry needed). Restructure `PlayerEntity` into:

```php
#[ORM\Entity]
#[ORM\Table(name: "players")]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'player_type', type: 'string')]
#[ORM\DiscriminatorMap([
    'real'     => RealPlayer::class,
    'tutorial' => TutorialPlayer::class,
    'npc'      => NonPlayerCharacter::class,
    'building' => Building::class,
    'unique'   => UniqueObject::class,
])]
abstract class GameEntity          // id, name, coordsId, PV surface
{ }

abstract class Character extends GameEntity   // race, faction, xp, DLA, psw…
abstract class Structure extends GameEntity   // immobile, no turns, owner…

class RealPlayer         extends Character { }
class TutorialPlayer     extends Character { }
class NonPlayerCharacter extends Character { }
class Building           extends Structure { }
class UniqueObject       extends Structure { }
```

Notes:

- `GameEntity` is mostly today's `PlayerEntity` split in two; the entity layer
  is young, so consumers are few.
- **Hard rule**: never add a third level. When a new type doesn't fit
  `Character`/`Structure`, add a component (satellite table), don't fork the tree.
- Polymorphic queries come free: `SELECT e FROM GameEntity e WHERE e.coordsId = :c`
  returns players and buildings mixed.

### 4.4 Capability interfaces

New/refactored services get typed against interfaces implemented by the
entity classes, so they survive the eventual dismantling of the god class:

```php
interface Damageable  { /* getRemaining('pv')-equivalent surface */ }
interface Movable     { }
interface Ownable     { public function getOwnerId(): ?int; }
```

- `attack` targets any `Damageable`
- `heal` targets only `Character`
- future `repair` targets only `Structure`

Enforced via a new **`TargetTypeCondition`** in `src/Action/Condition/` —
the conditions system is built for exactly this. The check becomes
`instanceof` / discriminator-based instead of an id-range convention.

### 4.5 Storage

**No migration on `players`** (the discriminator is already `varchar(20)` and
indexed). Add satellite tables (component pattern, mirrors `tutorial_players`
/ `players_pnjs` precedent):

```sql
CREATE TABLE buildings (
  player_id    INT NOT NULL PRIMARY KEY,          -- FK players.id
  archetype    VARCHAR(64) NOT NULL,              -- 'palissade', 'tour', 'entrepot'…
  owner_id     INT NULL,                          -- FK players.id (player or faction leader)
  faction      VARCHAR(255) NULL,
  build_state  VARCHAR(20) NOT NULL DEFAULT 'built',  -- 'construction', 'built', 'ruin'
  CONSTRAINT fk_buildings_player FOREIGN KEY (player_id) REFERENCES players (id),
  CONSTRAINT fk_buildings_owner  FOREIGN KEY (owner_id)  REFERENCES players (id)
);

CREATE TABLE unique_objects (
  player_id    INT NOT NULL PRIMARY KEY,          -- FK players.id
  archetype    VARCHAR(64) NOT NULL,
  -- interaction config, loot table ref… (TBD)
  CONSTRAINT fk_unique_objects_player FOREIGN KEY (player_id) REFERENCES players (id)
);
```

### 4.6 Health points via pseudo-races

Reuse the caracs computation untouched: create **non-playable "races"** per
structure archetype in the `races` table:

- `palissade`: `pv = 100`, everything else 0 — `esq 0` = cannot dodge,
  `mvt 0` = cannot move.
- Mark non-selectable at registration (mechanism TBD — flag column or
  exclusion in the registration list).

Damage then works with zero new code: `putBonus(['pv' => -X])` +
`getRemaining('pv')`.

### 4.7 Creation & destruction

- **`BuildingService::place(string $archetype, Coords $coords, ?int $ownerId)`**
  mirroring `Player::put_player()`: insert `players` row with
  `player_type='building'`, create/reuse `coords` row, insert satellite row.
- **Destruction**: branch on type in the death/kill path
  (`LifeLossOutcomeInstruction` / kill handling): a destroyed building drops
  materials on the tile (`map_items`) and is removed (or flips to
  `build_state='ruin'` — TBD), instead of the player-death flow.

### 4.8 Boundary with `map_walls`

Rule of thumb:

- Just blocks movement / gets harvested → **wall** (existing system).
- Needs the combat pipeline, effects, observation panel → **player-based structure**.
- Multi-tile buildings: one "heart" `players` row (the damageable entity) +
  `map_walls` rows for the footprint.

### 4.9 What buildings get for free

Being a `players` row means: targetable by `AttackAction`, damage via
`players_bonus`, distance/obstacle conditions (has `coords_id`), tile blocking
in `go.php`, map rendering (avatar), `observe.php` panel, effects, logs.

---

## 5. Risks & Required Audits

| Risk | Mitigation |
|---|---|
| **Player lists/queries** including structures (rankings, "who's here", faction lists) | Audit every direct `FROM players` query in `Classes/`, root controllers, and `api/`. Precedent exists (NPC/tutorial filtering), but this is the main bug source. |
| **Nonsensical actions** (heal/steal on a tower) | `TargetTypeCondition` (§4.4). Inventory of all action types needed to decide each one's allowed target class. |
| **Legacy `Classes/Player` assumptions** (race, faction, turn data) | Buildings get a real `races` row + defaulted columns, so most paths degrade gracefully — but test `get_data()` / `get_caracs()` on a building row **first**, before building on top. |
| **DLA/turn system** touching buildings | NPCs already don't take turns; verify cron/turn-refresh paths ignore `player_type` ≠ real. |
| **Registration/race lists** showing pseudo-races | Non-selectable flag + audit of race enumeration points (see open question #3). |

---

## 6. Incremental Roadmap

Each step is releasable on its own and moves toward the §3 ideal.

1. **Entity refactor**: split `PlayerEntity` → `GameEntity` + `Character`
   (pure rename/move, no behavior change). Small PR.
2. **`Structure` + `Building` entity** + discriminator entries + `buildings`
   satellite table + migration.
3. **Pseudo-race plumbing**: `palissade` race row, non-selectable mechanism,
   verify `get_caracs()` on a building row.
4. **`BuildingService::place()`** + admin/console command to place a building.
5. **Query audit** (risk table above) — buildings visible on map, absent from
   player lists.
6. **Combat**: verify attack-on-building end-to-end; add `TargetTypeCondition`;
   destruction branch in the death path.
7. **`UniqueObject`** type (repeat 2–6, smaller).
8. Later, opportunistically: extract capability interfaces from legacy
   `Classes/Player` as services get pulled out of the god class.

---

## 7. Migration Strategy — Turning the Whole Game Toward §3 Without Breaking It

### 7.1 Measured blast radius (2026-07-04)

The coupling surface is far smaller than folklore suggests:

| Surface | Measured |
|---|---|
| `Classes/Player.php` | **2,484 lines**, 86 public methods (not 51k LOC — that figure appears to count characters) |
| `new Player(...)` call sites | 69 |
| Raw SQL touching `players` | 141 statements across 59 files |
| Already-extracted services | 9+ (`PlayerCaracsService`, `PlayerBonusService`, `PlayerEffectService`, `PlayerOptionsService`, `PlayerPassiveService`, `PlayerSkillsService`, `PlayerActionsService`, …) |

The "Phase 2/3" program (see doc comments in `PlayerEntity`) already established
the non-breaking pattern: **extract a service, turn the legacy method into a
shim that delegates to it**. Zero call sites change; behavior matches
byte-for-byte. The migration below is that same move, repeated, with the §3
component model as the destination.

### 7.2 Ground rules (why nothing breaks)

1. **Legacy methods become shims, never change signatures.** A caller of
   `$player->putBonus()` cannot tell the logic moved.
2. **Shims are deleted only when grep shows zero callers** — never
   speculatively.
3. **One concern per PR, each PR releasable.** No long-lived migration branch.
4. **Characterization tests before each extraction**: pin the current
   behavior of the methods being moved (the existing PHPUnit + Cypress setup
   covers the harness; add golden-master tests for caracs/combat math).
5. **New features are built the new way** (services + interfaces). Buildings
   (§4) is deliberately the pilot: it exercises every seam and proves them.

### 7.3 Phases

**Phase 0 — safety net** (small)
Golden-master tests for `get_caracs()`, `getRemaining()`, `putBonus()`, and
one full attack resolution. These guard every later step.

**Phase A — finish extracting behavior into component services** (the bulk)
~10 concern clusters remain in the 86 methods (inventory, xp/level, DLA/turn,
combat helpers, logs, death…). Each becomes a service + shim, one PR each.
End state: `Classes/Player` is a thin façade of shims — the god class is
hollow, and each service *is* a §3 component's behavior
(`PlayerCaracsService` ≈ the `health`/`combat_stats` component, etc.).

**Phase B — query gateway** (mechanical, wide)
Funnel the 141 raw SQL statements (59 files) through repositories
(`GameEntityRepository` / finder methods). This is where `player_type`
filtering rules get centralized instead of being re-guessed per query — it
directly retires the §5 "player lists" risk class. Greppable, reviewable,
no behavior change.

**Phase C — capability interfaces** (small, high leverage)
Define `Damageable`, `Movable`, `Ownable`, … implemented by both the entity
classes and (via the façade) legacy `Player`. Widen
`ActionExecutorService::__construct(…, Player $target)` to
`Damageable $target` — the `new Player()` sites are unaffected because
`Player` implements the interface. From here on, the type tree stops
mattering to game systems.

**Phase D — schema, optional and last**
The schema is already ~80% of §3 (satellite tables ≈ components, one ID
space). Remaining moves, each independently skippable:
- split `account` out of `players` (email/psw/mail → own table); riskiest,
  do only when Phase B has centralized the queries;
- store `health.current` directly instead of deriving it from
  `players_bonus` rows (simplification, needs a data migration);
- cosmetic renames (`players` → `entities`) — probably never worth it.

### 7.4 What NOT to do

- **No big-bang rewrite / no long-lived branch** — the strangler only works
  if `staging` stays shippable at every commit.
- **Don't ORM-ify everything up front.** Doctrine entities grow as needed;
  raw-SQL-behind-a-repository is a perfectly fine intermediate state.
- **Don't rename tables early.** Renames buy purity, not capability, and
  break every raw query at once — the opposite of incremental.

### 7.5 Effort feel

Phase 0 + A are the real work (weeks of PR-sized steps, parallelizable with
feature work since each is independent). Phase B is mechanical and can be
nibbled file-by-file. Phase C is days. Phase D is optional. The buildings
feature (§6) can start as soon as Phase 0 exists — it doesn't wait for the
migration; it *drives* it.

---

## 8. Open Questions (to iterate on)

1. **Ownership model**: `owner_id` as player FK, faction string, or both?
   What happens to a building when its owner is deleted/inactive?
2. **Construction mechanics**: are buildings placed instantly (admin/quest) or
   built by players over time (resource costs, `build_state='construction'`,
   PV growing as it's built)?
3. **Pseudo-races source of truth**: `races` table vs `RACES` constant in
   `config/constants.php` — which paths read which? Needs a quick audit before
   step 3.
4. **Ruins vs removal**: does a destroyed building leave a ruin (repairable?
   blocks movement?) or vanish and drop materials?
5. **Repair action**: new action type targeting `Structure` — consumes
   resources? Who may repair (owner only, faction)?
6. **Unique objects semantics**: are they only attackable, or also
   interactable (dialogs, triggers)? Possible overlap with `map_dialogs` /
   `map_triggers`.
7. **Multi-tile buildings**: is the heart+walls pattern (§4.8) enough, or do
   we need footprint metadata on the `buildings` table?
8. **Visibility**: do buildings respect `player_visibility` plan JSON
   (tutorial isolation) like players do? Probably yes by construction — verify.

---

## 9. Reanalysis — 2026-07-16

Verified against the codebase twelve days after the plan was written. The
foundations still hold, and several open questions have since resolved
themselves through other chantiers:

- **§2.1 confirmed**: `PlayerEntity` STI with `player_type` discriminator and
  `real` / `tutorial` / `npc` map exists as described.
- **§7.1 measurements still accurate**: `Classes/Player.php` is 2,474 lines /
  86 public methods; 64 `new Player(` call sites outside tests; 10 extracted
  `Player*Service` classes.
- **Open question #3 — RESOLVED** by the races-DB migration (2026-07-10):
  races live in the `races` table with a **`playable` boolean**, and
  `RaceService::getPlayableRaces()` already filters every registration /
  select-list path on it. Pseudo-races are now `playable = false` rows created
  through the existing `admin/races.php` page — no new mechanism needed.
- **Open question #1 — partially resolved**: factions are now DB entities
  (`factions`, `faction_roles`, `FactionService` on
  `integration/hud-redesign`). `buildings.faction VARCHAR` should be a
  reference validated against the faction catalog (same pattern as
  `races.faction` starter select), not a free string.
- **Open question #6 — new hook available**: dialogs are now DB-backed
  (`dialogs` table + admin pages on staging), giving unique objects a
  ready-made interactability channel.
- **Phase 0 — partially exists**: `LifeLossExecuteCharacterizationTest` and
  the combat-simulation tests already pin part of the attack path; golden
  masters for `get_caracs()` / `getRemaining()` / `putBonus()` remain to do.
- **HUD synergy**: the new HUD's selection zone (`observe.php`) and 4-column
  action grid give buildings their UI surface for free — a selected building
  is just another selection-zone state, per `docs/wireframes/NOTES.md`.

### Usure (wear) — future-proofing constraint

A wear/degradation system is planned after this ships (early discussion,
2026-07). Choices in this plan must not preclude it; current design is
compatible:

- **Building decay** = periodic `putBonus(['pv' => -x])` — the pseudo-race PV
  channel carries it with zero schema change; `build_state` is an open
  varchar, so states like `damaged` can be added.
- **Repair** (open question #5) should be designed assuming damage sources
  other than combat (decay, weather) — i.e. don't couple repair eligibility
  to "was attacked".
- **Equipment wear** is a separate chantier: `players_items` stacks by
  `(player_id, item_id)` and per-instance durability will need its own
  data-model migration. Nothing in this plan touches item stacking, so it
  stays open.
- Keep archetype configuration (max PV via pseudo-race, drop tables, future
  wear rates) **in DB, editable via admin pages** — consistent with the
  project-wide JSON→DB strategy.

### Decisions from the 2026-07-16 review

- **One name for the type concept.** `buildings.archetype` duplicated
  `players.race`; dropped. The structure's type IS its races row, labelled
  « Type » in the UI. The races table is the catalog of entity base stats.
- **`races.kind` ('character' | 'structure')** separates PNJ races from
  structure types — `playable` alone couldn't (both are non-playable).
  PNJ creation lists kind='character'; building placement lists
  kind='structure'; a structure kind is never registrable.
- **Repair = heal.** No parallel repair pipeline: healing a structure is
  `putBonus(['pv' => +x])` like a character. The in-game repair ACTION will
  be a heal-type action gated by TargetType ['structure'] — distinct action
  in game terms (open question #5), same machinery. Admin « Restaurer » is
  a reset (PV ledger purge + build_state 'built'), not a game mechanic.
- **Effects are category-gated at the instruction** (App\Enum\EntityCategory):
  ApplyStatus applies only to declared categories, default ['character'] —
  no adrenaline on a palissade; a siege action may declare
  ['character','structure'] to set a building on fire. Removal never gated.
- **Buildings acting later is a non-issue by construction**: a tower that
  shoots = structure-kind race with a > 0 + rows in players_actions, pilotable
  through the same incarnation mechanism as NPCs. Nothing to redesign.

### Requirements traceability — team review (Discord, 2026-07)

The team's building requirements, checked against what is built:

| Demande | Verdict |
|---|---|
| Propriétaire faction OU user, + plan | ✅ shipped — buildings.owner_id (user) + faction (code) + coords/plan |
| PV, casser/réparer | ✅ shipped — attack pipeline, action `reparer`, admin restore |
| Attaquer de loin | ✅ works today — the `distance` action carries TargetType ['character','structure'] |
| Réduction de dégâts physiques/magiques | ✅ works today by catalog stats — the type's races row carries e / r / rm, LifeLoss already computes att − def |
| Voir à l'emplacement (P « comme un PNJ immobile ») — tours de guet | ✅ by construction (races row has `p`, building is a players row) ; the vision-SHARING with the owner is the new mechanic to design |
| Coût de construction + main d'œuvre | ✅ expressible — action *construire* = G1 RequiresItem (matériaux) + RequiresTraitValue (A/énergie) ; build_state 'construction' is reserved for multi-turn builds |
| Accès autorisé/interdit par faction | 🔨 component to add — `building_access` satellite (1:N, house pattern) + check in go.php/interactions |
| Comportements selon faction/race (taxes, interdiction d'utiliser) | 🔨 conditions data-driven sur les actions d'interaction (condition FactionRelation à créer) + le composant d'accès |
| Pré-requis (« une Forge avant un Magasin », « Elfe seulement ») | 🔨 conditions sur *construire* — race gating existe ; « possède un bâtiment X » = nouvelle condition G4 `RequiresBuilding` (petite, data-driven) |
| Niveaux + aperçu du niveau suivant | 🔨 choix de design : colonne level sur le satellite VS une ligne catalogue par niveau (upgrade = changement de type, aperçu = stats de la ligne suivante) — aucun bloqueur |
| Confort | ✅ colonne satellite le jour où son gameplay est défini |
| Ressources récoltables par bâtiment | 🔨 tick de production (cron) + config ; bonus : un bâtiment possède DÉJÀ son propre inventaire (players_items sur son id) — une mine peut accumuler chez elle |

No requirement contradicts the architecture. New generic action pieces
identified: G3 `AlterInstance` (items doc §5b) and G4 `RequiresBuilding`.

### Decisions from the 2026-07-17 review

- **Niveaux de bâtiments = le rang des personnages, même notion.** A
  building is a players row: its level lives in `players.rank`, is
  displayed as symbols exactly like character ranks, and upgrades
  increment it. Per-level stat growth can ride the existing
  `players_upgrades` path — no new leveling system.
- **Droits unifiés (accès + vision + …)**: one component,
  `building_rights (building_id, right, subject_type, subject_ref)` —
  `right` ∈ {access, vision, use…}, `subject_type` ∈ {faction, player}.
  Covers « autoriser l'accès par faction OU par joueur » and « la tour
  de guet partage sa vision à qui a le droit » with the same table.
- **Butin de destruction = l'inventaire du bâtiment, via le chemin de
  mort unifié.** `Player::death()` already drops the dying entity's own
  inventory with per-item loot chances; extract that block into a
  shared drop service and call it from `processBuildingDestruction`.
  Construction costs get DEPOSITED into the building's own inventory
  (players_items on its id — works today), so destroying a palissade
  naturally returns part of its materials. No separate loot-table
  system. Resolves open question #4.
