<?php

namespace App\Action;

use App\Entity\Action;
use App\Interface\ActorInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class PrayAction extends Action
{
    public function calculateXp(bool $success, ActorInterface $actor, ActorInterface $target): array
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

    public function getLogMessages(ActorInterface $actor, ActorInterface $target): array
    {
        $actorLog = $actor->data->name . ' a prié.';
        $infosArray["actor"] = $actorLog; 
        $infosArray["target"] = '';
        return $infosArray;
    }

}
