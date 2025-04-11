<?php

namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;

class ForbidIfHasEffectCondition extends BaseCondition
{
    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition): ConditionResult
    {
        $preConditionResult = parent::check($actor, $target, $condition);
        if (!$preConditionResult->isSuccess()) {
            return $preConditionResult;
        }

        $result = new ConditionResult(true, array(), array());
        $params = $condition->getParameters(); // e.g. { "effectName": "adrenaline" }
        $actorEffectName = $params['actorEffect'] ?? '';
        $targetEffectName = $params['targetEffect'] ?? '';

        $errorMessage = array();
        if ($actor && $actor->haveEffect($actorEffectName)) {
            $errorMessage[0] = 'Un effet empêche l\'action : ' .$actorEffectName. ' <span class="ra '. EFFECTS_RA_FONT[$actorEffectName] .'"></span>' ;
            $result = new ConditionResult(false, array(), $errorMessage);
        }

        if ($target && $target->haveEffect($targetEffectName)) {
            $errorMessage[sizeof($errorMessage)] = 'Un effet sur la cible empêche l\'action : ' .$targetEffectName. ' <span class="ra '. EFFECTS_RA_FONT[$targetEffectName] .'"></span>' ;
            $result = new ConditionResult(false, array(), $errorMessage);
        }

        return $result;
    }
}