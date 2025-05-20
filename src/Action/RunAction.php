<?php

namespace App\Action;

use App\Entity\Action;
use Doctrine\ORM\Mapping as ORM;
use Player;

#[ORM\Entity]
class RunAction extends Action
{
    public function calculateXp(bool $success, Player $actor, Player $target): array
    {
        $actorXp = 1;
        $targetXp = 0;
        $xpResultsArray["actor"] = $actorXp;
        $xpResultsArray["target"] = $targetXp;
        return $xpResultsArray;
    }

    public function getLogMessages(Player $actor, Player $target): array
    {
        $actorLog = 'Vous avez couru.';
        $infosArray["actor"] = $actorLog; 
        $infosArray["target"] = '';
        return $infosArray;
    }

    public function activateAntiBerserk(): bool
    {
        return true;
    }

}
