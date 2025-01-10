<?php
namespace App\Service;

use App\Entity\Action;
use Player;
use App\Entity\EffectInstruction;
use App\Entity\ActionEffect;
use App\Action\ActionResult;
use App\Action\Condition\ConditionRegistry;

class ActionExecutor
{
    public function __construct(
        private ConditionRegistry $conditionRegistry,
        private EffectInstructionExecutor $instructionExecutor
    ) {
    }

    public function executeAction(Action $action, Player $actor, ?Player $target = null): ActionResult
    {
        // 1) Check conditions
        foreach ($action->getConditions() as $condEntity) {
            $condition = $this->conditionRegistry->getCondition($condEntity->getConditionType());
            if (!$condition) {
                return new ActionResult(false, "Unknown condition type: {$condEntity->getConditionType()}");
            }

            if (!$condition->check($actor, $target, $condEntity)) {
                return new ActionResult(false, $condition->getErrorMessage() ?? "Condition failed");
            }
        }

        // 2) Conditions pass => apply each effect
        foreach ($action->getEffects() as $effectEntity) {
            $this->applyActionEffect($effectEntity, $actor, $target);
        }

        return new ActionResult(true, "Action executed successfully");
    }

    private function applyActionEffect(ActionEffect $effectEntity, Player $actor, ?Player $target): void
    {
        // Decide who is receiving the effect
        $recipient = $effectEntity->getApplyToSelf() ? $actor : ($target ?? $actor);

        // Sort instructions by orderIndex if relevant:
        $sortedInstructions = $effectEntity->getInstructions()->toArray();
        usort($sortedInstructions, fn($a, $b) => $a->getOrderIndex() <=> $b->getOrderIndex());

        // Execute instructions in order
        foreach ($sortedInstructions as $instruction) {
            $this->instructionExecutor->executeInstruction($recipient, $instruction);
        }
    }
}
