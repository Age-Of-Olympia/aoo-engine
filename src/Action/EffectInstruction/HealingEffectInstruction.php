<?php

namespace App\Action\EffectInstruction;

use App\Entity\EffectInstruction;
use Doctrine\ORM\Mapping as ORM;
use Player;
use View;

#[ORM\Entity]
class HealingEffectInstruction extends EffectInstruction
{
    public function execute(Player $actor, Player $target): EffectResult {

        // e.g. { "actorHealingTrait": "agi" }, { "actorHealingTrait": "agi", "bonusHealingTrait" : "3" }
        $actorTraitHealing = $this->getParameters()['actorHealingTrait'] ?? 0;
        $bonusTraitHealing = $this->getParameters()['bonusHealingTrait'] ?? 0;
        $effectSuccessMessages = array();
        if(!empty($actorTraitHealing)){
            $baseHeal = (is_numeric($actorTraitHealing)) ? $actorTraitHealing : $actor->caracs->{$actorTraitHealing};
            $bonusHeal = (is_numeric($bonusTraitHealing)) ? $bonusTraitHealing : $actor->caracs->{$bonusTraitHealing};
            $healing = $baseHeal + $bonusHeal;
            $target->putBonus(array('pv'=>$healing));
            $effectSuccessMessages[0] = 'Vous soignez '. $healing .' points de vie à '. $target->data->name.'.';
            $effectSuccessMessages[1] = CARACS[$actorTraitHealing] .' = '. $baseHeal;
            if ($bonusHeal > 0) {
                $effectSuccessMessages[1] .= ' + '. $bonusHeal;
            }
        
        } {
            //handle not working case
        }

        return new EffectResult(true, effectSuccessMessages:$effectSuccessMessages, effectFailureMessages: array(), totalDamages:$healing);
    }
}
