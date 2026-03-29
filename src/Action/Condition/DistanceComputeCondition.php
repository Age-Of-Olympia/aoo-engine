<?php
namespace App\Action\Condition;

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

    protected function computeTarget($target, $dice, $conditionObject)
    {
        $trait1 = $target->caracs->cc;
        $trait2 = $target->caracs->agi;
        $targetRollTraitValue = floor(max(3/4 * $trait1 + 1/4 * $trait2, 1/4 * $trait1 + 3/4 * $trait2));
        $targetRoll = $dice->roll($targetRollTraitValue);
        if($conditionObject->getTargetAdvantage() === true && $conditionObject->getTargetDisadvantage() === true){
            // Do nothing if advantage and disadvantage
        }
        elseif($conditionObject->getTargetAdvantage() === true || $conditionObject->getTargetDisadvantage() === true){
            $targetRoll2 = $dice->roll($targetRollTraitValue);
            if($conditionObject->getTargetAdvantage() === true){
                $targetRoll = max($targetRoll,$targetRoll2);
            }   
            else{
                $targetRoll = min($targetRoll,$targetRoll2);
            }
        }
        $targetTotal = array_sum($targetRoll) - $target->data->malus;
        $malusTxt = ($target->data->malus != 0) ? ' - '. $target->data->malus .' (Malus)' : '';
        $targetTotalTxt = $target->data->malus ? ' = '. $targetTotal : '';
        $targetTxt = 'Jet '. $target->data->name .' = '. array_sum($targetRoll) . $malusTxt . $targetTotalTxt;

        $conditionObject->setTargetRoll($targetTotal);
        
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