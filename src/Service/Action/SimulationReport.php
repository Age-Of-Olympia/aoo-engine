<?php

namespace App\Service\Action;

use App\Action\ActionResults;

/**
 * Aggregate of N simulation runs (random rolls) plus one full sample run for
 * the detailed view.
 */
final class SimulationReport
{
    /**
     * @param list<array{sides: int, faces: list<int>}> $sampleRolls dice rolled during the sample run
     */
    public function __construct(
        public readonly int $runs,
        public readonly int $successCount,
        public readonly int $hitCount,
        public readonly float $averageDamageOnHit,
        public readonly ?ActionResults $sample,
        public readonly array $sampleRolls = [],
    ) {
    }

    public function successRate(): float
    {
        return $this->runs > 0 ? $this->successCount / $this->runs : 0.0;
    }

    public function hitRate(): float
    {
        return $this->runs > 0 ? $this->hitCount / $this->runs : 0.0;
    }
}
