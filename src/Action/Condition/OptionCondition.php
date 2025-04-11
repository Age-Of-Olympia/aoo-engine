<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;

class OptionCondition extends BaseCondition
{
    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition): ConditionResult
    {
        $result = new ConditionResult(true, array(), array());

        $params = $condition->getParameters();
        $option = $params["option"]??"";

        if($target->have_option($option)){
            //devrait être un switch avec les options possibles
            $errorMessage[0] = "Ce personnage n\'autorise pas les entraînements.";
            return new ConditionResult(false, array(), $errorMessage);
        }

        return $result;
    }

}
