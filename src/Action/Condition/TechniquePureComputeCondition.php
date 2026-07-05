<?php
namespace App\Action\Condition;


class TechniquePureComputeCondition extends ComputePureCondition
{
    protected string $throwName = "La technique";

    protected function getDistanceTreshold() : int {
        return (4 * ($this->distance - 1));
    }

    protected function checkDistanceCondition(int $actorTotal): bool {
        $checkAboveDistance = true;
        if($this->distance > 1){
            $distanceTreshold = $this->getDistanceTreshold();
            $checkAboveDistance = $actorTotal >= $distanceTreshold;
        }
        return $checkAboveDistance;
    }
    
}