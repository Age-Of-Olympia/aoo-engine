<?php

namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;

class ForbidIfHasEffectCondition extends BaseCondition
{
    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition): ConditionResult
    {
        $result = new ConditionResult(true);
        $params = $condition->getParameters(); // e.g. { "effectName": "adrenaline" }
        $actorEffectName = $params['actorEffect'] ?? '';
        $targetEffectName = $params['targetEffect'] ?? '';

        $errorMessage = array();
        if ($actor && $actor->haveEffect($actorEffectName)) {
            $errorMessage[0] = 'Un effet empêche l\'action : ' .$actorEffectName. ' <span class="ra '. EFFECTS_RA_FONT[$actorEffectName] .'"></span>' ;
            $result = new ConditionResult(false, null, $errorMessage);
        }

        if ($target && $target->haveEffect($targetEffectName)) {
            $errorMessage[sizeof($errorMessage)] = 'Un effet sur la cible empêche l\'action : ' .$targetEffectName. ' <span class="ra '. EFFECTS_RA_FONT[$targetEffectName] .'"></span>' ;
            $result = new ConditionResult(false, null, $errorMessage);
        }

        return $result;
    }
}