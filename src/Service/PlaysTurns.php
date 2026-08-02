<?php

namespace App\Service;

/**
 * Who owns a turn and a progression of its own.
 *
 * Two answers, not one, and the second is the whole point of the playable
 * buildings work (docs/design-playable-buildings.md):
 *
 * - a **character** plays by nature, whatever its race. The hidden system races
 *   — `ame`, `dieu`, `animal` — carry `playable = 0` and still take turns, so
 *   the type's flag cannot stand alone here;
 * - anything whose **type declares itself playable** plays too. That is how a
 *   building opts in: `races.playable` on a building type, driven through
 *   faction access rather than registered as (§3.6).
 *
 * The predicate lives here rather than inline because the two services that
 * seed satellites and the migrations that backfill them must agree word for
 * word; when they drifted, an entity silently got one satellite and not the
 * other. It expects `players` aliased `p` and `races` left-joined as `r`.
 */
final class PlaysTurns
{
    /** Discriminators that play by nature, whatever their type says. */
    public const CHARACTER_TYPES = ['real', 'tutorial', 'npc'];

    public const SQL_PREDICATE =
        "(p.player_type IN ('real', 'tutorial', 'npc')
          OR p.player_type IS NULL
          OR r.playable = 1)";
}
