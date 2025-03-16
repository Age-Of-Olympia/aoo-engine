<?php
namespace App\Action\Condition;

class TechniqueComputeCondition extends ComputeCondition
{
    protected string $throwName = "La technique";

    protected function getDistanceTreshold() : int {
        return floor(($this->distance) * 2.5);
    }

    protected function checkDistanceCondition(int $actorTotal): bool {
        $checkAboveDistance = true;
        if($this->distance > 1){
            $distanceTreshold = 4 * ($this->distance - 1);
            $checkAboveDistance = $actorTotal >= $distanceTreshold;
        }
        return $checkAboveDistance;
    }
    
}