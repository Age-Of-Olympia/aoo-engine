<?php
namespace App\Action\Condition;

use Player;
use View;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;

class RequiresDistanceCondition extends BaseCondition
{
    private ?string $errorMessage = null;

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition): ConditionResult
    {
        $result = new ConditionResult(true);
        if (!$target) {
            $errorMessage[0] = "Aucune cible n'a été spécifiée.";
            return new ConditionResult(false, null, $errorMessage);
        }

        $params = $condition->getParameters(); // e.g. { "max": 1 }
        $maxDist = $params['max'] ?? 1;

        $distance = View::get_distance($actor->getCoords(), $target->getCoords());

        if ($distance > $maxDist) {
            $errorMessage[0] = "La cible est trop loin ! (distance $distance > max $maxDist)";
            return new ConditionResult(false, null, $errorMessage);
        }

        return $result;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
