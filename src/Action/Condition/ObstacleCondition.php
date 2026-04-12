<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Action\Condition\ConditionObject;
use Classes\View;

class ObstacleCondition extends BaseCondition
{
    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        $result = new ConditionResult(true, array(), array());

        View::get_walls_between($actor->coords, $target->coords);

        return $result;
    }

}
