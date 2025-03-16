<?php

namespace App\Action;

use App\Entity\Action;
use Doctrine\ORM\Mapping as ORM;
use Player;

#[ORM\Entity]
class StealAction extends AttackAction
{
    public function calculateXp(bool $success, Player $actor, Player $target): array
    {
        $actorXp = $this->calculateActorXp($success, $actor, $target);
        $targetXp =  $this->calculateTargetXp($success, $actor, $target);;
        $xpResultsArray["actor"] = $actorXp;
        $xpResultsArray["target"] = $targetXp;
        return $xpResultsArray;
    }

    public function getLogMessages(Player $actor, Player $target): array
    {
        $actorLog = $actor->data->name." a volé ".$target->data->name.".";
        $targetLog = $target->data->name." a été volé par ".$actor->data->name. ".";
        $infosArray["actor"] = $actorLog; 
        $infosArray["target"] = $targetLog;
        return $infosArray;
    }

    protected function calculateActorXp(bool $success, Player $actor, Player $target): int
    {
        if ($success) {
            $playerXp = $actor->get_action_xp($target);
            if ($playerXp > MAX_XP_FOR_STEALING) {
                $playerXp = MAX_XP_FOR_STEALING;
            }
        } else {
            $playerXp = 0;
        }
        return $playerXp;
    }

    protected function calculateTargetXp(bool $success, Player $actor, Player $target): int
    {
        if ($success) {
            $playerXp = 0;
        } else {
            $playerXp = 2;
        }
        return $playerXp;
    }

    public function hideWhenSuccess(): bool
    {
        return true;
    }

}
