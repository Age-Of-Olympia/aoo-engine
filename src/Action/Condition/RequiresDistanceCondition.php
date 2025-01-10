<?php
namespace App\Action\Condition;

use Player;
use View;

use App\Entity\ActionCondition;


class RequiresDistanceCondition implements ConditionInterface
{
    private ?string $errorMessage = null;

    public function check(Player $actor, ?Player $target, ActionCondition $condition): bool
    {
        if (!$target) {
            $this->errorMessage = "No target to check distance.";
            return false;
        }

        $params = $condition->getParameters(); // e.g. ["max" => 5]
        $maxDist = $params['max'] ?? 1;

        $distance = View::get_distance($actor->get_coords(), $target->get_coords());

        if ($distance > $maxDist) {
            $this->errorMessage = "Target is too far (distance $distance > max $maxDist)";
            return false;
        }

        return true;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
