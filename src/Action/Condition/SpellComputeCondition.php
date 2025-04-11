<?php
namespace App\Action\Condition;

class SpellComputeCondition extends ComputeCondition
{
    protected string $throwName = "Le sort";

    public function __construct()
    {
        parent::__construct();
        array_push($this->preConditions, new ObstacleCondition());
        array_push($this->preConditions, new AntiSpellCondition());
    }

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