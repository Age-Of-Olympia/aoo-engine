<?php
namespace App\Action\Condition;

use App\Action\Condition\ConditionInterface;
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

class ComputeCondition extends BaseCondition
{
    
    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition): ConditionResult
    {
        if (!$target) {
            $errorMessages[0] = "Aucune cible n'a été spécifiée.";
            return new ConditionResult(success: false, conditionSuccessMessages:$errorMessages);
        }

        $params = $condition->getParameters(); // e.g. {"actorRollType":"cc", "targetRollType": "cc/agi", "equipmentPosition": "hand1"}
        
        $actorRollType = $params['actorRollType'];
        
        switch ($actorRollType) {
            case Roll::cc->value :
                $result = $this->computeMeleeAttack($actor, $target);
                break;
            case Roll::ct->value :
                $result = $this->computeDistanceAttack($actor, $target, $params);
                break;
            // case Roll::fm :
            //     $this->computeSpellAttack($actor, $target, $params);
            //     break;
            default:
                # code...
                break;
        }

        return $result;
    }

    private function computeMeleeAttack(ActorInterface $actor, ?ActorInterface $target): ConditionResult 
    {
        $success = false;
        $dice = new Dice(3);

        $actorRollTraitValue = $actor->caracs->cc;

        $option1 = $target->caracs->cc;
        $option2 = $target->caracs->agi;
        $targetRollTraitValue = max($option1, $option2);

        $actorRoll = $dice->roll($actorRollTraitValue);
        $targetRoll = $dice->roll($targetRollTraitValue);

        $playerFat = floor($actor->data->fatigue / FAT_EVERY);
        $targetFat = floor($target->data->fatigue / FAT_EVERY);

        $actorTotal = array_sum($actorRoll) - $playerFat;
        $targetTotal = array_sum($targetRoll) - $targetFat - $target->data->malus;

        $distanceMalus = null;

        $malusTxt = ($target->data->malus != 0) ? ' - '. $target->data->malus .' (Malus)' : '';

        $playerFatTxt = ($playerFat != 0) ? ' - '. $playerFat .' (Fatigue)' : '';
        $targetFatTxt = ($targetFat != 0) ? ' - '. $targetFat .' (Fatigue)' : '';

        $playerTotalTxt = ($playerFat || $distanceMalus) ? ' = '. $actorTotal : '';
        $targetTotalTxt = ($targetFat || $target->data->malus) ? ' = '. $targetTotal : '';

        $conditionDetails[0] = 'Jet '. $actor->data->name .' = '. implode(' + ', $actorRoll) .' = '. array_sum($actorRoll) . $playerFatTxt . $playerTotalTxt;
        $conditionDetails[1] = 'Jet '. $target->data->name .' = '. array_sum($targetRoll) . $malusTxt . $targetFatTxt . $targetTotalTxt;

        if(!AUTO_FAIL && ($actorTotal >= $targetTotal))
        {
            $success = true;
        }

        return new ConditionResult($success,$conditionDetails,$conditionDetails,$actorRoll, $targetRoll, $actorTotal, $targetTotal);
    }

    private function computeDistanceAttack(ActorInterface $actor, ?ActorInterface $target): ConditionResult 
    {
        $success = false;
        $dice = new Dice(3);

        $actorRollTraitValue = $actor->caracs->ct;

        $option1 = floor( (3/4*$target->caracs->cc) + (1/4*$target->caracs->agi) );
        $option2 = floor( (1/4*$target->caracs->cc) + (3/4*$target->caracs->agi) );
        $targetRollTraitValue = max($option1, $option2);

        $actorRoll = $dice->roll($actorRollTraitValue);
        $targetRoll = $dice->roll($targetRollTraitValue);

        $playerFat = floor($actor->data->fatigue / FAT_EVERY);
        $targetFat = floor($target->data->fatigue / FAT_EVERY);

        $actorTotal = array_sum($actorRoll) - $playerFat;
        $targetTotal = array_sum($targetRoll) - $targetFat - $target->data->malus;

        $distance = View::get_distance($actor->getCoords(), $target->getCoords());

        $distanceMalus = 0;
        if($distance > 2){
            $distanceMalus = ($distance - 2) * 3;
        }

        $distanceMalusTxt = ($distanceMalus) ? ' - '. $distanceMalus .' (Distance)' : '';
        $malusTxt = ($target->data->malus != 0) ? ' - '. $target->data->malus .' (Malus)' : '';

        $playerFatTxt = ($playerFat != 0) ? ' - '. $playerFat .' (Fatigue)' : '';
        $targetFatTxt = ($targetFat != 0) ? ' - '. $targetFat .' (Fatigue)' : '';

        $playerTotalTxt = ($playerFat || $distanceMalus) ? ' = '. $actorTotal : '';
        $targetTotalTxt = ($targetFat || $target->data->malus) ? ' = '. $targetTotal : '';

        $conditionDetails[0] = 'Jet '. $actor->data->name .' = '. implode(' + ', $actorRoll) .' = '. array_sum($actorRoll) . $distanceMalusTxt . $playerFatTxt . $playerTotalTxt;
        $conditionDetails[1] = 'Jet '. $target->data->name .' = '. array_sum($targetRoll) . $malusTxt . $targetFatTxt . $targetTotalTxt;

        if(!AUTO_FAIL && ($actorTotal >= $targetTotal))
        {
            $success = true;
        }

        return new ConditionResult($success,$conditionDetails,$conditionDetails,$actorRoll, $targetRoll, $actorTotal, $targetTotal);
    }

}