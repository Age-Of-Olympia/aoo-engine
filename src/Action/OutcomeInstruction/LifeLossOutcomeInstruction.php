<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use Doctrine\ORM\Mapping as ORM;
use Player;
use View;

#[ORM\Entity]
class LifeLossOutcomeInstruction extends OutcomeInstruction
{
    public function execute(Player $actor, Player $target): OutcomeResult {

        // e.g. { "actorDamagesTrait": "f", "targetDamagesTrait": "e", "bonusDamagesTrait" : "m", "distance" : true, "autoCrit": true }
        $actorTraitDamages = $this->getParameters()['actorDamagesTrait'] ?? 0;
        $targetTraitDamagesTaken = $this->getParameters()['targetDamagesTrait'] ?? 0;
        $bonusTraitDamages = $this->getParameters()['bonusDamagesTrait'] ?? 0;
        $bonusTraitDefense = $this->getParameters()['bonusDefenseTrait'] ?? 0;
        $distanceInfluence = $this->getParameters()['distance'] ?? false;
        $autoCrit = $this->getParameters()['autoCrit'] ?? false;
        $outcomeSuccessMessages = array();

        if(!empty($actorTraitDamages) && !empty($targetTraitDamagesTaken)){
            $actorDamages = (is_numeric($actorTraitDamages)) ? $actorTraitDamages : $actor->caracs->{$actorTraitDamages};
            $targetDefense = (is_numeric($targetTraitDamagesTaken)) ? $targetTraitDamagesTaken : $target->caracs->{$targetTraitDamagesTaken};
            $bonusDamages = (is_numeric($bonusTraitDamages)) ? $bonusTraitDamages : $target->caracs->{$bonusTraitDamages};
            $bonusDefense = (is_numeric($bonusTraitDefense)) ? $bonusTraitDefense : $target->caracs->{$bonusTraitDefense};
            $totalDamages = $actorDamages + $bonusDamages - ($targetDefense + $bonusDefense);
            $cellCount = 0;
            if ($distanceInfluence) {
                $distance = View::get_distance($actor->getCoords(), $target->getCoords());
                $cellCount = $distance - 1;
                $totalDamages = $totalDamages - $cellCount;
            }
            if($totalDamages < 1){
                $totalDamages = 1;
            }

            //CRIT
            if(!isset($target->emplacements->tete) || $autoCrit){
                if(rand(1,100) <= DMG_CRIT || $autoCrit){ 
                    $critAdd = 3;
                    $totalDamages += $critAdd;
                    $outcomeSuccessMessages[sizeof($outcomeSuccessMessages)] = '<font color="red">Critique ! Dégâts augmentés ! +3 !</font>';
                }
            }
    
            //TANK ?

            $target->putBonus(array('pv'=>-$totalDamages));
            $outcomeSuccessMessages[sizeof($outcomeSuccessMessages)] = 'Vous infligez '. $totalDamages .' dégâts à '. $target->data->name.'.';
            $bonusDamagesText = "";
            if ($bonusDamages > 0) {
                $bonusText = '';
                if (!is_numeric($bonusTraitDamages)) {
                    $bonusText = ' '.CARACS[$bonusTraitDamages];
                }
                $bonusDamagesText = ' + ' . $bonusDamages. ' (bonus'.$bonusText.')';
            }
            $bonusDefenseText = "";
            if ($bonusDefense > 0) {
                $bonusText = '';
                if (!is_numeric($bonusTraitDefense)) {
                    $bonusText = ' '.CARACS[$bonusTraitDefense];
                }
                $bonusDefenseText = ' + ' . $bonusDefense. ' (bonus defense'.$bonusText.')';
            }
            $distanceText = "";
            if ($distanceInfluence) {
                $distanceText = ' - '. $cellCount. ' (distance)';
            }
            $outcomeSuccessMessages[sizeof($outcomeSuccessMessages)] = CARACS[$actorTraitDamages] .' - '. CARACS[$targetTraitDamagesTaken] .' = '. $actorDamages . $bonusDamagesText. ' - '. $targetDefense. $bonusDefenseText . $distanceText. ' = '. $totalDamages .' dégâts';

            // put assist
            $actor->put_assist($target, $totalDamages);
            
        } {
            //handle not working case
        }

        return new OutcomeResult(true, outcomeSuccessMessages:$outcomeSuccessMessages, outcomeFailureMessages: array(), totalDamages:$totalDamages);
    }
}
