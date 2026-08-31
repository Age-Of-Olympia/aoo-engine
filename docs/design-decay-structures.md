# Decay — player-built structures fall apart

Design note, 2026-08-30. Decisions taken with the lead; **nothing is
built**. Companions:
[design-buildings-entities.md](design-buildings-entities.md) (buildings
have PV), [design-items-instances.md](design-items-instances.md) §3.4
(item wear).

## Scope

What players build decays. What Tiled placed does not, unless someone
opts it in later.

## What decays, and what maintains it

| | maintained by | notes |
|---|---|---|
| buildings | being used — entered, acted in | |
| roads | being walked on — a step repairs | not before they are entities |
| walls | **nothing** — repair only | no gesture means "someone looks after this" |

Walls are the only ones that decay whatever their owner does. That is
intended: a rampart nobody maintains falls down.

## Decisions

1. Use POSTPONES decay for a configurable number of turns (**3 as a
   placeholder**); it does not heal. Repair is the remedy — the only one
   for walls. **Roads are the exception: a step heals them.**
2. At zero PV it is a destruction — `BuildingService::remove()`. Roads
   vanish.
3. Global defaults in `admin_settings`, overridable per type on `races`.
   **The grace value is not decided** — 3 turns is a placeholder.
4. Only player-built constructions are subject to the rule.
5. Structure types move to `spd` 16 — an 18 h turn, the players' rhythm.
   They sit at 2 and 0 today.
6. Roads become entities before they decay.
7. The faction page flags a construction below **75 %** of its life. A
   building attached to no faction warns nobody — deliberately.

## Model

```sql
CREATE TABLE entity_decay (
  player_id  INT PRIMARY KEY,
  decay_from INT NOT NULL,   -- decay is owed from this instant
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  KEY idx_due (decay_from)
);
```

**Having a row is the criterion.** What Tiled placed has no row and is
never read. Opting one in afterwards is one INSERT, which an admin screen
can offer per building.

**Where rows come from: `place(..., asConstructionSite: true)`.** That
flag already means "the player's *construire* gesture" — only
`PlaceStructureOutcomeInstruction` passes it; `admin/buildings-save.php`
and `buildingcmd` do not, and its docblock says so: *admin and editor
placements keep raising finished buildings*. One writer, at the one place
provenance is known.

The row is inserted at PLACEMENT, not at completion, because
`ConstructionSiteService::advance()` cannot tell how the entity was
placed. A site therefore carries its row while still being built, and is
kept out of the pass by `build_state = 'built'` — the same
`cs.player_id IS NULL` idiom `lineOfFireReport` already uses. That also
settles "do sites decay?": no, by exclusion rather than by a rule.

> `owner_id` cannot serve: `place()` sets it on every path, admin
> included, so it means "somebody owns this", not "a player built this".

**`decay_from` carries the grace.** `touch()` sets it to `now + grace`;
the pass advances it by the whole turns it applied; a wall is created
with `built_at + grace` and nothing ever pushes it.

> A separate last-used stamp would let `FLOOR((now - last_decay) / turn)`
> count turns during which the building was in use — six months of use
> becoming six months of decay the moment the grace lapses.

**`touch(player_id)`** — one `UPDATE entity_decay SET decay_from = :now +
:grace`, wired to building use (`BuildingService`) and road steps
(`go.php`). Never for a wall. A building used once per grace period is
never due, so the pass never reads it.

It POSTPONES; it does not heal. What decay already took stays until
someone repairs it, and a besieged tower is not mended by its owner
walking in and out. Roads alone also heal — see below.

### The pass

```sql
UPDATE players_bonus b
  JOIN players e      ON e.id = b.player_id
  JOIN races r        ON r.name = e.race
  JOIN entity_decay d ON d.player_id = e.id
   SET b.n = GREATEST(
               -r.pv,
               b.n - r.decay_rate
                   * FLOOR((:now - d.decay_from)
                           / (:base - (r.spd - :baseline) * :perPoint))
             ),
       d.decay_from = d.decay_from
                    + (:base - (r.spd - :baseline) * :perPoint)
                    * FLOOR((:now - d.decay_from)
                            / (:base - (r.spd - :baseline) * :perPoint))
 WHERE b.name = 'pv'
   AND r.decay_rate > 0
   AND NOT EXISTS (SELECT 1 FROM construction_sites cs WHERE cs.player_id = e.id)
   AND d.decay_from <= :now - (:base - (r.spd - :baseline) * :perPoint);
```

- **Every decay is materialised.** Stored PV is correct at all times; no
  reader learns a formula.
- **Turn length is per row**, from the type's `spd`. `:base`,
  `:baseline`, `:perPoint` are bound from `TurnScheduleService`
  constants.
- **`FLOOR` is catch-up**, normally 1. It covers missed runs and the
  backfill, and makes the pass idempotent.

Then one `SELECT` lists rows that reached zero — usually none — and PHP
loops over those alone to destroy them. That loop is the only one, and it
carries no ceiling: a cap looked like a safety valve and was a trap, since
what fell past it still decayed to zero, was never removed, and no later
run could see it again — the projection only looks at what is still
standing.

## Cost

| | |
|---|---|
| the pass | one indexed set-based UPDATE, whatever the world size |
| scenery | absent from the join, never read |
| buildings in use | not due, never read |
| PHP loop | collapses only, and no ceiling on them |
| cache | none — see below |

A structure's PV are read by `StructureSheetView`, `EntityCardView`,
`ContainerService` and `BuildingService::closureReasonOf`, all on demand;
**none by the map rendering**. So decay invalidates no per-player SVG
cache. Only a collapse changes the map, and it purges as removals already
do.

**Mobs will need more.** A mob's turn is behaviour, not arithmetic: it
cannot be caught up by multiplication. That needs scheduling (next
wakeup = next action) and interest management (simulate near players
only). Neither belongs here, and applying them to walls would cost
correctness for nothing.

**`TurnProcessingService::nextSlot()` catches up with a `while` loop**,
one iteration per missed turn. Harmless for players, load-bearing the day
a playable building goes untouched for months. It should be a modulo —
with the playable-buildings work, not here.

## Roads

**Walking on a road repairs it** — genuinely heals, where using a
building only postpones. `go.php` calls `touch()` on the road under the
player's feet: it pushes `decay_from` AND clears the `pv` deficit. A road
on a used path is always in good repair; one nobody takes disappears.

The asymmetry is deliberate and costs nothing extra: a road's only source
of damage is decay, so healing it in full needs no accounting of what
took what. The day something can deliberately damage a road, this has to
be revisited — a step should not undo sabotage.

At zero it vanishes outright — no ruin, no rubble, nothing to leave
behind.

**Not yet possible.** `map_routes` is `id, name, coords_id, player_id`:
no PV, so there is nothing to decrement. Roads become entities first
(step L3 of the resources→entities track); decay then needs no new
mechanism, and roads gain repair, observation and blocking rules with it.
`player_id` already records who laid one, so provenance survives the
move: the entified build path marks its rows the way `asConstructionSite`
does for buildings.

Until then roads do not decay, and the rule says what it does.

**Cost of touching on every step.** One indexed `UPDATE` per move onto a
road cell, on a path a player already writes to heavily. Acceptable as
is; if measurement disagrees, writing only when `decay_from` is more than
an hour from its target divides the traffic by a thousand without
changing behaviour.

## Consequences

**Neglect closes before it destroys.** `closureReasonOf` refuses the
counter of a building below `CLOSED_BELOW_PV_PCT` (**50 %**), and decay
writes the same `pv` deficit, so a neglected shop shuts on its own well
before collapsing.

The alert at 75 % therefore fires 25 points before the counter shuts:
a faction is warned while its building still works, and while a repair
still costs less than a rebuild. The two numbers belong together — moving
one without the other removes the margin.

**Warning channel: the faction page, and nothing else.** No missive.
`FactionService::buildingsOf()` already lists a faction's buildings with
their build state, so this is a column to add rather than a screen to
invent: remaining life, flagged **below 75 %**.

**A factionless building warns nobody, and that is the rule.** A faction
is a group of people; several pairs of eyes can notice a wall going soft
and say so. Build for yourself alone and nobody tells you — you do not
know what happens while you are away. Belonging buys watchers; solitude
does not.

It follows that a lone owner can find their shop closed by neglect
(above) with no notice anywhere. That is the cost of building alone, not
an oversight.

## Settings

Global defaults in `admin_settings`, per-type overrides on `races` —
the `harvest_default_pv` / `HarvestDefaultsService` pattern, with its own
admin screen in `admin/index.php`.

| global (`admin_settings`) | per type (`races`) | meaning |
|---|---|---|
| `decay_grace_turns` | `decay_grace` | turns without use before decay bites |
| `decay_rate_default` | `decay_rate` | PV lost per turn once it does |

**Read at use, not copied at creation** — the one place this departs from
`HarvestDefaultsService`, which copies its value into each new type
because a harvestable's PV is a starting stock and raising the default
must not re-heal standing trees. Decay grace is an ongoing rule, not a
stock: it is a dial the team will turn while watching players, and
turning it has to move the world.

In practice the setting is read at `touch()` and at placement, since
`decay_from` is `now + grace`. A new value therefore takes effect from
each construction's next use, progressively, with no data migration. A
wall keeps the value it was placed with — harmless, because its grace
elapses once and never returns.

Counted in turns, never in days. At `spd` 16 a turn is 18 h.

## Deferred on purpose

**The two default values.** Left undecided, deliberately: at 100 PV and
1/turn a wall would stand some 75 days past a 3-turn grace, but the
figures will be chosen once decay can be watched in play rather than
argued about now.

Deferring costs nothing here, which is why it is the right call: both are
`admin_settings` dials, so they ship as placeholders and are corrected
from the admin screen without a deployment, a migration or a developer.
Everything else in this note is settled.

## Slicing

0. Structure types to `spd` 16. — **done**
1. `entity_decay`, the two `admin_settings` dials and their `races`
   overrides, the pass — no caller. — **done**
2. The faction page column, remaining life flagged below 75 %. — **done**
3. The cron, the collapse loop, `touch()` on building use. — **done**
4. Roads, once they are entities: `touch()` on the step, and the heal.
   — **done**. Only a road a PLAYER lays enrols; the map editor uses the same
   gesture, so the caller says which it is.

Where the pieces live: `App\Service\Decay\StructureDecayService` (rule),
`DecayDefaultsService` (the two dials, edited from `admin/index.php`),
`scripts/crons/daily/15_decay_structures.php` (driver),
`BuildingService::place()` (enrolment),
`ActionExecutorService` (use), `FactionView::upkeepCellHtml()` (alert).

A road tells nobody it is rotting: the faction page lists buildings, and a
road is not one. Accepted — a road is re-walked rather than reported on.

Manual repair accepts roads without any work: `reparer` targets the
`structure` BRANCH, not a family.
