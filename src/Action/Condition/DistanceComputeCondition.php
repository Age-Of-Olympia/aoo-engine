<?php
namespace App\Action\Condition;

use Classes\View;

class DistanceComputeCondition extends ComputeCondition
{
    public function __construct()
    {
        parent::__construct();
        array_push($this->preConditions, new ObstacleCondition());
    }

    protected function getDistanceTreshold() : int {
        return floor(($this->distance) * 2.5);
    }

    protected function computeTarget($target, $dice)
    {
        $trait1 = $target->caracs->cc;
        $trait2 = $target->caracs->agi;
        $targetRollTraitValue = floor(max(3/4 * $trait1 + 1/4 * $trait2, 1/4 * $trait1 + 3/4 * $trait2));
        $targetRoll = $dice->roll($targetRollTraitValue);
        $targetTotal = array_sum($targetRoll) - $target->data->malus;
        $malusTxt = ($target->data->malus != 0) ? ' - '. $target->data->malus .' (Malus)' : '';
        $targetTotalTxt = $target->data->malus ? ' = '. $targetTotal : '';
        $targetTxt = 'Jet '. $target->data->name .' = '. array_sum($targetRoll) . $malusTxt . $targetTotalTxt;

        return array($targetRoll, $targetTotal, $targetTxt);
    }

    protected function checkDistanceCondition(int $actorTotal): bool {
        $checkAboveDistance = true;
        if($this->distance > 1){
            $distanceTreshold = $this->getDistanceTreshold($this->distance);
            $checkAboveDistance = $actorTotal >= $distanceTreshold;
        }
        return $checkAboveDistance;
    }
    
    protected function getDistanceMalus(): int {
        $distanceMalus = 0;
        if($this->distance > 2){
            $distanceMalus = ($this->distance - 2) * 3;
        }
        return $distanceMalus;
    }
}