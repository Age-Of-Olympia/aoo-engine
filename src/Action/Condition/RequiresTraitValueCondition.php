<?php

namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;

class RequiresTraitValueCondition extends BaseCondition
{
    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition): ConditionResult
    {
        $preConditionResult = parent::check($actor, $target, $condition);

        if (!$preConditionResult->isSuccess()) {
            return $preConditionResult;
        }

        $result = new ConditionResult(true, array(), array());
        $params = $condition->getParameters(); // e.g. { "a": 1, "pm": 10 }

        $details = array();
        $costIsAffordable = true;

        foreach ($params as $key => $value) {
            if ($key == "energie") {
                if ($value == "both") {
                    $errorMessage = array();
                    if (sizeof($errorMessage) > 0) {
                        return new ConditionResult(false, array(), $errorMessage);
                    }
                }
            } 
            else if($key == "repos"){
                if (!$actor->have_effects_to_purge()) {
                    array_push($details, "Vous n'avez aucun effet à purger !");
                    $costIsAffordable = false;
                    continue;
                }
            }    
            else if(is_array($value)){
                $passives = $actor->getPassives($actor->getId());
                $defaultValue = 0;
                if(!empty($passives)){
                    foreach ($value as $item) {
                        foreach ($passives as $passive) {
                            if($passive->getName() == $item[0]){
                                if($actor->getRemaining($key) < ($item[1])){
                                    array_push($details, "Pas assez de ".CARACS[$key]);
                                    $costIsAffordable = false;
                                    break 3;
                                }
                                break 3;
                            }
                        }
                        if($item[0] == "none"){
                            $defaultValue = $item[1];
                        }
                    }  

                }
                if ($actor->getRemaining($key) < $defaultValue) {
                    array_push($details, "Pas assez de ".CARACS[$key]);
                    $costIsAffordable = false;
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
        foreach ($parameters as $key => $value) {
            if ($key == "energie") {
                continue;
            }
            if(is_array($value)){
                $passives = $actor->getPassives($actor->getId());
                $defaultValue = 0;
                if(!empty($passives)){
                    foreach ($value as $item) {
                        foreach ($passives as $passive) {
                            if($passive->getName() == $item[0]){
                                $actor->putBonus([$key => -$item[1]]);
                                $text = "Vous avez dépensé " . $item[1] . " " . CARACS[$key].".";
                                array_push($result, $text);
                                break 3;
                            }
                        }
                        if($item[0] == "none"){
                            $defaultValue = $item[1];
                        }
                    }  

                }
                foreach ($value as $item) {
                    if($item[0] == "none"){
                        $defaultValue = $item[1];
                    }
                }
                $actor->putBonus([$key => -$defaultValue]);
                $text = "Vous avez dépensé " . $defaultValue . " " . CARACS[$key].".";
                array_push($result, $text);
                break;
            }
            $actor->putBonus([$key => -$value]);
            $text = "Vous avez dépensé " . $value . " " . CARACS[$key].".";
            array_push($result, $text);
        }
        return $result;
    }

    public function toRemove(): bool {
        return true;
    }
}