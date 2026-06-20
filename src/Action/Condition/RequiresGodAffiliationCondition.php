<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Action\Condition\ConditionObject;
use App\Action\Schema\HasParameterSchema;
use App\Action\Schema\ParameterSchema;

//add enum to display correctly the weapon type names (melee, distance, multipurpose, etc)

class RequiresGodAffiliationCondition extends BaseCondition implements HasParameterSchema
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema();
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {

        $result = new ConditionResult(true, array(), array());
        
        if(!$actor->data->godId){
            $errorMessages[0] = 'Vos prières ne servent à rien, car vous ne vénérez aucun Dieu !';
            $result = new ConditionResult(false, array(), $errorMessages);
        }

        return $result;
    }
}
