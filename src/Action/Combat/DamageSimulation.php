<?php

namespace App\Action\Combat;

final class DamageSimulation
{
    public function __construct(
        public readonly int $actorDamages,
        public readonly int $targetDefense,
        public readonly int $additionalDamages,
        public readonly int $total,
    ) {
    }
}
