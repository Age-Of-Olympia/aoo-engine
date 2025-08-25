<?php

namespace App\Action;

use App\Entity\Action;
use App\Interface\ActorInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class RunAction extends Action
{
    public function calculateXp(bool $success, ActorInterface $actor, ActorInterface $target): array
    {
        $actorXp = 1;
        $targetXp = 0;
        $xpResultsArray["actor"] = $actorXp;
        $xpResultsArray["target"] = $targetXp;
        return $xpResultsArray;
    }

    public function getLogMessages(ActorInterface $actor, ActorInterface $target): array
    {
        $actorLog = 'Vous avez couru.';
        $infosArray["actor"] = $actorLog; 
        $infosArray["target"] = '';
        return $infosArray;
    }
}
