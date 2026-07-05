<?php

namespace App\Enum;

/**
 * Who an action outcome can be applied to: the actor (sur soi), another player
 * (sur la cible), or either (sur soi ou la cible). Stored per outcome
 * (action_outcomes.apply_to) and edited in the action workbench; the derived
 * action scope in {@see \App\Service\Action\ActionTargeting} aggregates it.
 *
 * This is targeting/display metadata only: at execution time the instructions'
 * own "player" parameter decides who is mutated, and a self-cast simply passes
 * the actor as the target.
 */
enum OutcomeTarget: string
{
    case Self = 'self';
    case Target = 'target';
    case Both = 'both';

    public function appliesToSelf(): bool
    {
        return $this !== self::Target;
    }

    public function appliesToTarget(): bool
    {
        return $this !== self::Self;
    }
}
