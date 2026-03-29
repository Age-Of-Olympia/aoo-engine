<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Action\Condition\ConditionObject;
use Doctrine\ORM\Mapping as ORM;
use Classes\Player;

#[ORM\Entity]
class HealingOutcomeInstruction extends OutcomeInstruction
{
    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult {

        // e.g. { "actorHealingTrait": "agi" }, { "actorHealingTrait": "agi", "bonusHealingTrait" : "3" }
        $actorTraitHealing = $this->getParameters()['actorHealingTrait'] ?? 0;
        $targetTraitHealing =  $this->getParameters()['targetHealingTrait'] ?? 0;
        $bonusTraitHealing = $this->getParameters()['bonusHealingTrait'] ?? 0;
        $actorTraitPMHealing = $this->getParameters()['actorPMHealingTrait'] ?? 0;
        $bonusTraitPMHealing = $this->getParameters()['bonusPMHealingTrait'] ?? 0;
        $divisor =  $this->getParameters()['divisor'] ?? 1;

        $outcomeSuccessMessages = array();
        $healing = 0;

        if(!empty($actorTraitHealing) || !empty($targetTraitHealing)){
            if(!empty($actorTraitHealing)){
                $baseHeal = is_numeric($actorTraitHealing) ? $actorTraitHealing : $actor->caracs->{$actorTraitHealing};
            }
            else {
                $baseHeal = is_numeric($targetTraitHealing) ? $targetTraitHealing : $target->caracs->{$targetTraitHealing};
            }
            
            $bonusHeal = is_numeric($bonusTraitHealing) ? $bonusTraitHealing : ($actor->caracs->$bonusTraitHealing ?? 0);
            $healing = floor($baseHeal/$divisor) + $bonusHeal;

            $target->putBonus(array('pv'=>$healing));

            $outcomeSuccessMessages[0] = 'Vous soignez '. $healing .' points de vie à '. $target->data->name.'.';
        
        } 
        
        if(!empty($actorTraitPMHealing)){
            $baseHeal = is_numeric($actorTraitPMHealing) ? $actorTraitPMHealing : $actor->caracs->{$actorTraitPMHealing};
            $bonusHeal = is_numeric($bonusTraitPMHealing) ? $bonusTraitPMHealing : $actor->caracs->{$bonusTraitPMHealing};
            $healing = $baseHeal + $bonusHeal;
            $target->putBonus(array('pm'=>$healing));
            $outcomeSuccessMessages[0] = 'Vous rendez '. $healing .' points de mana à '. $target->data->name.'.';
            $outcomeSuccessMessages[1] = is_numeric($actorTraitPMHealing) ? "Valeur fixe à " . $actorTraitPMHealing . '.' : CARACS[$actorTraitPMHealing] .' = '. $baseHeal;
            if ($bonusHeal > 0) {
                $outcomeSuccessMessages[1] .= ' + '. $bonusHeal;
            }
        
        } {
            //handle not working case
        }

        return new OutcomeResult(true, outcomeSuccessMessages:$outcomeSuccessMessages, outcomeFailureMessages: array(), totalDamages:$healing);
    }
}
