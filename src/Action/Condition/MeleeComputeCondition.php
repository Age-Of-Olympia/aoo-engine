<?php
namespace App\Action\Condition;

use App\Action\Combat\CombatResolver;
use App\Action\Combat\RollDetail;
use App\Action\Combat\RollDetailView;
use App\Interface\DeclaresSimulationInputsInterface;

class MeleeComputeCondition extends ComputeCondition implements DeclaresSimulationInputsInterface
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
        // Modificateurs du jet de défense portés par les effets (catalogue).
        $mods = (new \App\Service\EffectService())->modifierContributions($target->getEffects(), 'getRollDefenseMod');
        $esquive = (int) ($target->caracs->esquive ?? 0);
        $malus = (int) $target->data->malus;
        $total = array_sum($targetRoll->roll) - $malus + $bonus + $mods['pos'] - $mods['neg'] + $esquive;

        $detail = new RollDetail(
            name: $target->data->name,
            rollSum: array_sum($targetRoll->roll),
            bonus: $bonus,
            positiveEffect: $mods['pos'],
            negativeEffect: $mods['neg'],
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