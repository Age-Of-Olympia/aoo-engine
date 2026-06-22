<?php

namespace App\Service\Action;

use App\Action\BuffAction;
use App\Entity\Action;

/**
 * Single source of truth for an action's targeting scope — who it can be used
 * on: only the actor (SELF), only another player (TARGET), or both (BOTH).
 *
 * There is no stored scope column; the rule is derived (and was previously
 * inlined in observe.php's button-rendering loop): a BuffAction is always SELF,
 * otherwise the scope aggregates its outcomes' applyToSelf flag — an "sur soi"
 * outcome makes it usable on self, a normal outcome on a target, and having both
 * kinds makes it usable on both. An action with no outcomes defaults to TARGET.
 *
 * Extracted so observe.php and the simulator agree on who an action targets
 * instead of each re-deriving it.
 */
final class ActionTargeting
{
    public const SELF = 'self';
    public const TARGET = 'target';
    public const BOTH = 'both';

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

        return $hasSelf ? self::SELF : self::TARGET;
    }

    /** True when the action can be used on the actor themselves. */
    public function canTargetSelf(Action $action): bool
    {
        return $this->scopeOf($action) !== self::TARGET;
    }

    /** True when the action can be used on another player. */
    public function canTargetOther(Action $action): bool
    {
        return $this->scopeOf($action) !== self::SELF;
    }

    /** Friendly French label for the config UI. */
    public function label(Action $action): string
    {
        return match ($this->scopeOf($action)) {
            self::SELF => 'sur soi',
            self::TARGET => 'sur une cible',
            default => 'sur soi ou une cible',
        };
    }
}
