# Design Plan — Item Instances & Unique Objects

**Status**: draft for iteration
**Date**: 2026-07-16
**Goal**: give items per-instance identity (durability/usure, enchantments,
names) and bridge singular items to the map through UniqueObject —
WITHOUT turning every plank into an entity, and keeping every current
item interaction expressible through the data-driven action system.

Companion of [design-buildings-entities.md](design-buildings-entities.md);
uses the same method (measure → smallest structural move → strangler
migration, golden masters first).

---

## 1. Problem Statement

Today's item model is stacks of fungible catalog references:

- `players_items (player_id, item_id, n, equiped)` — PK on
  (player_id, item_id): two gladius are indistinguishable, a stack of
  259 planches is one row.
- No per-instance state → **no usure/durability**, no named weapons, no
  per-item enchantment (the `enchanted` flag sits on the CATALOG row).
- Item game stats (emplacement, caracs, price…) live in
  `datas/*/items/*.json`, not in the `items` table — the same JSON→DB
  debt races/factions/dialogs already paid.

The request "items should use the new unique objects" splits into two
distinct needs:

1. **Identity**: some items need individual state — that is a data-model
   change (instances), not an entity change.
2. **Map presence**: some items need to exist ON the map as singular,
   observable, attackable things — that is exactly what the
   `UniqueObject` STI branch is for.

A `players` row means position, PV surface, an id in the entity space.
Right for an artifact on a pedestal; wrong for 259 planks in a bag.
Possession ≠ map presence.

---

## 2. Current State (measured 2026-07-16)

### 2.1 Data

| Surface | Measure |
|---|---|
| `items` catalog | 121 rows; flags `enchanted, vorpal, cursed, element, spell, is_bankable, exotique, is_deprecated` |
| Item stats | in `datas/*/items/{name}.json` (emplacement, caracs, price, text, subtype…) |
| `players_items` | stacks, max n observed 259, `equiped` = emplacement string |
| `players_items_bank` | same shape, no equiped |
| `map_items` | ground stacks (coords_id, item_id, n) |

### 2.2 Code coupling

| Surface | Measure |
|---|---|
| `Classes/Item.php` | 672 lines |
| `Item::` / `new Item()` call sites | ~110 |
| Raw `players_items` SQL | 31 statements / 14 files |
| `equiped` column readers | 13 files |
| Views/flows | InventoryView, BankView, CraftView, merchant scripts, exchanges (asks/bids), build.php, EquipmentSlotsView |

### 2.3 The action system is ALREADY item-aware

Conditions: `RequiresAmmo` (consumes munition), `RequiresWeaponType`,
`RequiresWeaponCraftedWith`, `ForbidOnEquipedObjectStatus`.
Outcome instructions: `DamageObject` (chance-based instant break +
recipe elements back — a proto-usure), `DropWeapon` (disarm),
`Enchant`, `Object` (steal/give by id), `ObjectEffect`, `Resource`
(gathering into inventory).

### 2.4 Page-coded flows (NOT actions today)

- `InventoryService::useItem` — equip/unequip (1 Ae) OR consume
  (item's `spell` column casts/grants the linked spell).
- `build.php` — items with `subtype` build **dumb map_walls** from
  inventory (1 A, coords picked on a mini-board).
- drop / pickup, bank store/withdraw, craft, market asks/bids,
  merchant, player exchanges.

---

## 3. Chosen Design

### 3.1 Decision summary

| Decision | Choice | Rejected alternative |
|---|---|---|
| Catalog | `items` stays THE catalog (like `races` for entities), + `items.kind`: `stackable` \| `instance` | every item a players row (259 planches = 259 entities) |
| Per-item state | new `item_instances` satellite table | new columns on players_items (stacks can't carry instance state) |
| Fungibles | stacks stay exactly as they are | instancing everything |
| Map presence of a singular item | `UniqueObject` whose satellite references `item_instance_id` | separate "ground instance" system |
| Containers (chests) | a UniqueObject already CAN own `players_items` rows (players.id FK) — zero schema | dedicated container tables |
| Item stats | migrate `datas/*/items/*.json` → items table columns + admin page (own sub-chantier, same move as races) | keep JSON |

### 3.2 Schema

```sql
CREATE TABLE item_instances (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  item_id        INT NOT NULL,              -- FK items.id (the catalog kind)
  durability     INT NOT NULL DEFAULT 100,
  durability_max INT NOT NULL DEFAULT 100,
  quality        INT NOT NULL DEFAULT 0,
  custom_name    VARCHAR(255) NOT NULL DEFAULT '',
  params         LONGTEXT NULL,             -- enchantments, provenance…
  created_at     INT NOT NULL,
  CONSTRAINT fk_item_instances_item FOREIGN KEY (item_id) REFERENCES items (id)
);
```

Ownership/location of an instance is exactly ONE of:
- `players_items_instances (player_id, instance_id, equiped)` — in a bag
  or worn (replaces the stack row for instance-kind items);
- referenced by a `unique_objects.item_instance_id` — on the map;
- referenced by a bank variant — stored.

(The exact carried/bank/map linkage tables are Phase 1 detail — the
invariant is: an instance has one location, enforced by service.)

### 3.3 `UniqueObject` bridge

`unique_objects` gains `item_instance_id INT NULL`: a dropped legendary
sword or a displayed artifact is a UniqueObject (position, observable,
attackable, TargetType 'structure') wrapping the instance. *Ramasser*
destroys the UniqueObject row and puts the instance back in the
inventory — identity survives the round trip. Ground STACKS of
fungibles stay `map_items` untouched.

### 3.4 Usure lands here

`durability` decrement evolves the existing `DamageObject` instruction
(chance-based instant break → gradual wear, break at 0 keeps the
recipe-elements-back behavior). Repair of ITEMS is a durability
mutation (merchant service or action with a `RequiresItem` material
cost) — distinct from structure repair (PV heal), same philosophy:
distinct actions, shared machinery.

---

## 4. Verification — every current interaction stays expressible

Checked against the code 2026-07-16 ("everything we do with items must
become doable with the new system, through the action system"):

| Interaction | Today | New system |
|---|---|---|
| Munitions à l'arc | `RequiresAmmo` | unchanged (stacks) |
| Garde d'arme | `RequiresWeaponType` / `CraftedWith` | reads instance → catalog |
| Casse d'objet | `DamageObject` (instant) | durability decrement on instance (§3.4) |
| Désarmement | `DropWeapon` | dropped instance keeps identity (→ UniqueObject) |
| Enchantement | `Enchant` (catalog-level flag!) | on the INSTANCE — fixes the flag-on-catalog wart |
| Vol d'objet | `Object` (steal) | transfers stack or instance ref |
| Récolte | `Resource` (fouiller) | unchanged (stacks) |
| Stats d'équipement en combat | `applyItemCaracs` (caracs pipeline) | instance → catalog caracs; durability can modulate later |
| Consommer (potion → sort) | page-coded `useItem` | *consommer* action — needs **G1** |
| Construire depuis un objet | page-coded `build.php` → dumb `map_walls` | *construire* action — needs **G1 + G2**; built palissades become real Buildings (PV, attaquables, réparables) |
| Ramasser / déposer | page-coded | stacks: map_items; instance: UniqueObject round trip; *ramasser* = action TargetType ['structure'] |
| Équiper / déséquiper (1 Ae) | `useItem` / `Player::equip` | stays a service (inventory management), operates on instance refs |
| Banque, craft, marché, marchand, échanges | pages/services | transfers of stacks or instance refs; craft of an instance-kind outputs a fresh full-durability instance |
| Objets maudits | checks in drop/equip | same checks, service-level |

**Gaps to add (both generic, each serving several features):**

- **G1 — `RequiresItem` condition** (+ consume-on-pay cost):
  generalizes `RequiresAmmo` to any catalog item. Serves potions,
  build material costs, repair material costs.
- **G2 — `PlaceStructure` outcome instruction**: places a Building /
  UniqueObject via BuildingService from action parameters. Serves the
  *construire* action (replacing build.php's wall placement) and any
  future summon/deploy action.

No blocker found: 8 of ~15 interactions are already data-driven actions
and port mechanically; the rest becomes expressible with G1+G2 or stays
legitimately UI.

---

## 5. Incremental Roadmap

Each step releasable; `staging` shippable at every commit.

- **Phase 0 — golden masters** (this MR): pin stack arithmetic
  (`add_item`/`get_n`), equip/unequip + `applyItemCaracs` effect on
  caracs, `give_item` transfer. Guards every later step.
- **Phase 1 — instances**: `items.kind` + `item_instances` +
  `ItemInstanceService`; equipment references instances; data
  migration converts currently-EQUIPPED rows only (unequipped stacks
  convert lazily on first touch). The risky one: the 13 `equiped`
  readers + inventory JS. ~1–2 weeks of PR-sized steps.
  **⚠ needs review before code: the equipped-rows conversion is the
  one hard-to-reverse data migration.**
- **Phase 2 — usure**: durability on instances + `DamageObject`
  evolution + G1 for repair costs. Small once Phase 1 exists.
- **Phase 3 — map bridge**: G2, UniqueObject ⇄ instance round trip,
  *construire*/*ramasser* actions, chests (UI only). build.php retires.
- **Opportunistic — items JSON→DB**: item stats into columns + admin
  page (same seed/export pattern as races). Independent, any time.

---

## 6. Open Questions

1. **Conversion policy**: equipped-only at migration + lazy on touch —
   or bulk-convert every instance-kind stack? (Lazy keeps the migration
   tiny; bulk is simpler to reason about.)
2. **Durability semantics**: wear on use (per attack?), on time (cron?),
   on damage taken? Interacts with the global usure design.
3. **Catalog flags** `enchanted` / `cursed` / `vorpal`: move to
   instance params, keep at catalog for inherently-cursed kinds, or
   both?
4. **Which kinds become `instance`**: weapons + armor only at first?
   Trophies? Quest items?
5. **Market/exchanges of instances**: sellable? (an ask/bid references
   a specific instance, not a quantity) — or instance-kind items are
   simply not marketable at first?
