<?php
namespace App\Service;

use Player;
use App\Entity\ActionOutcome;
use App\Action\ActionResults;
use App\Action\Condition\ConditionRegistry;
use App\Entity\Action;

class ActionExecutorService
{
    private ConditionRegistry $conditionRegistry;
    private bool $globalConditionsResult;
    private array $conditionResultsArray;
    private array $outcomeResultsArray;
    private array $conditionsToPay;
    private Player $actor;
    private Player $target;
    private Action $action;
    private PlayerService $playerService;
    // Same for actor ? Possible to loose pv on action and die ?
    private int $initialTargetPv;
    private int $finalTargetPv;
    private bool $blocked = false;
    
    public function __construct(Action $action, Player $actor, Player $target){
        $this->conditionRegistry = new ConditionRegistry();
        $this->conditionResultsArray = array();
        $this->outcomeResultsArray = array();
        $this->conditionsToPay = array();
        $this->actor = $actor;
        $this->target = $target;
        $this->action = $action;
        $this->playerService = new PlayerService($actor->id);
        $this->initialTargetPv = $target->getRemaining('pv');
    }

    public function executeAction(): ActionResults
    {
        // 1) Check conditions
        $this->globalConditionsResult = $this->checkConditions();

        // 2) apply each effect
        $this->applyOutcomes();
        $this->finalTargetPv = $this->target->getRemaining('pv');

        // update Last Action Time (used on new turn to set antiberserk time)
        $this->playerService->updateLastActionTime();

        $costsResultsArray = array();
        $xpResultsArray = array();
        if (!$this->blocked) {
            // 3) apply costs
            $costsResultsArray = $this->applyCosts();

            // 4) calculate XP
            $xpResultsArray = $this->action->calculateXp($this->globalConditionsResult, $this->actor, $this->target);
        }
        
        // 5) LOG
        $logsArray = $this->action->getLogMessages($this->actor, $this->target);

        // contains conditionsResults, effectsResults, costsResults, xpResults and logs
        return new ActionResults($this->globalConditionsResult, $this->conditionResultsArray, $this->outcomeResultsArray, $costsResultsArray, $xpResultsArray, $logsArray);
    }

    private function applyCosts(): array
    {
        $result = array();
        foreach ($this->conditionsToPay as $conditionToPay) {
            $condition = $this->conditionRegistry->getCondition($conditionToPay->getConditionType());
            $resultsArray = $condition->applyCosts($this->actor, $this->target, $conditionToPay);
            foreach ($resultsArray as $subResult) {
                array_push($result, $subResult);
            }
        }
        return $result;
    }

    private function applyOutcomes(): void
    {
        if ($this->globalConditionsResult) {
            foreach ($this->action->getOnSuccessOutcomess() as $outcomEntity) {
                $this->applyActionOutcome($outcomEntity, $this->actor, $this->target);
            }
        } else {
            foreach ($this->action->getOnSuccessOutcomess(false) as $outcomEntity) {
                $this->applyActionOutcome($outcomEntity, $this->actor, $this->target);
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
                $this->blocked = true;
                break;
            }
        
            // this condition has a cost and must be removed if the action is performed
            if ($condition->toRemove()) {
                array_push($this->conditionsToPay, $condEntity);
            }
        }

        return $globalConditionsResult;
    }

    private function applyActionOutcome(ActionOutcome $outcomeEntity): void
    {
        $outcomeInstructionService = new OutcomeInstructionService();
        $instructions = $outcomeInstructionService->getOutcomeInstructionsByOutcome($outcomeEntity->getId());

        // Execute instructions in order
        foreach ($instructions as $instruction) {
            $result = $instruction->execute($this->actor, $this->target);
            array_push($this->outcomeResultsArray, $result);
        }
    }

    public function getInitialTargetPv(): int
    {
        return $this->initialTargetPv;
    }

    public function getFinalTargetPv(): int
    {
        return $this->finalTargetPv;
    }
}
