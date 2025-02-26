<?php

namespace App\Action\Condition;

use Player;

use App\Entity\ActionCondition;

class RequiresTraitValueCondition extends BaseCondition
{
    public function check(Player $actor, ?Player $target, ActionCondition $condition): ConditionResult
    {
        $result = new ConditionResult(true);
        $params = $condition->getParameters(); // e.g. { "a": 1, "pm": 10 }
        
        $details = array();
        $costIsAffordable = true;
        foreach ($params as $key => $value) {
            if ($actor->get_left($key) < $value) {
                array_push($details, "Pas assez de ".$key);
                $costIsAffordable = false;
            }

        }
        
        if (!$costIsAffordable) {
            $result = new ConditionResult(false, null, $details);
        }

        return $result;
    }

    public function toRemove(): bool {
        return true;
    }
}