<?php
namespace App\Condition;

use App\Action\Condition\ConditionInterface;
use Player;
use App\Entity\ActionCondition;
use View;

class RangeBetweenDistanceCondition implements ConditionInterface
{
    private ?string $errorMessage = null;

    public function check(Player $actor, ?Player $target, ActionCondition $condition): bool
    {
        if (!$target) {
            $this->errorMessage = "No target specified.";
            return false;
        }

        $params = $condition->getParameters(); // e.g. {"min":2,"max":6}
        $min = $params['min'] ?? 1;
        $max = $params['max'] ?? 5;

        $distance = View::get_distance($actor->get_coords(), $target->get_coords());
        if ($distance < $min || $distance > $max) {
            $this->errorMessage = "Distance $distance is outside the allowed range [$min - $max].";
            return false;
        }

        return true;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}