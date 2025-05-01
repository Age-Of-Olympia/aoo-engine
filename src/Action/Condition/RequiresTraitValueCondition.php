<?php

namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;

class RequiresTraitValueCondition extends BaseCondition
{
    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition): ConditionResult
    {
        $result = new ConditionResult(true, array(), array());
        $params = $condition->getParameters(); // e.g. { "a": 1, "pm": 10 }

        $details = array();
        $costIsAffordable = true;
        foreach ($params as $key => $value) {
            if ($key == "uses_fatigue") {
                continue;
            }
            if ($key == "fatigue") {

                if ($value == "both") {
                    $errorMessage = array();
                    if($target->data->fatigue >= FAT_EVERY){
                        $errorMessage[sizeof($errorMessage)] = "Votre partenaire est trop fatigué pour s'entraîner.";
                    }
            
                    if($actor->data->fatigue >= FAT_EVERY){
                        $errorMessage[sizeof($errorMessage)] = "Vous êtes trop fatigué pour vous entraîner.";
                    }
                    if (sizeof($errorMessage) > 0) {
                        return new ConditionResult(false, array(), $errorMessage);
                    }
                }
            } else if ($actor->getRemaining($key) < $value) {
                array_push($details, "Pas assez de ".CARACS[$key]);
                $costIsAffordable = false;
            }
        }
        
        if (!$costIsAffordable) {
            $result = new ConditionResult(false, array(), $details);
        }

        return $result;
    }

    public function applyCosts(ActorInterface $actor, ?ActorInterface $target, ActionCondition $conditionToPay): array
    {
        $result = array();
        $parameters = $conditionToPay->getParameters();
        $fatigue = $parameters["uses_fatigue"] ?? true;
        foreach ($parameters as $key => $value) {
            if ($key == "fatigue" || $key == "uses_fatigue") {
                continue;
            }
            $actor->putBonus([$key => -$value], $fatigue);
            $text = "Vous avez dépensé " . $value . " " . CARACS[$key].".";
            array_push($result, $text);
        }
        return $result;
    }

    public function toRemove(): bool {
        return true;
    }
}