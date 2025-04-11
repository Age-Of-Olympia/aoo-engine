<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use View;

class ObstacleCondition extends BaseCondition
{
    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition): ConditionResult
    {
        $preConditionResult = parent::check($actor, $target, $condition);
        if (!$preConditionResult->isSuccess()) {
            $condition->setBlocking(true);
            return $preConditionResult;
        }

        $result = new ConditionResult(true, array(), array());

        View::get_walls_between($actor->coords, $target->coords);

        return $result;
    }

}
