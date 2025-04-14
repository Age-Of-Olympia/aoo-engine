<?php

namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;

class ForbidIfHasEffectCondition extends BaseCondition
{
    private array $errorMessage = array();

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition): ConditionResult
    {
        $preConditionResult = parent::check($actor, $target, $condition);
        if (!$preConditionResult->isSuccess()) {
            return $preConditionResult;
        }

        $result = new ConditionResult(true, array(), array());
        $params = $condition->getParameters(); // e.g. { "effectName": "adrenaline" }
        $actorEffectName = $params['actorEffect'] ?? '';
        $actorEffectsArrayName = $params['actorEffects'] ?? array();
        $targetEffectName = $params['targetEffect'] ?? '';
        $targetEffectsArrayName = $params['targetEffects'] ?? array();

        $this->checkEffect($actor, $actorEffectName);
        $this->checkEffect($target, $targetEffectName);

        if (sizeof($actorEffectsArrayName) > 0) {
            foreach ($actorEffectsArrayName as $k => $v ) {
                $this->checkEffect($actor, $v);
            }
        }

        if (sizeof($targetEffectsArrayName) > 0) {
            foreach ($targetEffectsArrayName as $k => $v ) {
                $this->checkEffect($target, $v);
            }
        }

        if (sizeof($this->errorMessage) > 0) {
            $result = new ConditionResult(false, array(), $this->errorMessage);
        }
        return $result;
    }

    private function checkEffect(ActorInterface $player, string $effectName)
    {
        if ($player && $player->haveEffect($effectName)) {
            $this->errorMessage[sizeof($this->errorMessage)] = 'Un effet empêche l\'action : ' .$effectName. ' <span class="ra '. EFFECTS_RA_FONT[$effectName] .'"></span>' ;
        }
    }
}
