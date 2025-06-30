<?php
namespace App\Action\Condition;

class MeleePureComputeCondition extends ComputeCondition
{
  protected function computeActor($actor, $dice, $actorRollBonus)
    {
        $actorRollTraitValue = $actor->caracs->{$this->actorRollTrait};
        $actorRoll = $dice->roll($actorRollTraitValue);
        $actorTotal = array_sum($actorRoll);
        $distanceMalus = $this->getDistanceMalus();
        $distanceMalusTxt = ($distanceMalus) ? ' - '. $distanceMalus .' (Distance)' : '';
        $actorTotal = $actorTotal - $distanceMalus;
        $actorTotalTxt = ($distanceMalus) ? ' = '. $actorTotal . ' (Jet pur)' : ' (Jet pur)';
        $actorTxt = 'Jet '. $actor->data->name .' = '. implode(' + ', $actorRoll) .' = '. array_sum($actorRoll) . $distanceMalusTxt . $actorTotalTxt;

        return array($actorRoll, $actorTotal, $actorTxt);
    }

  protected function computeTarget($target, $dice, $targetRollBonus)
    {
        $option1 = $target->caracs->cc;
        $option2 = $target->caracs->agi;
        $targetRollTraitValue = max($option1, $option2);
        $targetRoll = $dice->roll($targetRollTraitValue);
        $targetTotal = array_sum($targetRoll);
        $targetTxt = 'Jet '. $target->data->name .' = '. array_sum($targetRoll);

        return array($targetRoll, $targetTotal, $targetTxt);
    }
}