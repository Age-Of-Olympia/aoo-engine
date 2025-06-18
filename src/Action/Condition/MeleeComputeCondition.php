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
        $targetTotal = array_sum($targetRoll) - $target->data->malus;
        $malusTxt = ($target->data->malus != 0) ? ' - '. $target->data->malus .' (Malus)' : '';
        $targetTotalTxt = $target->data->malus ? ' = '. $targetTotal : '';
        $targetTxt = 'Jet '. $target->data->name .' = '. array_sum($targetRoll) . $malusTxt . $targetTotalTxt;

        return array($targetRoll, $targetTotal, $targetTxt);
    }
}