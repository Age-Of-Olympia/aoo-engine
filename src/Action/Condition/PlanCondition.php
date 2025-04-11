<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;

class PlanCondition extends BaseCondition
{
    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition): ConditionResult
    {
        $result = new ConditionResult(true, array(), array());

        $condition->setBlocking(true);

        $params = $condition->getParameters();
        $plan = $params["plan"]??"enfers";

        if($actor->coords->plan == $plan){
            if ($plan == 'enfers') {
                $errorMessage[0] = 'Impossible d\'agir aux Enfers.';
            } else {
                $errorMessage[0] = 'Impossible d\'agir sur ce plan : ' + $plan;
            }
            
            $result = new ConditionResult(false, array(), $errorMessage);
        }

        return $result;
    }

}
