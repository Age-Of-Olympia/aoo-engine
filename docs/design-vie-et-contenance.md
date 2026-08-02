# Design — One life, one containment

**Status**: agreed model, phased delivery in progress
**Date**: 2026-08-01
**Goal**: make *having a life* and *holding things* capabilities that any entity
can have, and let an individuated item be an entity — on the map or not.

Companion of [design-buildings-entities.md](design-buildings-entities.md),
[design-resources-entities.md](design-resources-entities.md) and
[design-items-instances.md](design-items-instances.md), whose conclusion on
items this note revisits (§2.4).

---

## 1. Problem

Two lives exist with the same shape and no shared contract:

| | current | max | anchored to |
|---|---|---|---|
| entity | deficit row in `players_bonus` (`name='pv'`, negative `n`) | `races.pv` + upgrades + equipment, recomputed by `get_caracs()` | `players.id` |
| item | `item_instances.durability` (absolute) | `item_instances.durability_max`, frozen copy of `items.durability_max` | `item_instances.id` |

`reparer` heals through `HealingOutcomeInstruction`, which calls
`$target->putBonus(['pv' => $n])` on a `Classes\Player`. Every entity is a
`players` row, so every entity can be a target. **An item in a bag has no
`players` row**, so it cannot be one.

The blocker behind the blocker: a `players` row was understood to mean "occupies
a tile". §3 shows that this was never true.

### 1.1 What it costs today, measured

An object on the board carries race `objet`, and **no `races` row named `objet`
exists**. `get_caracs()` therefore takes its "race not found" branch and sets all
sixteen caracs to 0. Both directions of its life are broken, in opposite ways:

- **Healing is inert.** `putBonus` caps a heal at the deficit; the deficit is 0,
  so repairing a dropped object restores nothing.
- **Damage is fatal.** `putBonus` does *not* cap a loss, so one point of damage
  puts `getRemaining('pv')` at −1; `action.php:154` reads `< 1` and destroys the
  object. **Any object on the ground dies to a single hit**, whatever its
  durability says.

`item_instances.durability` — its real life — is consulted on neither path.

---

## 2. The model

### 2.1 Life is a capability, not a place in the tree

Max life is computed the same way for every entity: **the type's own life, plus
that individual's bonuses**. No second concept, no frozen snapshot.

The type answers the question; the reader never asks what kind of thing it is
looking at:

- a **race** answers `races.pv`
- an **item** answers `items.durability`

This is the [`Harvestable`](../src/Entity/Harvestable.php) pattern: a capability
that crosses families without ranking one under the other. The override lives on
the type. The moment it lives in the reader — `if (this is an item) { … }` — we
have rebuilt the special case we are removing, in a more load-bearing place.

Current life is a **deficit** in `players_bonus` for everything that lives.
Pristine means **no row at all**, exactly as an unwounded character has none.

### 2.2 An item's other fifteen caracs are conferred, never owned

`items` already carries all sixteen CARACS — `a mvt p pv cc ct f e agi pm fm m r
rm spd ae` — the same set as `races`. The two type catalogues are already the
same shape. But the columns are spoken in opposite directions:

- `races.pv` — the PV a member of this race **has**
- `items.pv` — the PV this item **lends its bearer** (`applyItemCaracs`)

A breastplate's `pv` is not the breastplate's life. So an item type's **own** stat
block is exactly one entry — `pv`, taken from `durability` — and the other fifteen
columns stay conferred-only. Reading them as own would give a breastplate a max
life of 5 instead of 100, quietly, on exactly the items where it matters.

The own stat block is still sixteen keys: fifteen zeros and `pv`. `get_caracs()`
initialises all sixteen from the type, and the combat path reads
`$target->caracs->{$trait}` for whichever carac an action names. Fewer keys means
an undefined property mid-fight.

**Consequence, deliberate**: an item has no defence stat, so
`DamageCalculator::rawDamage()` subtracts nothing and the floor of 1 damage
applies. *An object's toughness is its life total, not a resistance.* A sturdy
chest and a flimsy one differ only in how much life they have. If one should ever
shrug off blows, the own stat block grows by one entry (`e`); nothing structural
changes.

**Consequence, deliberate**: raising `items.durability` in the catalogue lifts
every existing instance immediately, where the frozen `durability_max` used to
leave them behind. That is exactly how `races.pv` already behaves — buff a race,
every member gains. One rule, now applied to both.

### 2.3 Location is containment

An entity is **on a cell**, or **inside another entity**, or **nowhere**.

```
players.coords_id  — the cell it stands on        (nullable; NULL = not on one)
players.holder_id  — the entity holding it        (nullable)
players.slot       — how it is held: '', 'main1', 'tronc', 'bank'…
```

Exactly one of `coords_id` / `holder_id` carries meaning. Neither = off the
world. The cell an entity is *ultimately* on is found by walking up the holders:
a sword is in a bag, the bag is on a character, the character is on a cell.

**Children are inventory.** What an entity holds is what points at it. That single
relation gives, as consequences rather than features:

| | |
|---|---|
| an item in a bag | `holder_id` = the character, `slot` = `''` |
| an item equipped | `slot` = `'main1'` — which is what `players_items_instances.equiped` already stores |
| a chest's contents | children of the chest |
| an item on the ground | `coords_id` set, no holder — what the `unique_objects` bridge stages today |
| "a container holding anything cannot be picked up" | it has children |
| "smashing a chest loots it like a player who dies" | re-parent children to the cell — *the same code* |

`players_items_instances` and `map_items_instances` are two cases of this one
relation and retire into it.

`unique_objects` **retires after all** — this note said twice that it would not,
and both drafts were wrong for the same reason: the family was described by what
it was *for*, never counted. It holds zero rows on the development database and
zero on the experimental copy (13 549 buildings, 379 characters), and no entity
carries the `unique` discriminator anywhere. What it was meant to carry —
crystals, gates, artifacts — an installed exemplar now carries, keeping its
identity through being placed and picked up.

The discriminator and its ten branches are gone. The table itself waits for the
same post-deployment pass as `players_items_instances`: a removal lands after
the code, never before.

For the record, what the wrong version said: It is the 1:1 satellite of the `unique` family — crystals,
gates, artifacts — carrying `interaction` (free JSON: dialog id, trigger, loot
table). Its `item_instance_id` is **nullable**: a unique object that wraps
nothing is a legitimate map object with no item behind it. Only the rows that
*do* wrap an instance fold into the relation; the family and its satellite stay.

### 2.4 An individuated item is an entity; a stack is not

`design-items-instances.md` rejected items-as-entities, but its stated reason was
**stacks**: "259 planches = 259 entities". That objection never applied to
instances, which are already one row each — seven on the development database.

The line is the one the schema already draws: an `item_instances` row exists
exactly when an item has an identity to keep. So **an instance is an entity; a
stack stays a `players_items` quantity**. `item_instances` becomes the entity's
satellite table, like `buildings` or `resources`, keeping what is genuinely per
exemplar — `quality`, `params`, `creator_id`, `created_at`, `wear_pending`.

### 2.5 The discriminator is `item`, and location decides behaviour

Everything that made a dropped object structure-like — attackable, destructible,
drawn on the board — follows from **having a cell**, not from what it is. A sword
in a bag must be none of those things and is the same sword.

So the type says `item`, filed under `EntityCategory::Structure` to inherit the
behaviours already correct for it (no malus, no bleeding, the vanish death path
rather than the enfers one). One corner is left open on purpose: a pickaxe's
`demolition` bonus applies to any Structure, so it would apply to a dropped
sword. Harmless, pre-existing in spirit, revisited on its own.

---

## 3. Verified facts

Checked against the running database and the code, not assumed.

- **`players.coords_id` is constrained** by `players_ibfk_1` towards `coords`.
  No `coords` row has id 0 and no `players` row holds 0, so **NULL is the only
  expressible "nowhere"** — the nullable column is the mechanism, not a
  convenience. `EntityCellService` tests `coords_id > 0` throughout, guarding
  against a state the schema already forbids; the test stays true and stops
  being the way absence is written.
- **Off-board entities already exist.** *(superseded — kept because it is why
  the model says what it says.)* `vanish()` used to shelve a building on a
  sentinel plan `limbes_batiments`; every non-map location was faked that way. It
  now writes the absence — `coords_id` and `holder_id` both NULL, `entity_cells`
  removed — and the plan holds no coords at all (checked 2026-08-02). A dead
  character still goes to plan `enfers` (`Player.php`), which is a character
  path and stays.
- **Characters, NPCs and structures already share one life store.**
  `players_bonus` holds `pv` deficits for a character (`4 → −4`), an NPC
  (`−1 → −16`), buildings (`20000004 → −55`) and scenery (`40000016 → −1`).
- **Turn processing never clears `pv`.** `TurnProcessingService:127` deletes only
  `ae`, `a`, `mvt`. A wound persists across turns — which is what wear needs.
- **`players.malus` is not life.** It is the defence-roll penalty; `put_malus()`
  early-returns for every Structure. (The 2026-08-01 handoff note had this wrong.)
- **The frozen max never diverged, and the snapshot is gone.** When this was
  written every max was 100, the catalogue default, so dropping the per-instance
  snapshot cost nothing. The max now lives only on the type, and it does vary:
  25 / 40 / 100 across the chests (checked 2026-08-02). `quality` is still 0
  everywhere, so the per-individual bonus remains untested by real data.
- `takeInstance()` has exactly one caller, `TakeItemOutcomeInstruction`.
- `BuildingService::deleteEntityRows()` deletes the entity's `players_items`, so
  **picking up or destroying a full chest destroyed its contents** — unseen only
  because no unique object existed on the development database. Fixed for the
  death path: `vanish()` spills first, and a container holding anything cannot be
  picked up at all.

---

## 4. Delivery

Each of the three jobs on the original list arrives as a *consequence* of a
phase, not as a feature bolted onto it.

**Status at 2026-08-02.** Phases 1 to 5 are merged, plus the first half of
phase 6. What each phase says below is what was *decided*; where the shipped
work diverged, the phase text says so. Only phase 6 still has work in it.

**Phase 1 — containment exists, nobody uses it.**
`holder_id` + `slot` on the entity, `coords_id` nullable, one service holding the
invariant and resolving "which cell am I ultimately on". Every existing row keeps
a cell and a null holder. No behaviour change.

**Phase 2 — retire the fake.**
`vanish()` records no location instead of shelving on `limbes_batiments`; the
buildings sitting there migrate. `enfers` stays — it is a genuine place where
players act and `PlanCondition` governs what they may do. Only the fake goes.
Small, self-contained, and it proves the model on real data before items depend
on it.

**Phase 3a — the type answers its own life.**
`OwnsCaracs`: a race gives its sixteen caracs, an item gives its
`durability_max` as life and nothing else. `get_caracs()` asks the type through
one service and never learns which catalogue replied. `item` joins
`EntityCategory` and the id ranges (70 000 000+).

*This used to be phase 3b and was swapped deliberately.* An entity whose type
resolves to nothing takes `get_caracs()`'s "race not found" branch: an
`error_log` per call and an all-zero stat block — the exact `objet` bug this
chantier exists to kill, multiplied from "every dropped object" to "every
instance in the game". The type must be able to answer before anything asks it.
It also puts the reversible half first: this phase creates no rows.

**Phase 3b — instances become entities.** *(delivered in three)*
`3b-1` an entity row per `item_instances` row, identity only, no location — plus
the three lifecycle sites, because a migration alone leaves every exemplar born
after it without one. `item_instances` keeps its primary key and gains
`entity_id`: four FKs point at it, two carrying live market state, so re-keying
to save an id is a bad trade. `3b-2a` `slot` learns `installed` vs `dropped`.
`3b-2b` ground loot moves onto the entity, `map_items_instances` retires.
`3b-2c` a placed exemplar reclaims its `players` row — id included, since
keeping it alive is why `vanish()` shelves instead of deleting — and the
`unique_objects` bridge rows go while the family stays.

**Phase 3c — life moves.**
`durability` becomes a `players_bonus` deficit and the instance's two durability
columns die. Every reader keeps its column names: `WEAR_SELECT` / `WEAR_JOIN`
rebuild the pair from the shared life, so what changes is where the numbers come
from, not what the views ask for.

The `items.durability_max` → `items.durability` rename is **deferred**, not
dropped. It reaches the admin screens, the wiki generator and the JSON bundle
keys in `ItemExporter`/`ItemImporter`, where renaming a key is a compatibility
question that deserves its own answer rather than riding along with a data
migration.

**Phase 4 — lockable.** *(merged: !864)*
Being shut is one mechanism for a building and for an object, and it already
exists: `BuildingService::closureReason()` returns *en ruine* / *en construction*
/ *endommagé* (under half PV) / *fermé volontairement*. Nothing in that logic is
building-specific — only its signature, which takes a `BuildingDetails`. Same
shape as `HealingOutcomeInstruction` being typed `Classes\Player`: the rule was
always general, the parameter made it narrow.

So: the **type** says whether it can be shut at all (a chest and a door can, a
wall cannot), implemented by `Race` and by `Item` through the seam `OwnsCaracs`
opened; `owner_id` and `is_open` move from `buildings` onto the entity, so
anything can be owned by a **character or a faction** and shut; and
`closureReason()` becomes the shared rule.

**What closing DOES follows from the thing's other capabilities**, which is why
no door/chest distinction is needed anywhere:

| it also… | …so being shut means |
|---|---|
| blocks passage | shut blocks, open lets through — a **door** |
| holds children | shut denies its contents — a **chest** |
| offers services (dialog) | shut denies them — already true today |

A door is therefore not a family. It is anything shuttable that also blocks, and
it can live in either catalogue: a stone doorway in a rampart and a gate carried
in a bag are the same rule read from two type tables. That costs one thing —
`blocks_passage` lives only on `races` today and has to cross to `items`, the
same move `OwnsCaracs` already made. It also buys a crate that blocks a corridor
without inventing a family for it.

**Closed doors stop people.** They do not today: `TileOccupancyService` never
reads `is_open`, so closure gates services and nothing else, and a shut door is
walked through. Making the step rule ask the closure question is a **rule
change**, not a refactor, and it lands in the file that decides every step —
switched on deliberately, with its own tests.

*Why it follows 3c and not the reverse:* the `endommagé` clause reads a
percentage of life. Wire it before life is one thing and a battered exemplar
reports itself pristine and stays open. And `build_state = 'ruin'` and
`durability <= 0` are the same idea — once life is unified the clause becomes a
question about life instead of a state string, so the per-family branch is never
written rather than written then deleted.

`'en construction'` stays building-only and untouched: it is a placeholder for
the coming work-quantity mechanic, not something an exemplar should fake.

**Phase 5 — containers stop being building types.** *(merged: !866, !867)*
A chest exists twice today: an `items` row (constructible) and a `races` row of
kind `building`. Building one **consumes the item** to produce a building entity
with no exemplar behind it, so placing a chest destroys its wear, its name and
tomorrow its contents. The `coffre_*` building types retire and built chests
convert to installed exemplars.

*Why it follows phase 4:* `buildings` carries `owner_id` and `is_open`, and an
exemplar has nowhere to put either until the entity does. Converting first would
silently strip every standing chest of its owner and its lock.

*What shipped beyond the plan:* the seven standing chests kept their ids —
`ENTITY_ID_RANGES` allocates, it does not classify, and events already name
those ids. The material decides durability (40 / 70 / 100, 25 for the human
chest) instead of inheriting either catalogue's placeholder. Building an object
now **places that object**: a type still described by a race mints a building,
anything else installs its exemplar, so each family leaving `races` switches
sides on its own. A wooden-chest recipe exists so that path is exercised.

**Phase 6 — one way to say who holds a thing.**

Delivered already: `LootSpillService` spills children to the holder's cell, by
the same roll and the same `chanceFor()` rules as a stack unit; a container
holding anything cannot be picked up; `vanish()` spills instead of deleting.

What remains is a **collapse, not a build**, and §2.3 already called for it —
"`players_items_instances` and `map_items_instances` are two cases of this one
relation and retire into it". The new half was built and the old half was not
retired, so today there are two records of who holds an exemplar:

| | records | written by | read by |
|---|---|---|---|
| `players_items_instances(player_id, instance_id, equiped, location)` | ownership, equipped state, bank/market/exchange escrow | every legacy path | every inventory reader |
| `players.holder_id` + `slot` | containment | `putInside()`, `collectAt()` | `childrenOf()`, `holdsAnything()`, `cellOf()` |

`collectAt()` writes both. `putInside()` writes only the second — which is why a
sword placed in a chest is invisible to every inventory reader. **Nothing is
missing from entities**: both ownership tables are keyed on `player_id`, which is
any `players` row, so a chest can already own stacks and exemplars. The defect is
the duplication, not an absence.

The columns fold cleanly, because `slot` was defined with the vocabulary the old
table needs: `player_id` → `holder_id`, `equiped` → `slot` (`'main1'`, `'tronc'`),
`location` → `slot` (`'bank'`), the escrow states likewise. Strangler order:

1. make `holder_id` + `slot` the truth on write, both halves still written;
2. backfill the exemplars that have a bag link and no holder;
3. repoint the readers, one at a time, each with its own baseline;
4. stop writing the old half, then drop the table.

Steps 1–4 are merged. The DROP itself is deliberately **not** in them: a
removal is the one migration that must land AFTER the code, not before, and the
project already has the pattern for saying so —
`App\Service\OwnershipLinkRetirement` reports readiness on the admin dashboard
and the notice erases itself the day the table goes.

Then, riding the same relation: damage, heal and repair through the one life,
retiring the five `player_type === 'unique'` branches; and `reparer` reaching a
bagged sword, because a bagged sword becomes an entity below its max life.

*Verified against the database before writing this:* zero rows carry a
`holder_id`, 15 stack rows belong to characters and 1 to an NPC, 6 exemplars sit
in a bag link. Nothing has to be preserved through the collapse except those 6.

---

## 5. Open questions

Genuinely undecided — not deferred implementation.

- **How a player designates an item to repair.** Board actions target a tile; a
  bagged sword has no tile. Inventory panel, item card, something else.
- **Repair cost.** `reparer` costs one action point and grants 3 XP flat,
  whatever the target and whatever the damage.
- **What `quality` does to max life.** It is the natural per-individual bonus,
  the analogue of `players_upgrades` for a character, and it is 0 everywhere
  today. What it should be worth is game design.
- **Is broken terminal, and where is that enforced?** The contradiction this
  note described is half stale, checked 2026-08-02: `destroyToGround()` no
  longer claims a smashed object *"reste réparable"* — it sets the deficit to
  `-durability_max`, drops the exemplar on its cell and says only that its
  identity survives. But **nothing enforces terminality either**. `BROKEN_AT`
  gates equipping and the state line, never repair, and `reparer` asks only for
  a damaged target. So a broken exemplar is repairable today by omission, not by
  decision. Either the settled rule gains a guard in the repair path, or the
  rule changes — a call for whoever tunes the economy.

---

## 6. Settled rules

Decisions taken, not to be re-litigated without new information.

- Repairable is what someone **erected** — buildings and decor. Not resources,
  not plants. Any type may override its family either way.
- An object below its max life is repairable **wherever it is**. Location is not
  the criterion.
- Broken is terminal.
- A container holding anything cannot be picked up. Generic, not chest-specific.
- Smashing a container kills it: it spills its contents like a player who dies.
- **Being shut is one mechanism** for buildings and objects alike, and ownership
  with it — a character or a faction, exactly as buildings already do it. Locking
  a chest and locking a forge are the same rule read through the same contract.
- **A closed door stops people.** What closing does follows from what else the
  thing is: a barrier blocks, a container withholds, a shop stops serving. A door
  is not a family — it is anything shuttable that also blocks passage, and it may
  come from either catalogue.
- **A placed object is never a building.** Placing installs the exemplar itself,
  so it keeps its wear, its name and its contents through the round trip.
- **The world map shows no dropped or placed objects.** It is for orientation,
  and a chest is not a landmark at that zoom. Reversible if play disagrees.
