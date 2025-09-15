<?php
namespace App\Service;

use Classes\Player;
use App\Entity\ActionOutcome;
use App\Action\ActionResults;
use App\Action\Condition\ConditionRegistry;
use App\Entity\Action;
use App\Entity\OutcomeInstruction;
use Exception;

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

        $costsResultsArray = array();
        $xpResultsArray = array();
        if (!$this->blocked) {
            $this->action->initAutomaticOutcomeInstructions();

            // 2) apply each effect
            $this->applyOutcomes();
            $this->finalTargetPv = $this->target->getRemaining('pv');

            // update Last Action Time (used on new turn to set antiberserk time)
            if ($this->action->activateAntiBerserk()) {
                $this->playerService->updateLastActionTime();
            }

            // 3) apply costs
            $costsResultsArray = $this->applyCosts();

            // 4) calculate XP
            $xpResultsArray = $this->action->calculateXp($this->globalConditionsResult, $this->actor, $this->target);
            if(!empty($xpResultsArray["actor"])){            
                $this->actor->put_xp($xpResultsArray["actor"]);
            }
            
            if(!empty($xpResultsArray["target"])){            
                $this->target->put_xp($xpResultsArray["target"]);
            }

        }
        
        // 5) LOG
        $logsArray = $this->action->getLogMessages($this->actor, $this->target);

        // 6) Trigger automatic screenshot if action occurred on arene_s2
        $this->triggerAutomaticScreenshot();

        // contains conditionsResults, effectsResults, costsResults, xpResults and logs
        return new ActionResults($this->globalConditionsResult, $this->blocked, $this->conditionResultsArray, $this->outcomeResultsArray, $costsResultsArray, $xpResultsArray, $logsArray);
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
            foreach ($this->action->getOnSuccessOutcomes() as $outcomeEntity) {
                $this->applyActionOutcome($outcomeEntity);
            }
        } else {
            foreach ($this->action->getOnSuccessOutcomes(false) as $outcomeEntity) {
                $this->applyActionOutcome($outcomeEntity);
            }
        }

        foreach ($this->action->getAutomaticOutcomeInstructions() as $outcomeInstruction) {
            $this->applyActionOutcomeInstruction($outcomeInstruction);
        }
    }

    private function checkConditions(): bool
    {
        $globalConditionsResult = true;
        foreach ($this->action->getConditions() as $condEntity) {
            $condition = $this->conditionRegistry->getCondition($condEntity->getConditionType());
            if (!$condition) {
                error_log("Condition not found : ". $condEntity->getConditionType());
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
            $this->applyActionOutcomeInstruction($instruction);
        }
    }

    private function applyActionOutcomeInstruction(OutcomeInstruction $outcomeInstruction): void
    {
        $result = $outcomeInstruction->execute($this->actor, $this->target);
        array_push($this->outcomeResultsArray, $result);
    }

    private function triggerAutomaticScreenshot(): void
    {
        try {
            $screenshotService = new ScreenshotService();
            $actionName = $this->action->getName() ?? 'unknown';

            $result = $screenshotService->generateAutomaticScreenshot($this->actor, $actionName);

            if (!$result['success'] && $result['error'] !== 'Action not on arene_s2 map') {
                error_log("Automatic screenshot failed: " . $result['error']);
            }
        } catch (Exception $e) {
            error_log("Error triggering automatic screenshot: " . $e->getMessage());
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
