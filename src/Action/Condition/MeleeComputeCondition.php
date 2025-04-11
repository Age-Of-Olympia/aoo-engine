<?php
namespace App\Action\Condition;

class MeleeComputeCondition extends ComputeCondition
{
  protected function computeTarget($target, $dice)
    {
        $option1 = $target->caracs->cc;
        $option2 = $target->caracs->agi;
        $targetRollTraitValue = max($option1, $option2);
        $targetRoll = $dice->roll($targetRollTraitValue);
        $targetFat = floor($target->data->fatigue / FAT_EVERY);
        $targetTotal = array_sum($targetRoll) - $targetFat - $target->data->malus;
        $malusTxt = ($target->data->malus != 0) ? ' - '. $target->data->malus .' (Malus)' : '';
        $targetFatTxt = ($targetFat != 0) ? ' - '. $targetFat .' (Fatigue)' : '';
        $targetTotalTxt = ($targetFat || $target->data->malus) ? ' = '. $targetTotal : '';
        $targetTxt = 'Jet '. $target->data->name .' = '. array_sum($targetRoll) . $malusTxt . $targetFatTxt . $targetTotalTxt;

        return array($targetRoll, $targetTotal, $targetTxt);
    }
}