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
        return $this->rollDetailed($traitValue, $advantage, $disadvantage)->roll;
    }

    /** roll() en exposant le détail avantage/désavantage pour les logs. */
    public function rollDetailed(int $traitValue, bool $advantage = false, bool $disadvantage = false): AdvantageRoll
    {
        $dice = $this->dice ?? new Dice(3);
        $roll = $dice->roll($traitValue);

        if (($advantage || $disadvantage) && !($advantage && $disadvantage)) {
            $secondRoll = $dice->roll($traitValue);
            $sum1 = array_sum($roll);
            $sum2 = array_sum($secondRoll);

            if ($advantage) {
                $kept = ($sum1 >= $sum2) ? $roll : $secondRoll;
                $mode = AdvantageRoll::MODE_ADVANTAGE;
            } else {
                $kept = ($sum1 <= $sum2) ? $roll : $secondRoll;
                $mode = AdvantageRoll::MODE_DISADVANTAGE;
            }

            return new AdvantageRoll(
                $kept,
                $mode,
                array_sum($kept),
                array_sum($kept) === $sum1 ? $sum2 : $sum1,
            );
        }

        return new AdvantageRoll($roll, null, array_sum($roll), null);
    }

    public function resolve(int $actorTotal, int $targetTotal, bool $reachedTarget = true): OpposedRollResult
    {
        $hit = $reachedTarget && $actorTotal >= $targetTotal;

        return new OpposedRollResult($actorTotal, $targetTotal, $hit, $reachedTarget);
    }
}
