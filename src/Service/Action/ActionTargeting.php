<?php

namespace App\Service\Action;

use App\Action\BuffAction;
use App\Action\HealAction;
use App\Entity\Action;

/**
 * Single source of truth for an action's targeting scope — who it can be used
 * on: only the actor (SELF), only another player (TARGET), both (BOTH), or
 * NONE (not usable on anyone via the observe panel).
 *
 * There is no stored scope column; the rule is derived. A pure buff is always
 * SELF (its stored applyToSelf flag is unreliable). Otherwise the scope aggregates
 * the SUCCESS outcomes' applyToSelf flag — a "sur soi" success outcome makes it
 * usable on self, a normal one on a target, both kinds on both. Failure outcomes
 * are ignored: a jump attack that hurts the caster only when it MISSES is still a
 * target action. An action with no success outcomes (and not a buff) is NONE — it
 * surfaces no button (e.g. a technique / spell-modifier that only tweaks a later
 * attack), so it must not appear on a target.
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
        // A pure buff acts on the caster, and its outcome flag is not a reliable
        // target signal (many self-buffs are stored with apply_to_self = 0), so it
        // stays SELF. A heal is a BuffAction subclass but genuinely targets — a
        // cleric heals an ally, a self-heal heals the caster — so it falls through
        // to the success-outcome derivation below.
        if ($action instanceof BuffAction && !$action instanceof HealAction) {
            return self::SELF;
        }

        $hasSelf = false;
        $hasTarget = false;
        foreach ($action->getOutcomes() as $outcome) {
            // Only success outcomes say who the action is aimed at. A failure
            // outcome that lands on the caster (e.g. self-damage on a missed jump
            // attack) must not make the action usable on yourself.
            if (!$outcome->isOnSuccess()) {
                continue;
            }
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

        // No success outcomes: a heal with none still acts on the caster; anything
        // else is a no-target modifier (no observe button).
        return $action instanceof BuffAction ? self::SELF : self::NONE;
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
