<?php

namespace App\Service\Action;

use App\Action\BuffAction;
use App\Entity\Action;

/**
 * Single source of truth for an action's targeting scope — who it can be used
 * on: only the actor (SELF), only another player (TARGET), both (BOTH), or
 * NONE (not usable on anyone via the observe panel).
 *
 * There is no stored scope column; the rule is derived (and was previously
 * inlined in observe.php's button-rendering loop): a BuffAction is always SELF,
 * otherwise the scope aggregates its outcomes' applyToSelf flag — an "sur soi"
 * outcome makes it usable on self, a normal outcome on a target, both kinds make
 * it usable on both. An action with NO outcomes (and not a BuffAction) is NONE —
 * the old loop rendered no button for it (e.g. technique / spell-modifier actions
 * that only tweak a later attack), so it must not surface on a target.
 *
 * Extracted so observe.php and the simulator agree on who an action targets
 * instead of each re-deriving it.
 */
final class ActionTargeting
{
    public const SELF = 'self';
    public const TARGET = 'target';
    public const BOTH = 'both';
    public const NONE = 'none';

    public function scopeOf(Action $action): string
    {
        if ($action instanceof BuffAction) {
            return self::SELF;
        }

        $hasSelf = false;
        $hasTarget = false;
        foreach ($action->getOutcomes() as $outcome) {
            if ($outcome->getApplyToSelf()) {
                $hasSelf = true;
            } else {
                $hasTarget = true;
            }
        }

        if ($hasSelf && $hasTarget) {
            return self::BOTH;
        }
        if ($hasSelf) {
            return self::SELF;
        }
        if ($hasTarget) {
            return self::TARGET;
        }

        return self::NONE;
    }

    /** True when the action can be used on the actor themselves. */
    public function canTargetSelf(Action $action): bool
    {
        $scope = $this->scopeOf($action);

        return $scope === self::SELF || $scope === self::BOTH;
    }

    /** True when the action can be used on another player. */
    public function canTargetOther(Action $action): bool
    {
        $scope = $this->scopeOf($action);

        return $scope === self::TARGET || $scope === self::BOTH;
    }

    /** Friendly French label for the config UI. */
    public function label(Action $action): string
    {
        return match ($this->scopeOf($action)) {
            self::SELF => 'sur soi',
            self::TARGET => 'sur une cible',
            self::BOTH => 'sur soi ou une cible',
            default => 'sans cible',
        };
    }
}
