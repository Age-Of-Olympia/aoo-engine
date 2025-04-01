<?php

namespace App\Action;

use App\Entity\Action;
use Doctrine\ORM\Mapping as ORM;
use Player;

#[ORM\Entity]
class TrainAction extends AttackAction
{
    public function getLogMessages(Player $actor, Player $target): array
    {
        $actorLog = $actor->data->name." s'est entraîné avec ".$target->data->name.".";
        $targetLog = $target->data->name." a été entraîné par ".$actor->data->name. ".";
        $infosArray["actor"] = $actorLog; 
        $infosArray["target"] = $targetLog;
        return $infosArray;
    }

    protected function calculateActorXp(bool $success, Player $actor, Player $target): int
    {
        $actorRank = $actor->data->rank;
        $targetRank = $target->data->rank;

        if($actorRank == $targetRank){
            $actorXp = 2;
        }
        elseif($actorRank > $targetRank){
            $actorXp = 1;
        }
        elseif($actorRank < $targetRank){
            $actorXp = 3;
        }
        return $actorXp;
    }

    protected function calculateTargetXp(bool $success, Player $actor, Player $target): int
    {
        $actorRank = $actor->data->rank;
        $targetRank = $target->data->rank;

        if($actorRank == $targetRank){
            $targetXp = 2;
        }
        elseif($actorRank > $targetRank){
            $targetXp = 3;
        }
        elseif($actorRank < $targetRank){
            $targetXp = 1;
        }
        return $targetXp;
    }

}
