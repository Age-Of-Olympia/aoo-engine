# Handoff — one life system for entities and items

Paste this into a fresh session. Everything below is **verified**, not assumed:
don't re-derive it, but do re-check anything you intend to change.

## The mission, in order

1. **Give items a life that works off the board.** An item's life must be usable
   whether it lies on the ground or sits in a bag. Today it only works on the
   ground, and only by accident.
2. **Then containers: life and death of entities.** A chest that is smashed must
   die like a player dies — spilling its contents — and a chest holding anything
   must not be pickable.
3. **Then finish the job**: `reparer` targeting an item, and its cost (how much
   is repaired per action). The cost was explicitly deferred by the user.

## The core problem, stated precisely

Two lives exist, with the same shape (current + max) and no shared contract:

| | current | max | where |
|---|---|---|---|
| entity | `players.malus` (damage taken) | `caracs->pv`, from its `races` type | `players` |
| item | `item_instances.durability` | `item_instances.durability_max` | `item_instances` |

`reparer` heals via `HealingOutcomeInstruction`, which does
`$target->putBonus(['pv' => $n])` and is typed `Classes\Player`. Every entity is
a `players` row, so all entities fit. **An item in a bag has no `players` row**,
so it cannot be a target — that is the whole blocker.

**The user's decision (do not re-litigate):** the answer is a shared *contract*
(interface/trait), like the existing `Harvestable` + `HarvestableFields` pattern
in `src/Entity/`. "Having a life" must be orthogonal to "being on the map".

**Recommended shape** (agreed in discussion, not yet built): an interface with
`getLife / getMaxLife / restore / damage`; `Classes\Player` implements it over
`pv`/`malus`; an item-instance adapter implements it over `durability`. Then
**only `HealingOutcomeInstruction` widens its parameter type** — PHP allows a
child to widen (contravariance), so the other 27 outcome instructions are
untouched. Do **not** move storage into a shared table: that would touch ~149
call sites (82 `malus`, 67 `durability`), with combat and wear in the blast
radius, and no player would see a difference.

`ActorInterface` is **not** the right contract — it demands `equip()`,
`getMunition()`, effects. An item would need ~15 lying stubs.

## Verified facts

- `players.coords_id` is **NOT NULL** (default 0); `entity_cells.coords_id` is
  NOT NULL. **An entity row means occupying a tile.** That is why an item in a
  bag has no entity row, and why `UniqueObjectService::takeInstance()` deletes
  the entity rows on pickup — it removes a *presence*, not an object.
- Identity survives pickup: `custom_name`, `creator_id`, `created_at`,
  `quality`, `durability` all live on `item_instances`.
- A dropped object **is** an entity (`player_type = 'unique'`), bridged to its
  instance through the `unique_objects` table. Its entity is a **temporary
  shell**. Life must therefore be read on the instance, which outlives it —
  the code already syncs *entity destroyed → durability 0*, never the reverse.
- `UniqueObjectService` writes `race = 'objet'`, and **no `races` row named
  `objet` exists** → an object entity has no type and no PV. Any guard that
  consults the type catalogue must special-case `player_type === 'unique'`.
- `BuildingService::deleteEntityRows()` deletes `players_items` for the entity.
  A chest owns `players_items` rows (documented container design), so
  **picking up or destroying a full chest destroys its contents today.**
  Unconfirmed in play only because no unique object exists on the dev DB.
- `takeInstance` has exactly **one** caller: `TakeItemOutcomeInstruction`.
- `Player::death()` = spill loot → go to hell (`plan 'enfers'`) → purge malus,
  effects, bonuses. Only the spill is reusable; the rest is character-only.
- `docs/design-items-instances.md` **rejected** items-as-entities, but its
  stated reason was *stacks* ("259 planches = 259 entities"). Individual
  instances are already one row each, so that argument does not settle the
  case — this is open design space, not a closed door.

## Decisions already taken by the user

- **Repairable**: what someone *erected* — buildings and decor. Not resources,
  not plants. Any type can override its family in either direction.
- **Objects**: an object whose durability is below max is repairable, **wherever
  it is** (ground, bag, anywhere). Location is not the criterion.
- **Broken is terminal**: `durability <= 0` (`brisé`) cannot be repaired.
- **A container holding anything cannot be picked up.** Generic rule, not
  chest-specific — "for now we think of chests, but later why not something
  else".
- **Smashing a chest = killing it**: it loots its contents like a player who
  dies.

## Open questions — ask, do not decide

- **How the player designates an item** to repair (inventory panel? item card?).
  Board actions target a tile; a bagged sword has no tile.
- **`UniqueObjectService::destroyToGround()`'s docblock contradicts the rule**:
  it says the instance falls at durability 0 and *"reste réparable"*. Under
  "broken is terminal", smashing an object makes it scrap forever. The user has
  since said smashing a chest should loot it — reconcile explicitly.
- **Repair cost** (point 2 of the original ask): today `reparer` costs 1 action
  point and grants 3 XP flat, regardless of target or damage.

## Git state

- Merged to `integration/hud-redesign`: **!846** (a failed deploy now fails the
  job), **!847** (charset-proof joins + temp table), **!848** (database default
  utf8mb4), **!849** (board/panel truth after the entity conversion).
- **!850 open, 4 commits** — branch `feat/on-ne-repare-que-ce-qui-se-repare`:
  repairability on the type, `RequiresRepairableTarget` condition,
  `RequiresDamagedTarget` reading an object's durability with both bounds,
  admin tri-state select. PHPStan clean, 1303 tests green.
- **Branch `refactor/le-butin-quitte-la-mort`** (`dd7f33b6`), pushed, **no MR**:
  extracts the loot spill out of `Player::death()` into
  `src/Service/LootSpillService.php`. Behaviour unchanged, golden masters pass.
  This is the piece a chest needs to die like a player — build step 2 on it.

## Working conventions

- Run everything in the **`PHP-AOO4-Local`** container:
  `make phpstan` and `./vendor/bin/phpunit` from `/var/www/html`.
- Verify over real HTTP when a bug is user-visible: `curl -d "name=Cradek&psw=test"
  http://localhost/login.php`, then POST `observe.php`. Restore any dev data you
  move, and say so.
- Commits and MR descriptions in **French**, Conventional Commits, **no AI
  trailer**. Comments explain the algorithm; the story goes in the commit.
- MRs target `integration/hud-redesign`. Never merge before checking the
  pipeline is green **on the branch head SHA** — `glab ci status` has lied
  before. FF-only: rebase via the GitLab API when GitLab says `need_rebase`.
- Never commit or merge without the user asking.
