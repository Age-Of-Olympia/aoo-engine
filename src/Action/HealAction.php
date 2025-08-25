<?php

namespace App\Action;

use App\Entity\Action;
use App\Interface\ActorInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class HealAction extends Action
{
    public function calculateXp(bool $success, ActorInterface $actor, ActorInterface $target): array
    {
        $actorXp = $this->calculateActorXp($success, $actor, $target);
        $targetXp = 0;
        $xpResultsArray["actor"] = $actorXp;
        $xpResultsArray["target"] = $targetXp;
        return $xpResultsArray;
    }

    public function getLogMessages(ActorInterface $actor, ActorInterface $target): array
    {
        $actorLog = $actor->data->name." a lancé ".$this->getDisplayName()." sur ".$target->data->name.".";
        $targetLog = $target->data->name." a été soigné par ".$actor->data->name. " avec ".$this->getDisplayName().".";
        $infosArray["actor"] = $actorLog; 
        $infosArray["target"] = $targetLog;
        return $infosArray;
    }

    protected function calculateActorXp(bool $success, ActorInterface $actor, ActorInterface $target): int
    {
        if ($success) {
            $playerXp = 3;
        } else {
            $playerXp = 0;
        }
        return $playerXp;
    }

    public function activateAntiBerserk(): bool
    {
        return true;
    }

}
