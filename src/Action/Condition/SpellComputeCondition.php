<?php
namespace App\Action\Condition;

class SpellComputeCondition extends ComputeCondition
{
    protected string $throwName = "sort";

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
    
    protected function getDistanceMalus(): int {
        $distanceMalus = 0;
        if($this->distance > 2){
            $distanceMalus = ($this->distance - 2) * 3;
        }
        return $distanceMalus;
    }
}