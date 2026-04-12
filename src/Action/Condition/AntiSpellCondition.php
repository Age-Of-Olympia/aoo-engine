<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Action\Condition\ConditionObject;

class AntiSpellCondition extends BaseCondition
{
    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        $preConditionResult = parent::check($actor, $target, $condition, $conditionObject);
        if (!$preConditionResult->isSuccess()) {
            return $preConditionResult;
        }
        
        $result = new ConditionResult(true, array(), array());
        $errorMessages = array();
        $blocked = false;
        foreach(ITEM_EMPLACEMENT_FORMAT as $emp){
            if(!empty($actor->emplacements->{$emp})){
                if(!empty($actor->emplacements->{$emp}->data->spellMalus)){
                    $blocked = true;
                    $errorMessages[sizeof($errorMessages)] = $actor->emplacements->{$emp}->data->name .' empêche la magie.';
                }
            }
        }

        if($blocked){
            $condition->setBlocking(true);
            return new ConditionResult(false, array(), $errorMessages);
        }

        return $result;
    }

}
