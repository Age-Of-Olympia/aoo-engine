<?php

namespace App\Action;

use App\Entity\Action;
use App\Interface\ActorInterface;
use Classes\Player;

abstract class AttackAction extends Action
{
    // Adrenaline + object-effect used to be added here in code; they now live as
    // type-level instructions under the "attack" key (action_type_instructions)
    // and are applied by the executor via ActionTypeInstructionResolver.

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

    protected function calculateActorXp(bool $success, ActorInterface $actor, ActorInterface $target): int
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

            // Get Action upgrades for degressive XP
            $actorUpgrades = $actor->get_upgrades();
            $reducAction = $actorUpgrades->a;
    
            $playerXp = ACTION_XP - $diff - $reducAction;
    
            if ($playerXp < 2) {
                $playerXp = 2;
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
            if ($diff > 3) {
                $playerXp = 0;
            }
        } else {
            $playerXp = 0;
        }
        return $playerXp;
    }

    protected function calculateTargetXp(bool $success, ActorInterface $actor, ActorInterface $target): int
    {
        if ($success) {
            $targetXp = 0;
        } else {
            $targetXp = 2;
        }
        return $targetXp;
    }

    public function activateAntiBerserk(): bool {
        return true;
    }

}
