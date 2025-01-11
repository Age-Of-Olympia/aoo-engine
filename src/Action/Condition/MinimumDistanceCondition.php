<?php
namespace App\Condition;

use App\Action\Condition\ConditionInterface;
use Player;
use App\Entity\ActionCondition;
use View;

class MinimumDistanceCondition implements ConditionInterface
{
    private ?string $errorMessage = null;

    public function check(Player $actor, ?Player $target, ActionCondition $condition): bool
    {
        if (!$target) {
            $this->errorMessage = "No target specified.";
            return false;
        }

        $params = $condition->getParameters(); // e.g. {"min":5}
        $min = $params['min'] ?? 1;

        $distance = View::get_distance($actor->get_coords(), $target->get_coords());
        if ($distance < $min) {
            $this->errorMessage = "Target is too close. Distance: $distance, min required: $min.";
            return false;
        }

        return true;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}