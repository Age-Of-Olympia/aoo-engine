<?php
namespace App\Action\Condition;

use App\Action\Condition\ConditionInterface;
use Player;
use App\Entity\ActionCondition;
use View;

class MinimumDistanceCondition extends BaseCondition
{

    public function check(Player $actor, ?Player $target, ActionCondition $condition): ConditionResult
    {
        $result = new ConditionResult(true);
        if (!$target) {
            $errorMessage[0] = "Aucune cible n'a été spécifiée.";
            return new ConditionResult(false, null, $errorMessage);
        }

        $params = $condition->getParameters(); // e.g. {"min":5}
        $min = $params['min'];

        $distance = View::get_distance($actor->get_coords(), $target->get_coords());
        if ($distance < $min) {
            $errorMessage[0] = "La cible est trop proche. Distance : $distance, min requis : $min.";
            return new ConditionResult(false, null, $errorMessage);
        }

        return $result;
    }

}