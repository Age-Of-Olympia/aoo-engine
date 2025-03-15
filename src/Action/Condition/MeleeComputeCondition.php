<?php
namespace App\Action\Condition;

class MeleeComputeCondition extends ComputeCondition
{

    protected function getDistanceTreshold() : int {
        // return floor(($distance) * 2.5);
        return 0;
    }

    protected function checkDistanceCondition(int $actorTotal): bool {
        // $checkAboveDistance = true;
        // if($distance > 1){
        //     $distanceTreshold = $this->getDistanceTreshold($distance);
        //     $checkAboveDistance = $actorTotal >= $distanceTreshold;
        // }
        // return $checkAboveDistance;
        return true;
    }
    
    protected function getDistanceMalus(): int {
        // $distanceMalus = 0;
        // if($distance > 2){
        //     $distanceMalus = ($distance - 2) * 3;
        // }
        // return $distanceMalus;
        return 0;
    }
}