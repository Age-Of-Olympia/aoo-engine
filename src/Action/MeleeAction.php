<?php

namespace App\Action;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Action;
use Player;

#[ORM\Entity]
class MeleeAction extends Action
{
    public function calculateXp(bool $success, Player $actor, Player $target): array
    {
        $actorXp = $this->calculateActorXp($success, $actor, $target);
        $targetXp = $this->calculateTargetXp($success, $actor, $target);
        $xpResultsArray["actor"] = $actorXp;
        $xpResultsArray["target"] = $targetXp;
        return $xpResultsArray;
    }

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

    private function calculateActorXp(bool $success, Player $actor, Player $target): int
    {
        if ($success) {
            if (!isset($actor->data)) {
                $actor->get_data();
            }
            if (!isset($target->data)) {
                $target->get_data();
            }
    
            $playerRank = $actor->data->rank;
            $targetRank = $target->data->rank;
            $diff = $playerRank - $targetRank;
    
            $playerXp = ACTION_XP - $diff;
    
            if ($playerXp < 1) {
                $playerXp = 1;
            }
    
            if ($actor->data->faction != '' && $actor->data->faction == $target->data->faction) {
                $playerXp = 1;
            }
    
            if ($actor->data->secretFaction != '' && $actor->data->secretFaction == $target->data->secretFaction) {
                $playerXp = 1;
            }
            if ($target->data->isInactive) {
                $playerXp = 1;
            }
        } else {
            $playerXp = 0;
        }
        return $playerXp;
    }

    private function calculateTargetXp(bool $success, Player $actor, Player $target): int
    {
        if ($success) {
            $targetXp = 0;
        } else {
            $targetXp = 2;
        }
        return $targetXp;
    }

}
