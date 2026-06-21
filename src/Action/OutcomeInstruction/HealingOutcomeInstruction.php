<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Action\Condition\ConditionObject;
use App\Action\Schema\FieldType;
use App\Action\Schema\HasParameterSchema;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use Doctrine\ORM\Mapping as ORM;
use Classes\Player;

#[ORM\Entity]
class HealingOutcomeInstruction extends OutcomeInstruction implements HasParameterSchema
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField('actorHealingTrait', FieldType::TRAIT_OR_INT, 'Soin PV depuis (acteur)'),
            new ParameterField('targetHealingTrait', FieldType::TRAIT_OR_INT, 'Soin PV depuis (cible)'),
            new ParameterField('bonusHealingTrait', FieldType::TRAIT_OR_INT, 'Bonus soin PV'),
            new ParameterField('actorPMHealingTrait', FieldType::TRAIT_OR_INT, 'Soin PM depuis (acteur)'),
            new ParameterField('bonusPMHealingTrait', FieldType::TRAIT_OR_INT, 'Bonus soin PM'),
            new ParameterField('divisor', FieldType::INT, 'Diviseur', default: 1),
        );
    }

    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult {

        $params = $this->getParameters();

        // e.g. { "actorHealingTrait": "agi" }, { "actorHealingTrait": "agi", "bonusHealingTrait" : "3" }
        $actorTraitHealing = $params['actorHealingTrait'] ?? 0;
        $targetTraitHealing =  $params['targetHealingTrait'] ?? 0;
        $bonusTraitHealing = $params['bonusHealingTrait'] ?? 0;
        $actorTraitPMHealing = $params['actorPMHealingTrait'] ?? 0;
        $bonusTraitPMHealing = $params['bonusPMHealingTrait'] ?? 0;
        $divisor =  $params['divisor'] ?? 1;

        $outcomeSuccessMessages = array();
        $pvHealing = 0;
        $pmHealing = 0;

        if(!empty($actorTraitHealing) || !empty($targetTraitHealing)){
            if(!empty($actorTraitHealing)){
                $baseHeal = is_numeric($actorTraitHealing) ? $actorTraitHealing : $actor->caracs->{$actorTraitHealing};
            }
            else {
                $baseHeal = is_numeric($targetTraitHealing) ? $targetTraitHealing : $target->caracs->{$targetTraitHealing};
            }

            $bonusHeal = is_numeric($bonusTraitHealing) ? $bonusTraitHealing : ($actor->caracs->$bonusTraitHealing ?? 0);
            $pvHealing = $this->computePvHeal((float) $baseHeal, (float) $bonusHeal, (int) $divisor);

            $target->putBonus(array('pv'=>$pvHealing));

            $outcomeSuccessMessages[0] = 'Vous soignez '. $pvHealing .' points de vie à '. $target->data->name.'.';

        }

        if(!empty($actorTraitPMHealing)){
            $baseHeal = is_numeric($actorTraitPMHealing) ? $actorTraitPMHealing : ($actor->caracs->{$actorTraitPMHealing} ?? 0);
            $bonusHeal = is_numeric($bonusTraitPMHealing) ? $bonusTraitPMHealing : ($actor->caracs->{$bonusTraitPMHealing} ?? 0);
            $pmHealing = $this->computePmHeal((float) $baseHeal, (float) $bonusHeal);
            $target->putBonus(array('pm'=>$pmHealing));
            $outcomeSuccessMessages[] = 'Vous rendez '. $pmHealing .' points de mana à '. $target->data->name.'.';
            $pmDetail = is_numeric($actorTraitPMHealing) ? "Valeur fixe à " . $actorTraitPMHealing . '.' : CARACS[$actorTraitPMHealing] .' = '. $baseHeal;
            if ($bonusHeal > 0) {
                $pmDetail .= ' + '. $bonusHeal;
            }
            $outcomeSuccessMessages[] = $pmDetail;
        }

        return new OutcomeResult(true, outcomeSuccessMessages:$outcomeSuccessMessages, outcomeFailureMessages: array(), totalDamages:$pvHealing + $pmHealing);
    }

    public function computePvHeal(float $baseHeal, float $bonusHeal, int $divisor): int
    {
        return (int) (floor($baseHeal / $divisor) + $bonusHeal);
    }

    public function computePmHeal(float $baseHeal, float $bonusHeal): int
    {
        return (int) ($baseHeal + $bonusHeal);
    }
}
