<?php

namespace App\Service\Action;

/**
 * The game's max-energie rule, for the simulator's defaults. At a turn refresh
 * the engine sets energie to ENERGIE_CST − a (action points) — see
 * NewTurnView — so a fighter's real maximum energie depends on how many actions
 * they have. The simulator defaults the energie field to that real max instead
 * of a flat 100.
 */
final class EnergieRule
{
    /** The common action-point count (6 is rare). */
    public const DEFAULT_ACTION_POINTS = 3;

    /** Max energie for a fighter with the given action points: ENERGIE_CST − a. */
    public static function maxFor(int $actionPoints): int
    {
        $cst = defined('ENERGIE_CST') ? ENERGIE_CST : 7;

        return max(0, $cst - $actionPoints);
    }
}
