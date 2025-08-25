<?php

namespace App\Action;

use App\Interface\ActorInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class TechniqueAction extends AttackAction
{

    public function getLogMessages(ActorInterface $actor, ActorInterface $target): array
    {
        $actorLog = $actor->data->name." a lancé ".$this->getDisplayName()." sur ".$target->data->name.".";
        $targetLog = $target->data->name." a été attaqué par ".$actor->data->name. " avec ".$this->getDisplayName().".";
        $infosArray["actor"] = $actorLog; 
        $infosArray["target"] = $targetLog;
        return $infosArray;
    }

}
