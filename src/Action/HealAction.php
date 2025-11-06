<?php

namespace App\Action;

use App\Entity\Action;
use App\Interface\ActorInterface;
use Doctrine\ORM\Mapping as ORM;
use Classes\Player;

#[ORM\Entity]
class HealAction extends BuffAction
{

    public function getLogMessages(Player $actor, Player $target): array
    {
        $actorLog = $actor->data->name." a lancé ".$this->getDisplayName()." sur ".$target->data->name.".";
        $targetLog = $target->data->name." a été soigné par ".$actor->data->name. " avec ".$this->getDisplayName().".";
        $infosArray["actor"] = $actorLog; 
        $infosArray["target"] = $targetLog;
        return $infosArray;
    }
    
}
