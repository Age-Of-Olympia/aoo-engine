<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;

class MeleePureComputeCondition extends ComputePureCondition
{
    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        $result = parent::check($actor, $target, $condition, $conditionObject);

        return $this->appendJumpDistanceResult($result, $actor, $target, $condition);
    }
}
