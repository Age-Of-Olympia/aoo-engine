<?php

namespace App\Action;

use Doctrine\ORM\Mapping as ORM;
use Classes\Player;
use App\Entity\Action;

#[ORM\Entity]
class BuffAction extends Action
{   
    public function calculateXp(bool $success, Player $actor, Player $target): array
    {
        $actorXp = $this->calculateActorXp($success, $actor, $target);
        $targetXp = 0;
        $xpResultsArray["actor"] = $actorXp;
        $xpResultsArray["target"] = $targetXp;
        return $xpResultsArray;
    }
    
    public function getLogMessages(Player $actor, Player $target): array
    {
        $actorLog = $actor->data->name." a lancé ".$this->getDisplayName()." sur ".$target->data->name.".";
        $targetLog = $target->data->name." a été renforcé par ".$actor->data->name. " avec ".$this->getDisplayName().".";
        $infosArray["actor"] = $actorLog; 
        $infosArray["target"] = $targetLog;
        return $infosArray;
    }

    protected function calculateActorXp(bool $success, Player $actor, Player $target): int
    {
        if ($success) {
            $playerXp = 3;
        } else {
            $playerXp = 0;
        }
        return $playerXp;
    }
}
