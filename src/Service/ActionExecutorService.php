<?php
namespace App\Service;

use App\Entity\Action;
use Player;
use App\Entity\ActionEffect;
use App\Action\ActionResult;
use App\Action\Condition\ConditionRegistry;

class ActionExecutorService
{
    private ConditionRegistry $conditionRegistry;
    private EffectInstructionExecutorService $effectInstructionExecutor;
    
    public function __construct() {
        $this->conditionRegistry = new ConditionRegistry();
        $this->effectInstructionExecutor = new EffectInstructionExecutorService();
    }


    public function executeAction(Action $action, Player $actor, ?Player $target = null): ActionResult
    {
        $conditionResultsArray = array();
        $globalConditionsResult = true;
        // 1) Check conditions
        foreach ($action->getConditions() as $condEntity) {
            $condition = $this->conditionRegistry->getCondition($condEntity->getConditionType());
            if (!$condition) {
                return new ActionResult(false, "Unknown condition type: {$condEntity->getConditionType()}");
            }

            $conditionResult = $condition->check($actor, $target, $condEntity);
            $globalConditionsResult = $globalConditionsResult && $conditionResult->isSuccess();
            array_push($conditionResultsArray, $conditionResult);

            if (!$conditionResult->isSuccess() && $condEntity->isBlocking()) {
                break;
            }
        }

        // 2) apply each effect
        if ($globalConditionsResult) {
            foreach ($action->getOnSuccessEffects() as $effectEntity) {
                $this->applyActionEffect($effectEntity, $actor, $target);
            }
        } else {
            foreach ($action->getOnSuccessEffects(false) as $effectEntity) {
                $this->applyActionEffect($effectEntity, $actor, $target);
            }
        }

        // 3) return results


        // should contain conditionsResults & effectsResults !!!
        return new ActionResult(true, "Action executed successfully");
    }

    private function applyActionEffect(ActionEffect $effectEntity, Player $actor, ?Player $target): void // add effect results
    {
        // Decide who is receiving the effect
        $recipient = $effectEntity->getApplyToSelf() ? $actor : ($target ?? $actor);

        // Sort instructions by orderIndex if relevant:
        $sortedInstructions = $effectEntity->getInstructions()->toArray();
        usort($sortedInstructions, fn($a, $b) => $a->getOrderIndex() <=> $b->getOrderIndex());

        // Execute instructions in order
        foreach ($sortedInstructions as $instruction) {
            $this->effectInstructionExecutor->executeInstruction($recipient, $instruction);
        }
    }
}
