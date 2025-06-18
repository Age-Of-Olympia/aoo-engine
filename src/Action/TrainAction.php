<?php

namespace App\Action;

use App\Entity\Action;
use Doctrine\ORM\Mapping as ORM;
use Classes\Player;

#[ORM\Entity]
class TrainAction extends Action
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
        $actorEnergie = $actor->data->energie;

        $actorXp = 1;
        $bonusXp = 0;

        if($actorEnergie > 2){
            $bonusXp += 1;
        }
        if($actorEnergie > 0){
            $bonusXp += 1;
        }
        if($actorRank < $targetRank){
            $bonusXp += 1;
        }
        
        // Retire 1 à l'énergie de l'acteur
        $actor->putEnergie(-1);

        return $actorXp+$bonusXp;
    }

    protected function calculateTargetXp(bool $success, Player $actor, Player $target): int
    {
        $actorRank = $actor->data->rank;
        $targetRank = $target->data->rank;
        $targetEnergie = $target->data->energie;

        $targetXp = 1;
        $bonusXp = 0;

        if($targetEnergie > 2){
            $bonusXp += 1;
        }
        if($targetEnergie > 0){
            $bonusXp += 1;
        }
        if($targetRank < $actorRank){
            $bonusXp += 1;
        }
        
        // Retire 1 à l'énergie de la cible
        $target->putEnergie(-1);
        
        return $targetXp+$bonusXp;
    }

}
