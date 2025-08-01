<?php

namespace App\Action;

use Doctrine\ORM\Mapping as ORM;
use Classes\Player;

#[ORM\Entity]
class TechniqueAction extends AttackAction
{

    public function getLogMessages(Player $actor, Player $target): array
    {
        $actorLog = $actor->data->name." a lancé ".$this->getDisplayName()." sur ".$target->data->name.".";
        $targetLog = $target->data->name." a été attaqué par ".$actor->data->name. " avec ".$this->getDisplayName().".";
        $infosArray["actor"] = $actorLog; 
        $infosArray["target"] = $targetLog;
        return $infosArray;
    }

}
