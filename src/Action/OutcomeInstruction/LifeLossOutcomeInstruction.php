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

        // e.g. { "actorDamagesTrait": "f", "targetDamagesTrait": "e", "bonusDamagesTrait" : "m", "distance" : true }
        $actorTraitDamages = $this->getParameters()['actorDamagesTrait'] ?? 0;
        $targetTraitDamagesTaken = $this->getParameters()['targetDamagesTrait'] ?? 0;
        $bonusTraitDamages = $this->getParameters()['bonusDamagesTrait'] ?? 0;
        $bonusTraitDefense = $this->getParameters()['bonusDefenseTrait'] ?? 0;
        $distanceInfluence = $this->getParameters()['distance'] ?? false;

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

            //CRIT ? (devrait dépendre du scores des dés ?)
            //ESQUIVE ? (géré dans les conditions ?)
            //TANK ?
            $target->putBonus(array('pv'=>-$totalDamages));
            $outcomeSuccessMessages[0] = 'Vous infligez '. $totalDamages .' dégâts à '. $target->data->name.'.';
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
            $outcomeSuccessMessages[1] = CARACS[$actorTraitDamages] .' - '. CARACS[$targetTraitDamagesTaken] .' = '. $actorDamages . $bonusDamagesText. ' - '. $targetDefense. $bonusDefenseText . $distanceText. ' = '. $totalDamages .' dégâts';

            // put assist
            $actor->put_assist($target, $totalDamages);

            //BREAK WEAPON ? -> not here, not a direct consequence
            
        } {
            //handle not working case
        }

        return new OutcomeResult(true, outcomeSuccessMessages:$outcomeSuccessMessages, outcomeFailureMessages: array(), totalDamages:$totalDamages);
    }
}
