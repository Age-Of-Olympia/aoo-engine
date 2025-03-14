<?php

namespace App\Action\EffectInstruction;

use App\Entity\EffectInstruction;
use Doctrine\ORM\Mapping as ORM;
use Player;

#[ORM\Entity]
class ModifyStatEffectInstruction extends EffectInstruction
{
    public function execute(Player $actor, Player $target): EffectResult {

        // e.g. { "actorDamages": "f", "targetDamages": "e" } (bonusDamage?)
        $actorTraitDamages = $this->getParameters()['actorDamages'];
        $targetTraitDamagesTaken = $this->getParameters()['targetDamages'];

        if(!empty($actorTraitDamages) && !empty($targetTraitDamagesTaken)){
            $actorDamages = (is_numeric($actorTraitDamages)) ? $actorTraitDamages : $target->caracs->{$actorTraitDamages};
            $targetDamages = (is_numeric($targetTraitDamagesTaken)) ? $targetTraitDamagesTaken : $target->caracs->{$targetTraitDamagesTaken};
            $totalDamages = $actorDamages - $targetDamages;
            if($totalDamages < 1){
                $totalDamages = 1;
            }

            //CRIT ? (devrait dépendre du scores des dés ?)
            //ESQUIVE ? (géré dans les conditions ?)
            //TANK ?
            $target->put_bonus(array('pv'=>-$totalDamages));
            $effectSuccessMessages[0] = 'Vous infligez '. $totalDamages .' dégâts à '. $target->data->name.'.';
            $effectSuccessMessages[1] = CARACS[$actorTraitDamages] .' - '. CARACS[$targetTraitDamagesTaken] .' = '. $actorDamages .' - '. $targetDamages .' = '. $totalDamages .' dégâts';

            // put assist
            $actor->put_assist($target, $totalDamages);

            //BREAK WEAPON ? -> not here, not a direct consequence
            
        } {
            //handle not working case
        }

        return new EffectResult(true, effectSuccessMessages:$effectSuccessMessages, effectFailureMessages: array(), totalDamages:$totalDamages);
    }
}
