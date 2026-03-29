<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Action\Condition\ConditionObject;

class PlanCondition extends BaseCondition
{
    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        $result = new ConditionResult(true, array(), array());

        $params = $condition->getParameters();
        $plan = $params["plan"]??"enfers";

        if($actor->coords->plan == $plan){
            if ($plan == 'enfers') {
                $errorMessage[0] = 'Impossible d\'agir aux Enfers.';
            } else {
                $errorMessage[0] = 'Impossible d\'agir sur ce plan : ' + $plan;
            }
            
            $condition->setBlocking(true);
            $result = new ConditionResult(false, array(), $errorMessage);
        }

        return $result;
    }

}
