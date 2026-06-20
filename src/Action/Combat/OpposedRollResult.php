<?php

namespace App\Action\Combat;

final class OpposedRollResult
{
    public function __construct(
        public readonly int $actorTotal,
        public readonly int $targetTotal,
        public readonly bool $hit,
        public readonly bool $reachedTarget,
    ) {
    }
}
