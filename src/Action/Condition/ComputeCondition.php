<?php
namespace App\Action\Condition;

use App\Action\OutcomeInstruction\MalusOutcomeInstruction;
use Classes\Player;
use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use Classes\Dice;
use Classes\View;

enum Roll: string
{
    case cc = "cc";
    case ct = "ct";
    case fm = "fm";
    case cc_agi = "cc_agi";
}

class ComputeCondition extends BaseCondition
{
    protected int $distance;
    protected string $throwName = "Le tir";
    protected string $actorRollTrait;
    protected string $targetRollTrait;

    public function __construct() {
        array_push($this->preConditions, new DodgeCondition());
        array_push($this->preConditions, new NoBerserkCondition());
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition): ConditionResult
    {
        $preConditionResult = parent::check($actor, $target, $condition);
        if (!$preConditionResult->isSuccess()) {
            return $preConditionResult;
        }

        $params = $condition->getParameters(); // e.g. { "max": 1 }
        $this->actorRollTrait = $params['actorRollType'] ?? null;
        $this->targetRollTrait = $params['targetRollType'] ?? null;

        if (!$target) {
            $errorMessages[0] = "Aucune cible n'a été spécifiée.";
            return new ConditionResult(success: false, conditionSuccessMessages:$errorMessages, conditionFailureMessages:array());
        }

        $this->distance = View::get_distance($actor->getCoords(), $target->getCoords());

        $result = $this->computeAttack($actor, $target);

        if (!$result->isSuccess()) {
            $condition->getAction()->addAutomaticOutcomeInstruction(new MalusOutcomeInstruction());
        }

        return $result;
    }

    private function computeAttack(ActorInterface $actor, ?ActorInterface $target): ConditionResult 
    {
        $success = false;
        $dice = new Dice(3);

        list($actorRoll, $actorTotal, $actorTxt) = $this->computeActor($actor, $dice);
        $conditionDetailsSuccess[0] = $actorTxt;

        list($targetRoll, $targetTotal, $targetTxt) = $this->computeTarget($target, $dice);
        $conditionDetailsSuccess[1] = $targetTxt;
       
        $checkAboveDistance = $this->checkDistanceCondition($actorTotal);

        if(!AUTO_FAIL && $checkAboveDistance && ($actorTotal >= $targetTotal))
        {
            $success = true;
        }

        $conditionDetailsFailure = array();
        if (!$success) {
            $conditionDetailsFailure[0] = $conditionDetailsSuccess[0];
            $conditionDetailsFailure[1] = $conditionDetailsSuccess[1];
            if (!$checkAboveDistance) {
                $conditionDetailsFailure[2] = $this->throwName." n'atteint pas sa cible ! Il fallait un jet supérieur à ". $this->getDistanceTreshold() . ".";
            }
        }

        return new ConditionResult($success,$conditionDetailsSuccess,$conditionDetailsFailure,$actorRoll, $targetRoll, $actorTotal, $targetTotal);
    }

    protected function computeActor($actor, $dice)
    {
        $actorRollTraitValue = $actor->caracs->{$this->actorRollTrait};
        $actorRoll = $dice->roll($actorRollTraitValue);
        $actorTotal = array_sum($actorRoll);
        $distanceMalus = $this->getDistanceMalus();
        $distanceMalusTxt = ($distanceMalus) ? ' - '. $distanceMalus .' (Distance)' : '';
        $actorTotal = $actorTotal - $distanceMalus;
        $actorTotalTxt = $distanceMalus ? ' = '. $actorTotal : '';
        $actorTxt = 'Jet '. $actor->data->name .' = '. implode(' + ', $actorRoll) .' = '. array_sum($actorRoll) . $distanceMalusTxt . $actorTotalTxt;

        return array($actorRoll, $actorTotal, $actorTxt);
    }

    protected function computeTarget($target, $dice)
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
        
        $targetRoll = $dice->roll($targetRollTraitValue);
        $targetEsq = $this->carac->esquive ?? 0;
        $targetTotal = array_sum($targetRoll) - $target->data->malus;
        if($this->targetRollTrait == "cc" || $this->targetRollTrait == "agi"){
            $targetTotal = array_sum($targetRoll) - $targetEsq - $target->data->malus;
        }
        $targetTotal = array_sum($targetRoll) - $target->data->malus;
        $malusTxt = ($target->data->malus != 0) ? ' - '. $target->data->malus .' (Malus)' : '';
        $targetEsqTxt = ($targetEsq != 0) ? ' - '. $targetEsq .' (Esquive)' : '';
        $targetTotalTxt = $target->data->malus ? ' = '. $targetTotal : '';
        $targetTxt = 'Jet '. $target->data->name .' = '. array_sum($targetRoll) . $malusTxt . $targetTotalTxt;
        if($this->targetRollTrait == "cc" || $this->targetRollTrait == "agi"){
            $targetTxt = 'Jet '. $target->data->name .' = '. array_sum($targetRoll) . $targetEsqTxt . $malusTxt . $targetTotalTxt;
        }

        return array($targetRoll, $targetTotal, $targetTxt);
    }

    protected function getDistanceTreshold() : int {
        return 0;
    }

    protected function checkDistanceCondition(int $actorTotal): bool {
        return true;
    }
    
    protected function getDistanceMalus(): int {
        return 0;
    }

}