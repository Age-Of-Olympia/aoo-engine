<?php
namespace App\Action\Condition;

use App\Action\OutcomeInstruction\MalusOutcomeInstruction;
use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Action\Condition\ConditionObject;
use Classes\Dice;
use Classes\View;

class ComputePureCondition extends BaseCondition
{
    protected int $distance;
    protected string $throwName = "Le tir";
    protected string $actorRollTrait;
    protected string $targetRollTrait;


    public function __construct() {
        array_push($this->preConditions, new DodgeCondition());
        array_push($this->preConditions, new NoBerserkCondition());
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        $preConditionResult = parent::check($actor, $target, $condition, $conditionObject);
        if (!$preConditionResult->isSuccess()) {
            return $preConditionResult;
        }

        $params = $condition->getParameters(); // e.g. { "max": 1 }
        $this->actorRollTrait = $params['actorRollType'] ?? null;
        $this->targetRollTrait = $params['targetRollType'] ?? null;
        $conditionObject->setActorRollBonus($params['actorRollBonus'] ?? 0);
        $conditionObject->setTargetRollBonus($params['targetRollBonus'] ?? 0);
        $conditionObject->setActorRollTrait($params['actorRollType'] ?? 0);
        $conditionObject->setTargetRollTrait($params['targetRollType'] ?? 0);
        $conditionObject->setActorAdvantage($params['actorAdvantage'] ?? false);
        $conditionObject->setTargetAdvantage($params['targetAdvantage'] ?? false);
        $conditionObject->setActorDisadvantage($params['actorDisadvantage'] ?? false);
        $conditionObject->setTargetDisadvantage($params['targetDisadvantage'] ?? false);
        $target->playerPassiveService->getPassivesByPlayerId($target->getId());

        foreach ($actor->playerPassiveService->getPassivesByPlayerId($actor->getId()) as $actorPassive) {
            if (in_array($this->actorRollTrait, $actorPassive->getTraits()) && ($actorPassive->getType() == "att" || $actorPassive->getType() == "mixte" )) {
                if($actor->playerPassiveService->checkPassiveConditionsByPlayerById($actor,$actorPassive,$conditionObject)){
                    if($actorPassive->getCarac() == "advantage"){
                        $conditionObject->setActorAdvantage(true);
                    }
                    else{
                        $conditionObject->addActorRollBonus($actor->playerPassiveService->getComputedValueByPlayerIdById($actor->id,$actorPassive->getId()));
                    }
                }
            }
        }

        foreach ($target->playerPassiveService->getPassivesByPlayerId($target->getId()) as $targetPassive) {
            if (in_array($this->targetRollTrait, $targetPassive->getTraits()) && ($targetPassive->getType() == "def" || $targetPassive->getType() == "mixte" )) {
                if($target->playerPassiveService->checkPassiveConditionsByPlayerById($target,$targetPassive,$conditionObject)){
                    if($targetPassive->getCarac() == "advantage"){
                        $conditionObject->setTargetAdvantage(true);
                    }
                    else{
                     $conditionObject->addTargetRollBonus($target->playerPassiveService->getComputedValueByPlayerIdById($target->id,$targetPassive->getName()));
                    }
                }
            }
        }

        if (!$target) {
            $errorMessages[0] = "Aucune cible n'a été spécifiée.";
            return new ConditionResult(success: false, conditionSuccessMessages:$errorMessages, conditionFailureMessages:array());
        }

        $this->distance = View::get_distance($actor->getCoords(), $target->getCoords());

        $result = $this->computeAttack($actor, $target, $conditionObject);

        if (!$result->isSuccess()) {
            $condition->getAction()->addAutomaticOutcomeInstruction(new MalusOutcomeInstruction());
        }

        return $result;
    }

    private function computeAttack(ActorInterface $actor, ?ActorInterface $target, ConditionObject $conditionObject): ConditionResult 
    {
        $success = false;
        $dice = new Dice(3);

        list($actorRoll, $actorTotal, $actorTxt) = $this->computeActor($actor, $dice, $conditionObject);
        $conditionDetailsSuccess[0] = $actorTxt;

        list($targetRoll, $targetTotal, $targetTxt) = $this->computeTarget($target, $dice, $conditionObject);
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

        return new ConditionResult($success,$conditionDetailsSuccess,$conditionDetailsFailure);
    }

    protected function computeActor($actor, $dice, $conditionObject)
    {
        $actorRollBonus = $conditionObject->getActorRollBonus();
        $actorRollTraitValue = $actor->caracs->{$this->actorRollTrait};
        $actorRoll = $dice->roll($actorRollTraitValue);
        if($conditionObject->getActorAdvantage() && $conditionObject->getActorDisadvantage()){
            // Do nothing if advantage and disadvantage
        }
        elseif($conditionObject->getActorAdvantage() || $conditionObject->getActorDisadvantage()){
            $actorRoll2 = $dice->roll($actorRollTraitValue);
            if($conditionObject->getActorAdvantage()){
                $actorRoll = max($actorRoll,$actorRoll2);
            }   
            else{
                $actorRoll = min($actorRoll,$actorRoll2);
            }
        }
        $bonus = $conditionObject->getActorRollBonus();
        $tooltipOtherTxt = !empty($actorRollBonus) ? 'Bonus de compétence : ' . $actorRollBonus . ' ' : '';
        $actorTotal = array_sum($actorRoll) + $bonus;
        $distanceMalus = $this->getDistanceMalus();
        $distanceMalusTxt = ($distanceMalus) ? ' - '. $distanceMalus .' (Distance)' : '';
        $actorTotal = $actorTotal - $distanceMalus;
        $actorTxt = 'Jet '. $actor->data->name .' = ' . '<span style="text-decoration: underline;" flow="up" tooltip="' . $distanceMalusTxt . (($distanceMalusTxt) ? ', ' . $tooltipOtherTxt : $tooltipOtherTxt) . '">' . $actorTotal . '</span> (Jet pur)';

        $conditionObject->setActorRoll($actorTotal);

        return array($actorRoll, $actorTotal, $actorTxt);
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
        
        $targetRoll = $dice->roll($targetRollTraitValue);
        if($conditionObject->getTargetAdvantage() && $conditionObject->getTargetDisadvantage()){
            // Do nothing if advantage and disadvantage
        }
        elseif($conditionObject->getTargetAdvantage() || $conditionObject->getTargetDisadvantage()){
            $targetRoll2 = $dice->roll($targetRollTraitValue);
            if($conditionObject->getTargetAdvantage()){
                $targetRoll = max($targetRoll,$targetRoll2);
            }   
            else{
                $targetRoll = min($targetRoll,$targetRoll2);
            }
        }
        $bonus = $conditionObject->getTargetRollBonus();
        $targetTotal = array_sum($targetRoll) + $bonus;
        $tooltipOtherTxt = !empty($bonus) ? 'Bonus de compétence : ' . $conditionObject->getTargetRollBonus() . ' ' : '';
        $targetOtherTxt = ($bonus != 0) ? ($bonus < 0 ? ' - '.abs($bonus) : $bonus) . ' (<span style="text-decoration: underline;" flow="up" tooltip="' . $tooltipOtherTxt+$bonus . '">Autre</span>) = ' . array_sum($targetRoll)+$bonus . ' (Jet pur)' : ' (Jet pur)';
        $targetTxt = 'Jet '. $target->data->name .' = '. array_sum($targetRoll) . $targetOtherTxt;

        $conditionObject->setTargetRoll($targetTotal);

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