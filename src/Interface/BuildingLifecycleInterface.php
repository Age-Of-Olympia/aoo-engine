<?php

namespace App\Interface;

/**
 * Behavior specific to a building type when it is destroyed in game
 * (fell). Resolved by type via
 * BuildingLifecycleRegistry — the lifecycle counterpart of the actions'
 * ConditionRegistry: a type without behavior simply has no registry
 * entry.
 */
interface BuildingLifecycleInterface
{
    /** The building has just been destroyed in game (death path). */
    public function fell(int $buildingId, string $plan, string $faction): void;
}
