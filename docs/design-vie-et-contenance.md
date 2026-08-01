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

`unique_objects` does **not** retire, and an earlier draft of this note was
wrong to say so. It is the 1:1 satellite of the `unique` family — crystals,
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
- **Off-board entities already exist.** `BuildingService::vanish()` shelves a
  building on plan `limbes_batiments` with its `entity_cells` removed — five
  buildings sit there now. A dead character goes to plan `enfers`
  (`Player.php:1934`). There is a `pnjdump` plan. Every non-map location is
  currently faked as a sentinel plan.
- **Characters, NPCs and structures already share one life store.**
  `players_bonus` holds `pv` deficits for a character (`4 → −4`), an NPC
  (`−1 → −16`), buildings (`20000004 → −55`) and scenery (`40000016 → −1`).
- **Turn processing never clears `pv`.** `TurnProcessingService:127` deletes only
  `ae`, `a`, `mvt`. A wound persists across turns — which is what wear needs.
- **`players.malus` is not life.** It is the defence-roll penalty; `put_malus()`
  early-returns for every Structure. (The 2026-08-01 handoff note had this wrong.)
- **The frozen max has never diverged.** Every `item_instances.durability_max` is
  100, the catalogue default, and every `quality` is 0. Nothing is lost by
  dropping the snapshot.
- `takeInstance()` has exactly one caller, `TakeItemOutcomeInstruction`.
- `BuildingService::deleteEntityRows()` deletes the entity's `players_items`, so
  **picking up or destroying a full chest destroys its contents today** — unseen
  only because no unique object exists on the development database.

---

## 4. Delivery

Each of the three jobs on the original list arrives as a *consequence* of a
phase, not as a feature bolted onto it.

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

**Phase 4 — lockable.**
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

*Why it follows 3c and not the reverse:* the `endommagé` clause reads a
percentage of life. Wire it before life is one thing and a battered exemplar
reports itself pristine and stays open. And `build_state = 'ruin'` and
`durability <= 0` are the same idea — once life is unified the clause becomes a
question about life instead of a state string, so the per-family branch is never
written rather than written then deleted.

`'en construction'` stays building-only and untouched: it is a placeholder for
the coming work-quantity mechanic, not something an exemplar should fake.

**Phase 5 — containers stop being building types.**
A chest exists twice today: an `items` row (constructible) and a `races` row of
kind `building`. Building one **consumes the item** to produce a building entity
with no exemplar behind it, so placing a chest destroys its wear, its name and
tomorrow its contents. The `coffre_*` building types retire and built chests
convert to installed exemplars.

*Why it follows phase 4:* `buildings` carries `owner_id` and `is_open`, and an
exemplar has nowhere to put either until the entity does. Converting first would
silently strip every standing chest of its owner and its lock.

**Phase 6 — the rest of the capabilities land.**
Damage, heal and repair go through the one life; every `player_type === 'unique'`
branch dies with them. Inventory becomes children, so `LootSpillService` becomes
"re-parent my children to my cell", and a smashed chest spilling its contents is
the same code as a player dying. `reparer` reaches a bagged sword because a
bagged sword is an entity below its max life.

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
- **Broken is terminal** (`durability <= 0` cannot be repaired) versus
  `destroyToGround()`'s docblock, which says a smashed object falls at 0 and
  *"reste réparable"*. The two must be reconciled once a chest dies by spilling.

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
- **A placed object is never a building.** Placing installs the exemplar itself,
  so it keeps its wear, its name and its contents through the round trip.
- **The world map shows no dropped or placed objects.** It is for orientation,
  and a chest is not a landmark at that zoom. Reversible if play disagrees.
