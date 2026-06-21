<?php
namespace App\Action\Condition;

use Classes\Dice;

class TechniquePureComputeCondition extends ComputePureCondition
{
    protected string $throwName = "La technique";

    public function __construct(?Dice $dice = null)
    {
        parent::__construct($dice);
        array_push($this->preConditions, new ObstacleCondition());
    }

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