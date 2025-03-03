<?php
namespace App\Service;

use Player;
use App\Entity\ActionEffect;
use App\Action\ActionResults;
use App\Action\Condition\ConditionRegistry;
use App\Entity\Action;
use App\Interface\ActionInterface;

class ActionExecutorService
{
    private ConditionRegistry $conditionRegistry;
    private EffectInstructionExecutorService $effectInstructionExecutor;
    private bool $globalConditionsResult;
    private array $conditionResultsArray;
    private array $effectResultsArray;
    private array $conditionsToPay;
    private Player $actor;
    private Player $target;
    private Action $action;
    
    public function __construct(Action $action, Player $actor, Player $target){
        $this->conditionRegistry = new ConditionRegistry();
        $this->effectInstructionExecutor = new EffectInstructionExecutorService();
        $this->conditionResultsArray = array();
        $this->effectResultsArray = array();
        $this->conditionsToPay = array();
        $this->actor = $actor;
        $this->target = $target;
        $this->action = $action;
    }

    public function executeAction(): ActionResults
    {
        // ajouter des conditions génériques ? posséder une action, ne pas être dans les enfers ?
        
        // 1) Check conditions
        $this->globalConditionsResult = $this->checkConditions();

        // 2) apply each effect
        $this->applyEffects();

        // 3) apply costs
        $this->applyCosts();

        // Update Anti-zerk

        // 4) calculate XP
        $xpResultsArray = $this->action->calculateXp($this->globalConditionsResult, $this->actor, $this->target);
        // 5) LOG
        $logsArray = $this->action->getLogMessages($this->actor, $this->target);

        // should contain conditionsResults, effectsResults and costs results !!!
        return new ActionResults($this->globalConditionsResult, $this->conditionResultsArray, $this->effectResultsArray, $xpResultsArray, $logsArray);
    }

    private function applyCosts()
    {
        foreach ($this->conditionsToPay as $conditionToPay) {
            foreach ($conditionToPay->getParameters() as $key => $value) {
                $this->actor->put_bonus([$key => -$value]);
            }
        }
    }

    private function applyEffects(): void
    {
        if ($this->globalConditionsResult) {
            foreach ($this->action->getOnSuccessEffects() as $effectEntity) {
                $this->applyActionEffect($effectEntity, $this->actor, $this->target);
            }
        } else {
            foreach ($this->action->getOnSuccessEffects(false) as $effectEntity) {
                $this->applyActionEffect($effectEntity, $this->actor, $this->target);
            }
        }
    }

    private function checkConditions(): bool
    {
        $globalConditionsResult = true;
        foreach ($this->action->getActionConditions() as $condEntity) {
            $condition = $this->conditionRegistry->getCondition($condEntity->getConditionType());
            if (!$condition) {
                return false;
            }
        
            $conditionResult = $condition->check($this->actor, $this->target, $condEntity);
            $globalConditionsResult = $globalConditionsResult && $conditionResult->isSuccess();
            array_push($this->conditionResultsArray, $conditionResult);
        
            if (!$conditionResult->isSuccess() && $condEntity->isBlocking()) {
                break;
            }
        
            // this condition has a cost and must be removed if the action is performed
            if ($condition->toRemove()) {
                array_push($this->conditionsToPay, $condEntity);
            }
        }

        return $globalConditionsResult;
    }

    private function applyActionEffect(ActionEffect $effectEntity): void
    {
        // Decide who is receiving the effect
        //$recipient = $effectEntity->getApplyToSelf() ? $actor : ($target ?? $actor);

        // Sort instructions by orderIndex if relevant:
        $sortedInstructions = $effectEntity->getInstructions()->toArray();
        usort($sortedInstructions, fn($a, $b) => $a->getOrderIndex() <=> $b->getOrderIndex());

        // Execute instructions in order
        foreach ($sortedInstructions as $instruction) {
            array_push($this->effectResultsArray, $this->effectInstructionExecutor->executeInstruction($this->actor, $this->target, $instruction));
        }
    }

    public function getActionDetails(): array
    {
        if ($this->globalConditionsResult) {

        }
        return array();
    }
}
