<?php

namespace App\Action\Combat;

/**
 * The structured breakdown of one side's opposed roll: the dice sum plus every
 * modifier that fed the total. Produced by the compute conditions (the math)
 * and rendered to a tooltip by RollDetailView (the display) — keeping the two
 * concerns out of the same method.
 */
final class RollDetail
{
    public function __construct(
        public readonly string $name,
        public readonly int $rollSum,
        public readonly int $bonus = 0,
        public readonly int $positiveEffect = 0,
        public readonly int $negativeEffect = 0,
        public readonly int $distanceMalus = 0,
        public readonly int $malus = 0,
        public readonly int $esquive = 0,
        public readonly int $total = 0,
    ) {
    }
}
