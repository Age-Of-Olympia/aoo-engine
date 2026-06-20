<?php

namespace App\Action\Combat;

final class DamageModifiers
{
    public function __construct(
        public readonly int $bonusDamages,
        public readonly int $othersDamages,
        public readonly int $agressivite,
        public readonly int $faiblesse,
        public readonly int $bonusDefense,
        public readonly int $othersDefense,
        public readonly int $armure,
        public readonly int $fragilite,
    ) {
    }
}
