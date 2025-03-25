<?php

namespace App\Action;

use Doctrine\ORM\Mapping as ORM;
use Player;

#[ORM\Entity]
class SpellAction extends AttackAction
{

    public function getLogMessages(Player $actor, Player $target): array
    {
        $actorLog = $actor->data->name." a lancé ".$this->getName()." sur ".$target->data->name.".";
        $targetLog = $target->data->name." a été attaqué par ".$actor->data->name. "avec ".$this->getName().".";
        $infosArray["actor"] = $actorLog; 
        $infosArray["target"] = $targetLog;
        return $infosArray;
    }

}
