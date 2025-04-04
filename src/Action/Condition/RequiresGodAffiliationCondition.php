<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;

//add enum to display correctly the weapon type names (melee, distance, multipurpose, etc)

class RequiresGodAffiliationCondition extends BaseCondition
{
    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition): ConditionResult
    {

        $result = new ConditionResult(true, array(), array());
        
        if(!$actor->data->godId){
            $errorMessages[0] = 'Vos prières ne servent à rien, car vous ne vénérez aucun Dieu !';
            $result = new ConditionResult(false, array(), $errorMessages);
        }

        return $result;
    }
}
