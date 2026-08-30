# Roads as entities — plan

Draft, 2026-08-31. Prerequisite for road decay
([design-decay-structures.md](design-decay-structures.md), step 4).
**Nothing built. Adjust before I start.**

## First: laying a road stopped making a road — fixed

**Confirmed in play.** A road placed from the inventory displayed wrong and
granted no `+1 (route)`. A road drawn in Tiled works.

**Why.** Everything that knows about roads reads `map_routes`: the running
bonus (`courir` → `TileTypeOutcomeInstruction` → `MapService`), the drawn map,
`observe`, and the rule keeping plants off roads. When the placement action
moved to `placestructure`, road items followed the object path with it and got
INSTALLED on the cell — so the board drew an object where a road should lie,
and no reader of roads saw one.

`PlaceLayerOutcomeInstruction` had said what a road is all along: *une route se
marche, elle n'occupe pas la case.*

**Fix** (step 0): `GroundLayerService` holds the layer write, both placement
instructions use it, and a catalogue item whose `subtype` names a layer
(`routes`) is laid as ground instead of installed as a thing. The migration
marks the road items and **rescues the roads already laid the wrong way** —
each becomes a `map_routes` row on its cell, keeping its builder.

`scripts/actions/courir.php` went with it: dead since `courir` became an
action, and it still carried the old road bonus, which made it look like the
live path.

## What roads become

A road becomes an entity like a resource or a building: a `players` row with
`player_type = 'route'`, its cells in `entity_cells`, its type a `races` row of
kind `structure` (nature: something walkable, blocking nothing).

That buys the things a layer cannot have: PV, repair, observation, an owner —
and decay.

## Steps

0. **Roads are ground again.** — **done**
1. **Point the producer at entities**: placing a `route` mints a road entity
   instead of an installed exemplar.
2. **Pseudo-races for road types**, a family rather than one row — several
   kinds are planned. Template: the walls migration.
3. **Convert the rows.** Each `map_routes` line becomes an entity; `player_id`
   becomes the owner, `NULL` for the editor's.
4. **The six readers.** `courir` (does this cell carry a road?), `observe`,
   `PlantsService`, `Classes/View.php`, and the two `scripts/tiled/` endpoints.
5. **Rendering.** Smaller than I first said: `generateResourceLayer` already
   reads entities and keeps its GD layer, so `generateRoutesLayer` copies it —
   swap `map_routes` for `players JOIN entity_cells WHERE player_type = 'route'`.
6. **Tiled keeps its palette, the server changes what it stores.** The editor
   goes on sending a routes layer; the endpoint that receives it mints road
   entities instead of `map_routes` rows, the way it already handles buildings
   in `erase_case.php`. Nothing changes for whoever draws maps, no extension
   release — and `map_routes` can then be dropped, since nothing writes it any
   more.
7. **Repair** accepts roads, like buildings.
8. **Decay**, then three lines: `enrol()` when a player lays one,
   `touchAndHeal()` on the step in `go.php`. Both already written and tested.

## Two details that will bite

**`Classes/View.php` draws the board from a UNION** where each layer carries a
depth (`tableOrder`): roads 97.6, players 98. If roads become `players` rows,
the branch at 98 would draw them a second time, at the wrong depth. Roads keep
their own branch, filtered on `player_type = 'route'`, and the players branch
excludes it — the same treatment scenery already gets.

**A road is walkable.** Structures block passage by default; a road must not.
`races.blocks_passage = 0`, and the cell role has to be the one that lets a
player stand there — the same one plants use.

## Decisions taken

- **Several road types**, so the migration creates a family rather than one
  row: each type carries its own PV and its own decay rate, like walls.
- **Tiled keeps its routes palette.** Map authors see no change. The server
  stores what the editor sends as entities rather than as `map_routes` rows,
  so the table goes away without the editor knowing.
- **A road is repairable by hand**, on top of healing when walked on. It joins
  the repair action like a building.
- **One road per cell.** A player cannot lay a road where one already lies,
  editor's or not — so an editor road can never be turned into a perishable one
  by covering it.

## Still open

Nothing blocking. Steps 1 to 8 wait on a decision to start them.
