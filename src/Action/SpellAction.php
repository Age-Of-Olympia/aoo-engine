<?php

namespace App\Action;

use Doctrine\ORM\Mapping as ORM;
use Player;

#[ORM\Entity]
class SpellAction extends AttackAction
{

  public function getLogMessages(Player $actor, Player $target): array
    {
        //Player should have a method to give correct weapon (with inheritance ?)
        if ($actor->data->race != 'animal') {
            $weapon = " avec ".$actor->emplacements->main1->data->name.".";
        } else {
            $weapon = ".";
        }
        $actorLog = $actor->data->name." a attaqué ".$target->data->name.$weapon;
        $targetLog = $target->data->name." a été attaqué par ".$actor->data->name.$weapon;
        $infosArray["actor"] = $actorLog; 
        $infosArray["target"] = $targetLog;
        return $infosArray;
    }

}
