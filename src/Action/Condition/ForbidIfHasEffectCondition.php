<?php

namespace App\Action\Condition;

include '../../classes/player.php';
use Player;

use App\Entity\ActionCondition;

class ForbidIfHasEffectCondition implements ConditionInterface
{
    private ?string $errorMessage = null;

    public function check(Player $actor, ?Player $target, ActionCondition $condition): bool
    {
        $params = $condition->getParameters(); // e.g. { "effectName": "stunned" }
        $effectName = $params['effectName'] ?? '';

        if ($target && $target->hasEffect($effectName)) {
            $this->errorMessage = "Target has forbidden effect: $effectName";
            return false;
        }

        return true;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}