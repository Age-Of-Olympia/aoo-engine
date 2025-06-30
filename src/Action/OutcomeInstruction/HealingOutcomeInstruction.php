<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use Doctrine\ORM\Mapping as ORM;
use Classes\Player;
use Classes\View;

#[ORM\Entity]
class HealingOutcomeInstruction extends OutcomeInstruction
{
    public function execute(Player $actor, Player $target, array $rollsArray): OutcomeResult {

        // e.g. { "actorHealingTrait": "agi" }, { "actorHealingTrait": "agi", "bonusHealingTrait" : "3" }
        $actorTraitHealing = $this->getParameters()['actorHealingTrait'] ?? 0;
        $bonusTraitHealing = $this->getParameters()['bonusHealingTrait'] ?? 0;
        $outcomeSuccessMessages = array();
        if(!empty($actorTraitHealing)){
            $baseHeal = (is_numeric($actorTraitHealing)) ? $actorTraitHealing : $actor->caracs->{$actorTraitHealing};
            $bonusHeal = (is_numeric($bonusTraitHealing)) ? $bonusTraitHealing : $actor->caracs->{$bonusTraitHealing};
            $healing = $baseHeal + $bonusHeal;
            $target->putBonus(array('pv'=>$healing));
            $outcomeSuccessMessages[0] = 'Vous soignez '. $healing .' points de vie à '. $target->data->name.'.';
            $outcomeSuccessMessages[1] = CARACS[$actorTraitHealing] .' = '. $baseHeal;
            if ($bonusHeal > 0) {
                $outcomeSuccessMessages[1] .= ' + '. $bonusHeal;
            }
        
        } {
            //handle not working case
        }

        return new OutcomeResult(true, outcomeSuccessMessages:$outcomeSuccessMessages, outcomeFailureMessages: array(), totalDamages:$healing);
    }
}
