<?php

namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;

class ForbidIfHasEffectCondition extends BaseCondition
{
    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition): ConditionResult
    {
        $result = new ConditionResult(true);
        $params = $condition->getParameters(); // e.g. { "effectName": "stunned" }
        $effectName = $params['effectName'] ?? '';

        if ($target && $target->haveEffect($effectName)) {
            $errorMessage[0] = "Un effet empêche l'action : $effectName";
            $result = new ConditionResult(false, null, $errorMessage);
        }

        return $result;
    }
}