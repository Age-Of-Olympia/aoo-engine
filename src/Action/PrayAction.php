<?php

namespace App\Action;

use App\Entity\Action;
use Doctrine\ORM\Mapping as ORM;
use Classes\Player;

#[ORM\Entity]
class PrayAction extends Action
{
    public function calculateXp(bool $success, Player $actor, Player $target): array
    {
        if ($success) {
            $actorXp = 1;
        } else {
            $actorXp = 0;
        }
        $targetXp = 0;
        $xpResultsArray["actor"] = $actorXp;
        $xpResultsArray["target"] = $targetXp;
        return $xpResultsArray;
    }

    public function getLogMessages(Player $actor, Player $target): array
    {
        $actorLog = $actor->data->name . ' a prié.';
        $infosArray["actor"] = $actorLog; 
        $infosArray["target"] = '';
        return $infosArray;
    }

}
