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
| Catalog | `items` stays THE catalog (like `races` for entities) | every item a players row (259 planches = 259 entities) |
| Per-item state | new `item_instances` satellite table | new columns on players_items (stacks can't carry instance state) |
| Which items can instance | **ALL of them** (décision 2026-07-17) — no `kind` column: an instance is BORN FROM A STATE EVENT (craft with name, equip, enchant/alter, wear, map placement). Pristine quantities stay stack rows; gold never experiences those events so never instances | a hard stackable/instance split per catalog kind |
| Fungibles | stacks stay exactly as they are until an event promotes a unit | instancing everything |
| Map presence of a singular item | `UniqueObject` whose satellite references `item_instance_id` | separate "ground instance" system |
| Containers (chests) | a UniqueObject already CAN own `players_items` rows (players.id FK) — contents unrestricted: stacks AND instances (décision 2026-07-17) | dedicated container tables |
| Market | asks/bids get a nullable `instance_id`: sell a stock of wood OR a specific sword (décision 2026-07-17) | instances non-marketable |
| Item stats | migrate `datas/*/items/*.json` → items table columns + admin page (own sub-chantier, same move as races) | keep JSON |

### 3.2 Schema

```sql
CREATE TABLE item_instances (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  item_id        INT NOT NULL,              -- FK items.id (the catalog kind)
  durability     INT NOT NULL DEFAULT 100,  -- 0 = brisé (réparable), < 0 = détruit
  durability_max INT NOT NULL DEFAULT 100,  -- altérations move it (Béni +50, Maudit -50)
  quality        INT NOT NULL DEFAULT 0,
  custom_name    VARCHAR(255) NOT NULL DEFAULT '',  -- set at creation ONLY (service rule)
  params         LONGTEXT NULL,             -- altérations/enchantments (Enflammé, Béni…)
  creator_id     INT NULL,                  -- FK players.id, provenance (not displayed)
  created_at     INT NOT NULL,
  destroyed      TINYINT(1) NOT NULL DEFAULT 0,  -- soft-delete: history survives
  CONSTRAINT fk_item_instances_item FOREIGN KEY (item_id) REFERENCES items (id)
);
```

Durability thresholds carry the whole state story (équipe review
2026-07): `0` = brisé (repairable), `< 0` = détruit — no separate state
column. Destruction is a soft-delete (`destroyed` flag), keeping
creator/date traceability; a thrown weapon that vanishes is the same
flag through a consume-on-use action cost.

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

### 3.4 Usure lands here — wear model (decided 2026-07-17)

**Per-object triggers, per-turn decay.** Each catalog item declares
which events arm its wear and how fast it wears (columns shipped with
the items JSON→DB move):

- `wear_triggers` — set of {`attack`, `defense`, `move`, `usage`}:
  a sword arms on attack, an armor when its wearer takes a hit, boots
  on movement, a tool on usage. Empty = never wears (gold, trophies).
- `wear_rate` — durability points lost **per turn** in which at least
  one armed trigger fired.

Everything about wear is CONFIGURABLE per object and defaults to « ne
s'use pas » (`wear_rate` 0, no triggers) : the engine ships inert, and
WHICH objects wear at WHAT rate is admin/balance content tuned later —
deliberately not decided now (décision 2026-07-17).

**The unit of decay is the TURN**: events during a turn only FLAG the
instance (`worn_this_turn` / last-armed timestamp); the decrement is
applied once, at new-turn processing — the same pass that refreshes
PA/MVT. Ten attacks in one turn wear the sword once. This keeps wear
predictable, cheap (one pass), and turn-native like everything else in
AoO. The new-turn recap logs it (« Votre gladius s'use (−2) »).

A wear trigger on a still-stacked unit promotes it first (§5c) — in
practice equip already promoted weapons/armor, movement wear concerns
equipped boots, usage promotes tools on first use.

`DamageObject` (crits, corruption) stays as bigger, immediate
decrements on top. Repair of ITEMS is a durability mutation (merchant
service or action with a `RequiresItem` material cost) — distinct from
structure repair (PV heal), same philosophy: distinct actions, shared
machinery.

### 3.5 HUD figuration of wear

Where the player sees durability, consistent with the paper & ink HUD:

- **Bandeau d'équipement** (EquipmentSlotsView, top bar + fiche): a
  thin gauge under each equipped item's icon — same visual language as
  the existing `.hud-xp-progress` bar; neutral ≥ 50 %, encre orangée
  < 50 %, rouge < 20 %.
- **Brisé (0)**: icon grayed with a fissure overlay + « brisé » badge;
  a broken item stops contributing its caracs (applyItemCaracs skips
  it) — that IS the gameplay meaning of brisé.
- **Inventaire**: identical pristine units stay one stacked line;
  instances with differing wear render as separate lines each with its
  gauge (the team's schema) — grouping key = catalogue + empreinte
  d'état (§5b). Tooltip shows « durabilité 37/100 » + altérations.
- **Nouveau tour**: wear applied that turn is listed in the recap /
  events feed, so decay is visible when it happens, not discovered.
- **Objets uniques posés** (map): the selection zone already shows a
  state line for structures; a wrapped instance shows its gauge there.

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
- **Phase 1 — instances** (policy DECIDED 2026-07-17: lazy promotion,
  see §5c): `item_instances` + `ItemInstanceService`; equipment
  references instances; the data migration converts currently-EQUIPPED
  rows only. The risky one: the 13 `equiped` readers + inventory JS.
  ~1–2 weeks of PR-sized steps.
- **Phase 2 — usure**: durability on instances + `DamageObject`
  evolution + G1 for repair costs. Small once Phase 1 exists.
- **Phase 3 — map bridge**: G2, UniqueObject ⇄ instance round trip,
  *construire*/*ramasser* actions, chests (UI only). build.php retires.
- **Opportunistic — items JSON→DB**: item stats into columns + admin
  page (same seed/export pattern as races). Independent, any time.

---

## 5b. Requirements traceability — team review (Discord, 2026-07)

The team's object requirements, checked against this design:

| Demande | Verdict |
|---|---|
| Nom custom à la création uniquement (Labrys → « Dette de Thétis ») | ✅ `custom_name`, exposed by the service only at creation |
| Tracer créateur + date, non affiché | ✅ `creator_id` + `created_at` |
| Usure = PV qui montent/descendent | ✅ `durability` / `durability_max` |
| 0 = brisé (réparable), négatif = détruit — pas de colonne d'état | ✅ adopted (§3.2) |
| Destruction définitive après action (arme de jet) | ✅ consume-on-use via G1; soft-delete (`destroyed`) per the team's « on garde tout à -1 » |
| Altérations (Enflammé, Gelé, Béni +50, Maudit −50…) | ✅ `params` + **G3**: generalize the existing `Enchant` instruction into `AlterInstance` (data-driven action piece) |
| Stacker les uniques identiques, différencier par usure (schéma de l'équipe) | ✅ display concern: inventory groups instances by (catalogue + empreinte d'état) — no schema impact |
| Deux tables, templates + instances ; « pas les PO » | ✅ literally §3.1 — `items` catalog + `item_instances`; gold stays `kind = stackable` |
| Emplacements (main att/def, 2 mains, tête, torse, cape, pieds, doigt, munition) | ✅ ITEM_EMPLACEMENT_FORMAT has 14 slots (main1/main2/deuxmains…) ; renames = config |
| Type d'objet (mêlée, tir, distance, jet, bouclier, consommable, anneau, **mur, décoration, route**) | ✅ catalog column (items JSON→DB); mur/décoration/route are build.php's subtypes — G2 turns them into real placements |
| Encombrement/poids, taux de drop | ✅ catalog columns; enforcement mechanics to write (model-compatible) |
| Non stockable en banque | ✅ exists today (`items.is_bankable`) |
| Coffre : stocker, **casser**, ouvrir avec clé | ✅ chest = UniqueObject → owns `players_items` (zero schema) and is attackable **today by construction**; « ouvrir avec clé » = action TargetType ['structure'] + G1 `RequiresItem {clé}` |
| Coffre PORTABLE (instance contenant des instances) | ⚠ the one genuinely open design — not blocked, but needs its own decision (open question #6) |

## 5c. Migration policy — lazy promotion (decided 2026-07-17)

### The two candidate policies

**A — bulk conversion**: one migration splits every stack of every
item into N instance rows.

Problems that killed it:
- **Volume & lock time**: stacks reach 259; prod would create thousands
  of rows in one transaction on the hottest inventory table.
- **Big-bang switch**: all 31 raw `players_items` SQL statements, the
  views and the inventory JS must understand instances on day one —
  the exact opposite of the strangler rule (staging shippable at every
  commit).
- **No rollback**: once players have played, instance states diverge
  and re-aggregating into stacks loses data. A bug found after one day
  in prod cannot be rolled back cleanly.

**B — lazy promotion (CHOSEN)**: nothing converts up front except
currently-equipped rows. An instance is created only by a
state-changing event:

- craft (creator, date, custom name at creation),
- equip (the item starts existing individually — it will wear),
- enchant / altération,
- placement on the map (UniqueObject),
- wear applied by an action.

A pristine unit in a stack has, by definition, no state to lose —
promotion is exact, and demotion (rollback) is possible as long as the
instance state equals pristine.

### Known problems of the chosen policy — and their mitigations

| # | Problem | Mitigation |
|---|---|---|
| P1 | **The `equiped`-on-stack wart**: today `equiped` sits ON the stack row — `(n=3, equiped='main1')` is legal, the whole stack is "equipped". The migration must SPLIT: 1 instance (equipped) + n−1 stack | one-shot in the Phase 1 migration; golden masters already pin `get_n(equiped)` behavior |
| P2 | **Dual representation lives for a long time**: every read path must see stacks AND instance links, or counts go wrong (`get_n`, craft ingredients, market quantities) | funnel reads through `Item`/`ItemInstanceService` shims FIRST (query-gateway step), before any promotion exists; golden masters guard the totals |
| P3 | **Promotion atomicity**: splitting a stack + creating the instance must be one transaction or a crash duplicates/loses a unit | `add_item` already runs transactions; promotion goes through the same `Db` transaction helper |
| P4 | **Dual identifiers in the UI**: views/JS pass `item_id` today; instance paths need `instance_id`. Mixing them up equips/sells the wrong object | explicit separate parameter (`instanceId`), never overloading `itemId`; server rejects ambiguous requests |
| P5 | **"Give me 1 gladius" ambiguity**: when a player owns 2 worn instances + a stack, transfers/sales must pick WHICH unit | rule: stack units first (pristine), instances only when explicitly selected (P4's `instanceId`) — matches the market decision (stock OR specific sword) |
| P6 | **Bank/exchange/steal flows** move rows between tables; instance links must move with the same guarantees | the location invariant (§3.2: an instance has exactly ONE location) is enforced in ONE service; flows call it instead of writing SQL |

## 6. Open Questions

1. ~~**Conversion policy**~~ RESOLVED (2026-07-17): lazy promotion —
   §5c.
2. ~~**Durability semantics**~~ FULLY RESOLVED (2026-07-17): thresholds
   (0 = brisé, < 0 = détruit) + per-object triggers
   (attack/defense/move/usage, settable per catalog item) + **the turn
   as the unit of decay** — see §3.4, HUD figuration §3.5.
3. ~~**Catalog flags**~~ RESOLVED (2026-07-17): **both** — catalog
   flags remain for inherently-cursed/enchanted KINDS; instance params
   carry per-instance altérations.
4. ~~**Which kinds become instance**~~ RESOLVED (2026-07-17): **all**
   items may instance; there is no kind gate — the state EVENT creates
   the instance (§3.1).
5. ~~**Market of instances**~~ RESOLVED (2026-07-17): **both** — asks/
   bids get a nullable `instance_id`; sell a stock of wood or one
   specific sword.
6. ~~**Containers**~~ RESOLVED (2026-07-17): a chest holds
   **anything** — stacks and instances alike (contents unrestricted).
   Portability of the chest itself stays map/bank-anchored for now.
