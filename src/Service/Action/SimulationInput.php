<?php

namespace App\Service\Action;

/**
 * Hypothetical actor/target state for a simulation. Anything not provided
 * defaults to a clean character. Built from the form fields that
 * SimulationFormBuilder derived for the action.
 */
final class SimulationInput
{
    /**
     * @param array<string, int> $actorCaracs      trait => value
     * @param array<string, int> $targetCaracs
     * @param array<string, int> $actorRemaining   pa/pv/pm/mvt/... => value
     * @param array<string, int> $targetRemaining
     * @param array<string, int> $actorEffects      effect name => value
     * @param array<string, int> $targetEffects
     * @param list<string> $actorPassives
     * @param list<string> $targetPassives
     */
    public function __construct(
        public readonly array $actorCaracs = [],
        public readonly array $targetCaracs = [],
        public readonly array $actorRemaining = [],
        public readonly array $targetRemaining = [],
        public readonly int $distance = 1,
        public readonly ?string $actorWeapon = null,
        public readonly ?string $targetWeapon = null,
        public readonly array $actorEffects = [],
        public readonly array $targetEffects = [],
        public readonly array $actorPassives = [],
        public readonly array $targetPassives = [],
        // Environment toggles so the global / condition preconditions can be
        // actually exercised in a simulation.
        public readonly string $plan = 'gaia',
        public readonly bool $actorBerserk = false,
    ) {
    }
}
