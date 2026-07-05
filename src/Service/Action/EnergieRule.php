<?php

namespace App\Service\Action;

/**
 * The game's max-energie rule: at a turn refresh a player's energie is set to
 * ENERGIE_CST − a (action points), so the real maximum depends on how many
 * actions they have. Single source of truth — the turn engine (NewTurnView)
 * and the workbench simulator (SimulationInputMapper) both resolve energie
 * through here rather than each spelling out the formula.
 */
final class EnergieRule
{
    /** The common action-point count (6 is rare). */
    public const DEFAULT_ACTION_POINTS = 3;

    /** The maximum energie a fighter with the given action points can have: ENERGIE_CST − a. */
    public static function maxEnergieFor(int $actionPoints): int
    {
        $cst = defined('ENERGIE_CST') ? ENERGIE_CST : 7;

        return max(0, $cst - $actionPoints);
    }
}
