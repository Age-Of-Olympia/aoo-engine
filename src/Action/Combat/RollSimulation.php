<?php

namespace App\Action\Combat;

final class RollSimulation
{
    public function __construct(
        public readonly string $actorTrait,
        public readonly int $actorTraitValue,
        public readonly int $actorRoll,
        public readonly int $actorBonus,
        public readonly int $distanceMalus,
        public readonly int $actorTotal,
        public readonly string $targetTrait,
        public readonly int $targetTraitValue,
        public readonly string $targetFormula,
        public readonly int $targetRoll,
        public readonly int $targetBonus,
        public readonly int $targetTotal,
        public readonly int $distanceThreshold,
        public readonly bool $reachedThreshold,
        public readonly bool $hit,
    ) {
    }
}
