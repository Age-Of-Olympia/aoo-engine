<?php
namespace App\Action\Condition;

use Player;
use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use View;

class RangeBetweenDistanceCondition extends BaseCondition
{

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition): ConditionResult
    {
        $result = new ConditionResult(true);
        if (!$target) {
            $errorMessage[0] = "Aucune cible n'a été spécifiée.";
            return new ConditionResult(false, null, $errorMessage);
        }

        $params = $condition->getParameters(); // e.g. {"min":2,"max":6}
        $min = $params['min'] ?? 1;
        $max = $params['max'] ?? 5;

        $distance = View::get_distance($actor->getCoords(), $target->getCoords());
        if ($distance < $min || $distance > $max) {
            $errorMessage[0] = "Le distance $distance n'est pas dans l'intervalle [$min - $max].";
            return new ConditionResult(false, null, $errorMessage);
        }

        return $result;
    }

}