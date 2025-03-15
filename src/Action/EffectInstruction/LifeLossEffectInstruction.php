<?php

namespace App\Action\EffectInstruction;

use App\Entity\EffectInstruction;
use Doctrine\ORM\Mapping as ORM;
use Player;
use View;

#[ORM\Entity]
class LifeLossEffectInstruction extends EffectInstruction
{
    public function execute(Player $actor, Player $target): EffectResult {

        // e.g. { "actorDamagesTrait": "f", "targetDamagesTrait": "e", "bonusDamagesTrait" : "m", "distance" : true }
        $actorTraitDamages = $this->getParameters()['actorDamagesTrait'] ?? 0;
        $targetTraitDamagesTaken = $this->getParameters()['targetDamagesTrait'] ?? 0;
        $bonusTraitDamages = $this->getParameters()['bonusDamagesTrait'] ?? 0;
        $distanceInfluence = $this->getParameters()['distance'] ?? false;


        if(!empty($actorTraitDamages) && !empty($targetTraitDamagesTaken)){
            $actorDamages = (is_numeric($actorTraitDamages)) ? $actorTraitDamages : $actor->caracs->{$actorTraitDamages};
            $targetDamages = (is_numeric($targetTraitDamagesTaken)) ? $targetTraitDamagesTaken : $target->caracs->{$targetTraitDamagesTaken};
            $bonusDamages = (is_numeric($bonusTraitDamages)) ? $bonusTraitDamages : $target->caracs->{$bonusTraitDamages};
            $totalDamages = $actorDamages + $bonusDamages - $targetDamages;
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
            $effectSuccessMessages[0] = 'Vous infligez '. $totalDamages .' dégâts à '. $target->data->name.'.';
            $bonusDamagesText = "";
            if ($bonusDamages > 0) {
                $bonusDamagesText = ' + ' . $bonusDamages. ' (bonus)';
            }
            $distanceText = "";
            if ($distanceInfluence) {
                $distanceText = ' - '. $cellCount. ' (distance)';
            }
            $effectSuccessMessages[1] = CARACS[$actorTraitDamages] .' - '. CARACS[$targetTraitDamagesTaken] .' = '. $actorDamages . $bonusDamagesText. ' - '. $targetDamages . $distanceText. ' = '. $totalDamages .' dégâts';

            // put assist
            $actor->put_assist($target, $totalDamages);

            //BREAK WEAPON ? -> not here, not a direct consequence
            
        } {
            //handle not working case
        }

        return new EffectResult(true, effectSuccessMessages:$effectSuccessMessages, effectFailureMessages: array(), totalDamages:$totalDamages);
    }
}
