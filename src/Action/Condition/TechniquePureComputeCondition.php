<?php
namespace App\Action\Condition;


class TechniquePureComputeCondition extends ComputePureCondition
{
    protected string $throwName = "La technique";

    protected function getDistanceTreshold(ConditionObject $conditionObject) : int {
        $bonusTreshold = 0;
        foreach ($conditionObject->getActorPassives() as $actorPassive) {
            $passiveName = $actorPassive->getName();

            if($passiveName == "retrait"){
                $bonusTreshold += (int) $actorPassive->getValue();
            }
        }
        
        return (4 * ($this->distance - 1) - $bonusTreshold);
    }

    protected function checkDistanceCondition(int $actorTotal, ConditionObject $conditionObject): bool {
        $checkAboveDistance = true;
        if($this->distance > 1){
            $distanceTreshold = $this->getDistanceTreshold($conditionObject);
            $checkAboveDistance = $actorTotal >= $distanceTreshold;
        }
        return $checkAboveDistance;
    }
    
}