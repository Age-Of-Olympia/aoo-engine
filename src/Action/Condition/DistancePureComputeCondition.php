<?php
namespace App\Action\Condition;

use Classes\View;

class DistancePureComputeCondition extends ComputePureCondition
{
    public function __construct()
    {
        parent::__construct();
        array_push($this->preConditions, new ObstacleCondition());
    }

    protected function getDistanceTreshold() : int {
        return floor(($this->distance) * 2.5);
    }

    protected function computeTarget($target, $dice, $targetRollBonus)
    {
        $trait1 = $target->caracs->cc;
        $trait2 = $target->caracs->agi;
        $targetRollTraitValue = floor(max(3/4 * $trait1 + 1/4 * $trait2, 1/4 * $trait1 + 3/4 * $trait2));
        $targetRoll = $dice->roll($targetRollTraitValue);
        $targetTotal = array_sum($targetRoll);
        $targetTxt = 'Jet '. $target->data->name .' = '. array_sum($targetRoll) . ' (Jet pur)';

        return array($targetRoll, $targetTotal, $targetTxt);
    }

    protected function checkDistanceCondition(int $actorTotal): bool {
        $checkAboveDistance = true;
        if($this->distance > 1){
            $distanceTreshold = $this->getDistanceTreshold();
            $checkAboveDistance = $actorTotal >= $distanceTreshold;
        }
        return $checkAboveDistance;
    }
    
    protected function getDistanceMalus(): int {
        $distanceMalus = 0;
        $cellCount = $this->distance - 1;
        if($cellCount > 2){
            $distanceMalus = ($cellCount - 2) * 3;
        }
        return $distanceMalus;
    }
}