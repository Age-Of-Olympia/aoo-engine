<?php

namespace App\Action\Condition;

use Player;

use App\Entity\ActionCondition;

class ForbidIfHasEffectCondition extends BaseCondition
{
    public function check(Player $actor, ?Player $target, ActionCondition $condition): ConditionResult
    {
        $result = new ConditionResult(true);
        $params = $condition->getParameters(); // e.g. { "effectName": "stunned" }
        $effectName = $params['effectName'] ?? '';

        if ($target && $target->have_effect($effectName)) {
            $errorMessage[0] = "Un effet empêche l'action : $effectName";
            $result = new ConditionResult(false, null, $errorMessage);
        }

        return $result;
    }
}