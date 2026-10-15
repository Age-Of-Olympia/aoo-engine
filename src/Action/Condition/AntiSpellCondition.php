<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Action\Condition\ConditionObject;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterSchema;

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
        $seen = [];
        foreach(ITEM_EMPLACEMENT_FORMAT as $emp){
            if(!empty($actor->emplacements->{$emp})){
                $item = $actor->emplacements->{$emp};
                $identity = spl_object_id($item);
                if (isset($seen[$identity])) {
                    continue;
                }
                $seen[$identity] = true;

                if(!empty($item->data->spellMalus)){
                    $blocked = true;
                    $errorMessages[sizeof($errorMessages)] = $item->data->name .' empêche la magie.';
                }
            }
        }

        if($blocked){
            // Refusal: its config row is blocking, so nothing is paid.
            return new ConditionResult(false, array(), $errorMessages);
        }

        return $result;
    }

}
