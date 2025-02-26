<?php
namespace App\Service;

use App\Entity\Action;
use Player;
use App\Entity\ActionEffect;
use App\Action\ActionResults;
use App\Action\Condition\BaseCondition;
use App\Action\Condition\ConditionRegistry;
use App\Action\Effect\EffectResult;

class ActionExecutorService
{
    private ConditionRegistry $conditionRegistry;
    private EffectInstructionExecutorService $effectInstructionExecutor;
    
    public function __construct() {
        $this->conditionRegistry = new ConditionRegistry();
        $this->effectInstructionExecutor = new EffectInstructionExecutorService();
    }


    public function executeAction(Action $action, Player $actor, ?Player $target): ActionResults
    {
        // ajouter des conditions génériques ? posséder une action, ne pas être dans les enfers ?
        $conditionResultsArray = array();
        $globalConditionsResult = true;
        $conditionsToPay = array();
        // 1) Check conditions
        foreach ($action->getActionConditions() as $condEntity) {
            $condition = $this->conditionRegistry->getCondition($condEntity->getConditionType());
            if (!$condition) {
                return new ActionResults(false, array(), array(), array());
            }

            $conditionResult = $condition->check($actor, $target, $condEntity);
            $globalConditionsResult = $globalConditionsResult && $conditionResult->isSuccess();
            array_push($conditionResultsArray, $conditionResult);

            if (!$conditionResult->isSuccess() && $condEntity->isBlocking()) {
                break;
            }

            // this condition has a cost and must be removed if the action is performed
            if ($condition->toRemove()) {
                array_push($conditionsToPay, $condEntity);
            }
        }

        // 2) apply each effect
        if ($globalConditionsResult) {
            foreach ($action->getOnSuccessEffects() as $effectEntity) {
                $effectResultsArray = $this->applyActionEffect($effectEntity, $actor, $target);
            }
        } else {
            foreach ($action->getOnSuccessEffects(false) as $effectEntity) {
                $effectResultsArray = $this->applyActionEffect($effectEntity, $actor, $target);
            }
        }

        // 3) apply costs
        foreach ($conditionsToPay as $conditionToPay) {
            foreach ($conditionToPay->getParameters() as $key => $value) {
                $actor->put_bonus([$key => -$value]);
            }
        }

        // Update Anti-zerk

        // 4) calculate XP

        // 5) LOG
        $logsArray = array();

        // should contain conditionsResults, effectsResults and costs results !!!
        return new ActionResults(true, $conditionResultsArray, $effectResultsArray, $logsArray);
    }

    private function applyActionEffect(ActionEffect $effectEntity, Player $actor, ?Player $target): array
    {
        // Decide who is receiving the effect
        //$recipient = $effectEntity->getApplyToSelf() ? $actor : ($target ?? $actor);
        $effectResults = array();
        // Sort instructions by orderIndex if relevant:
        $sortedInstructions = $effectEntity->getInstructions()->toArray();
        usort($sortedInstructions, fn($a, $b) => $a->getOrderIndex() <=> $b->getOrderIndex());

        // Execute instructions in order
        foreach ($sortedInstructions as $instruction) {
            array_push($effectResults, $this->effectInstructionExecutor->executeInstruction($actor, $target, $instruction));
        }

        return $effectResults;
    }
}
