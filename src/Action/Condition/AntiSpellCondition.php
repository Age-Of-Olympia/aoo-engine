<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Action\Condition\ConditionObject;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterSchema;
use App\Service\EquipmentSlotService;

class AntiSpellCondition extends BaseCondition implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema();
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        
        $result = new ConditionResult(true, array(), array());
        $errorMessages = array();
        $blocked = false;
        foreach(EquipmentSlotService::wornItems($actor->getEmplacements()) as $item){
            if(!empty($item->data->spellMalus)){
                $blocked = true;
                $errorMessages[sizeof($errorMessages)] = $item->data->name .' empêche la magie.';
            }
        }

        if($blocked){
            // Refusal: its config row is blocking, so nothing is paid.
            return new ConditionResult(false, array(), $errorMessages);
        }

        return $result;
    }

}
