<?php

namespace App\Action;

use App\Entity\Action;
use App\Interface\ActorInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class StealAction extends Action
{
    public function getLogMessages(ActorInterface $actor, ActorInterface $target): array
    {
        $actorLog = $actor->data->name." a volé ".$target->data->name.".";
        $targetLog = $target->data->name." a été volé par ".$actor->data->name. ".";
        $infosArray["actor"] = $actorLog; 
        $infosArray["target"] = $targetLog;
        return $infosArray;
    }

    protected function calculateActorXp(bool $success, ActorInterface $actor, ActorInterface $target): int
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

    protected function calculateTargetXp(bool $success, ActorInterface $actor, ActorInterface $target): int
    {
        if ($success) {
            $playerXp = 0;
        } else {
            $playerXp = 2;
        }
        return $playerXp;
    }

    public function hideOnSuccess(): bool
    {
        return true;
    }

    public function activateAntiBerserk(): bool
    {
        return true;
    }

}
