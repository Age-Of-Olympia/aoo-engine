<?php

namespace App\Action\Combat;

use Classes\Dice;

class CombatResolver
{
    private ?Dice $dice;

    public function __construct(?Dice $dice = null)
    {
        $this->dice = $dice;
    }

    /**
     * @return array<int, int>
     */
    public function roll(int $traitValue, bool $advantage = false, bool $disadvantage = false): array
    {
        $dice = $this->dice ?? new Dice(3);
        $roll = $dice->roll($traitValue);

        if (($advantage || $disadvantage) && !($advantage && $disadvantage)) {
            $secondRoll = $dice->roll($traitValue);
            $roll = $advantage ? max($roll, $secondRoll) : min($roll, $secondRoll);
        }

        return $roll;
    }

    public function resolve(int $actorTotal, int $targetTotal, bool $reachedTarget = true): OpposedRollResult
    {
        $hit = $reachedTarget && $actorTotal >= $targetTotal;

        return new OpposedRollResult($actorTotal, $targetTotal, $hit, $reachedTarget);
    }
}
