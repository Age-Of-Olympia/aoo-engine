# Roads as entities — plan

Draft, 2026-08-31. Prerequisite for road decay
([design-decay-structures.md](design-decay-structures.md), step 4).
**Nothing built. Adjust before I start.**

## First: the running bonus was lost — fixed

**Confirmed in play.** Laying a road granted no `+1 (route)`.

**Why.** `courir` asks `map_routes`, which only the map editor writes. A player
crafts a `route`, places it, and the placement action installs an exemplar on
the cell instead — a road nothing recognised. The producer changed when the
action moved to `placestructure`; `courir` was never told.

**Fix** (step 0, shippable alone): `RoadService::hasRoadAt()` asks both
sources, and `courir` asks it. A road item is recognised by
`items.subtype = 'routes'`, the vocabulary the workbench field already
documents — and one that holds for the several types to come.

`PlantsService` has the same blind spot: it reads `map_routes` to keep plants
off roads, so a plant can still sprout on a player-laid one. Left alone here on
purpose — it changes behaviour rather than restoring it, and step 4 moves that
reader anyway.

When roads become entities, `RoadService` is the only class that changes.

## What roads become

A road becomes an entity like a resource or a building: a `players` row with
`player_type = 'route'`, its cells in `entity_cells`, its type a `races` row of
kind `structure` (nature: something walkable, blocking nothing).

That buys the things a layer cannot have: PV, repair, observation, an owner —
and decay.

## Steps

0. **Restore the running bonus.** — **done**
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
