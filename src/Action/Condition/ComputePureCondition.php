<?php
namespace App\Action\Condition;

use App\Action\OutcomeInstruction\MalusOutcomeInstruction;
use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Action\Condition\ConditionObject;
use App\Action\Combat\CombatResolver;
use App\Action\Combat\RollDetailView;
use App\Action\Schema\HasParameterSchema;
use App\Action\Schema\ParameterSchema;
use Classes\Dice;
use Classes\View;

class ComputePureCondition extends AbstractComputeCondition implements HasParameterSchema
{
    public static function parameterSchema(): ParameterSchema
    {
        return ComputeCondition::parameterSchema();
    }

    protected function computeActor($actor, $dice, $conditionObject)
    {
        $actorRollBonus = $conditionObject->getActorRollBonus();
        $actorRollTraitValue = $actor->caracs->{$this->actorRollTrait};
        $actorRoll = (new CombatResolver($dice))->rollDetailed(
            (int) $actorRollTraitValue,
            (bool) $conditionObject->getActorAdvantage(),
            (bool) $conditionObject->getActorDisadvantage()
        );
        $bonus = $conditionObject->getActorRollBonus();
        $tooltipOtherTxt = !empty($actorRollBonus) ? 'Bonus de compétence : ' . $actorRollBonus . ' ' : '';
        $actorTotal = array_sum($actorRoll->roll) + $bonus;
        $distanceMalus = $this->getDistanceMalus();
        $distanceMalusTxt = ($distanceMalus) ? ' - '. $distanceMalus .' (Distance)' : '';
        $actorTotal = $actorTotal - $distanceMalus;
        $actorTxt = 'Jet '. $actor->data->name .' = ' . '<span style="text-decoration: underline;" flow="up" tooltip="' . $distanceMalusTxt . (($distanceMalusTxt) ? ', ' . $tooltipOtherTxt : $tooltipOtherTxt) . RollDetailView::advantageTooltip($actorRoll) . '">' . $actorTotal . '</span> (Jet pur)';

        $conditionObject->setActorRoll($actorTotal);

        return array($actorRoll->roll, $actorTotal, $actorTxt);
    }

    protected function computeTarget($target, $dice, $conditionObject)
    {
        $traitsArray = explode('/', $this->targetRollTrait);
        if (sizeof($traitsArray) == 1) {
            $targetRollTraitValue = $target->caracs->{$this->targetRollTrait};
        } else if (sizeof($traitsArray) == 2) {
            $option1 = $target->caracs->{$traitsArray[0]};
            $option2 = $target->caracs->{$traitsArray[1]};
            $targetRollTraitValue = max($option1, $option2);
        } else {
            return array(0, 0, "Impossible de calculer, erreur de paramétrage.");
        }
        
        $targetRoll = (new CombatResolver($dice))->rollDetailed(
            (int) $targetRollTraitValue,
            (bool) $conditionObject->getTargetAdvantage(),
            (bool) $conditionObject->getTargetDisadvantage()
        );
        $bonus = $conditionObject->getTargetRollBonus();
        $targetTotal = array_sum($targetRoll->roll) + $bonus;
        $tooltipOtherTxt = !empty($bonus) ? 'Bonus de compétence : ' . $conditionObject->getTargetRollBonus() . ' ' : '';
        $targetOtherTxt = ($bonus != 0) ? ($bonus < 0 ? ' - '.abs($bonus) : $bonus) . ' (<span style="text-decoration: underline;" flow="up" tooltip="' . $tooltipOtherTxt . '">Autre</span>) = ' . (array_sum($targetRoll->roll) + $bonus) . ' (Jet pur)' : ' (Jet pur)';
        $targetTxt = 'Jet '. $target->data->name .' = '. RollDetailView::advantageWrap(array_sum($targetRoll->roll), $targetRoll) . $targetOtherTxt;

        $conditionObject->setTargetRoll($targetTotal);

        return array($targetRoll->roll, $targetTotal, $targetTxt);
    }

}
