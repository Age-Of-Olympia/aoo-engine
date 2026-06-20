<?php

namespace App\Action\Schema;

/**
 * One input the simulator needs for an action, declared by the condition /
 * outcome that reads it (see DeclaresSimulationInputs). SimulationFormBuilder
 * unions these across an action's conditions + outcomes to render the form.
 */
final class SimulationField
{
    public const KIND_TRAIT = 'trait';
    public const KIND_DISTANCE = 'distance';
    public const KIND_WEAPON = 'weapon';
    public const KIND_REMAINING = 'remaining';

    public const SIDE_ACTOR = 'actor';
    public const SIDE_TARGET = 'target';
    public const SIDE_SHARED = 'shared';

    public function __construct(
        public readonly string $kind,
        public readonly string $side,
        public readonly string $key,
        public readonly string $label,
        public readonly ?string $default = null,
    ) {
    }

    /** Stable identity for de-duplication across declarations. */
    public function id(): string
    {
        return $this->side . '|' . $this->kind . '|' . $this->key;
    }
}
