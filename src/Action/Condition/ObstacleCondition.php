<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use View;

class ObstacleCondition extends BaseCondition
{
    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition): ConditionResult
    {
        $result = new ConditionResult(true, array(), array());

        $params = $condition->getParameters();
        $display = $params["display"]??false;

        if ($display) {
            View::get_walls_between($actor->coords, $target->coords);
            $successMessages = array();
            $successMessages[0] = "Aucun obstacle ne gène votre attaque !";
            $result = new ConditionResult(true, $successMessages, array());
        } else {
            View::get_walls_between($actor->coords, $target->coords);
        } 

        return $result;
    }

}
