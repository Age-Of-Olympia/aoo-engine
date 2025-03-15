<?php
namespace App\Action\Condition;
use Player;
use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use Dice;
use View;

enum Roll: string
{
    case cc = "cc";
    case ct = "ct";
    case fm = "fm";
    case cc_agi = "cc_agi";
}

abstract class ComputeCondition extends BaseCondition
{
    protected int $distance;
    protected string $throwName = "tir";
    
    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition): ConditionResult
    {
        if (!$target) {
            $errorMessages[0] = "Aucune cible n'a été spécifiée.";
            return new ConditionResult(success: false, conditionSuccessMessages:$errorMessages);
        }

        $this->distance = View::get_distance($actor->getCoords(), $target->getCoords());

        $result = $this->computeAttack($actor, $target);

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
                $conditionDetailsFailure[2] = "Le ".$this->throwName." n'atteint pas sa cible ! Il fallait un jet supérieur à ". $this->getDistanceTreshold() . ".";
            }
        }

        return new ConditionResult($success,$conditionDetailsSuccess,$conditionDetailsFailure,$actorRoll, $targetRoll, $actorTotal, $targetTotal);
    }

    protected function computeTarget($target, $dice)
    {
        $option1 = $target->caracs->cc;
        $option2 = $target->caracs->agi;
        $targetRollTraitValue = max($option1, $option2);
        $targetRoll = $dice->roll($targetRollTraitValue);
        $targetFat = floor($target->data->fatigue / FAT_EVERY);
        $targetTotal = array_sum($targetRoll) - $targetFat - $target->data->malus;
        $malusTxt = ($target->data->malus != 0) ? ' - '. $target->data->malus .' (Malus)' : '';
        $targetFatTxt = ($targetFat != 0) ? ' - '. $targetFat .' (Fatigue)' : '';
        $targetTotalTxt = ($targetFat || $target->data->malus) ? ' = '. $targetTotal : '';
        $targetTxt = 'Jet '. $target->data->name .' = '. array_sum($targetRoll) . $malusTxt . $targetFatTxt . $targetTotalTxt;

        return array($targetRoll, $targetTotal, $targetTxt);
    }

    protected function computeActor($actor, $dice)
    {
        $actorRollTraitValue = $actor->caracs->cc;
        $actorRoll = $dice->roll($actorRollTraitValue);
        $actorFat = floor($actor->data->fatigue / FAT_EVERY);
        $actorTotal = array_sum($actorRoll) - $actorFat;
        $actorFatTxt = ($actorFat != 0) ? ' - '. $actorFat .' (Fatigue)' : '';
        $distanceMalus = $this->getDistanceMalus();
        $distanceMalusTxt = ($distanceMalus) ? ' - '. $distanceMalus .' (Distance)' : '';
        $actorTotal = $actorTotal - $distanceMalus;
        $actorTotalTxt = ($actorFat || $distanceMalus) ? ' = '. $actorTotal : '';
        $actorTxt = 'Jet '. $actor->data->name .' = '. implode(' + ', $actorRoll) .' = '. array_sum($actorRoll) . $distanceMalusTxt . $actorFatTxt . $actorTotalTxt;

        return array($actorRoll, $actorTotal, $actorTxt);
    }

    protected function getDistanceTreshold() : int {
        return floor(($this->distance) * 2.5);
    }

    protected function checkDistanceCondition(int $actorTotal): bool {
        $checkAboveDistance = true;
        if($this->distance > 1){
            $distanceTreshold = $this->getDistanceTreshold();
            $checkAboveDistance = $actorTotal >= $distanceTreshold;
        }
        return $checkAboveDistance;
    }
    
    protected function getDistanceMalus(): int {
        $distanceMalus = 0;
        if($this->distance > 2){
            $distanceMalus = ($this->distance - 2) * 3;
        }
        return $distanceMalus;
    }

}