<?php
namespace App\Action\Condition;

use App\Action\Combat\CombatResolver;
use App\Action\Combat\RollDetail;
use App\Action\Combat\RollDetailView;
use App\Action\Schema\DeclaresSimulationInputs;

class MeleeComputeCondition extends ComputeCondition implements DeclaresSimulationInputs
{
    public static function targetDefenseValue(int $cc, int $agi): int
    {
        return max($cc, $agi);
    }

    public static function simulationInputs(array $params): array
    {
        return self::physicalDefenseSimulationInputs($params);
    }

  protected function computeTarget($target, $dice, $conditionObject)
    {
        $option1 = $target->caracs->cc;
        $option2 = $target->caracs->agi;
        $targetRollTraitValue = self::targetDefenseValue((int) $option1, (int) $option2);
        $targetRoll = (new CombatResolver($dice))->rollDetailed(
            (int) $targetRollTraitValue,
            (bool) $conditionObject->getTargetAdvantage(),
            (bool) $conditionObject->getTargetDisadvantage()
        );
        $bonus = (int) $conditionObject->getTargetRollBonus();
        $protection = (int) ($target->getEffectValue("protection") ?: 0);
        $vulnerabilite = (int) ($target->getEffectValue("vulnerabilite") ?: 0);
        $esquive = (int) ($target->caracs->esquive ?? 0);
        $malus = (int) $target->data->malus;
        $total = array_sum($targetRoll->roll) - $malus + $bonus + $protection - $vulnerabilite + $esquive;

        $detail = new RollDetail(
            name: $target->data->name,
            rollSum: array_sum($targetRoll->roll),
            bonus: $bonus,
            positiveEffect: $protection,
            negativeEffect: $vulnerabilite,
            malus: $malus,
            esquive: $esquive,
            total: $total,
            advantage: $targetRoll,
        );

        $conditionObject->setTargetRoll($total);

        return array($targetRoll->roll, $total, (new RollDetailView())->renderTarget($detail));
    }

    protected function getDistanceMalus(): int {
        $distanceMalus = 0;
        $cellCount = $this->distance - 1;
        if($cellCount > 1){
            $distanceMalus = ($cellCount - 1) * 4;
        }
        return $distanceMalus;
    }
}