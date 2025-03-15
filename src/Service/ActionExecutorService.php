<?php
namespace App\Service;

use Player;
use App\Entity\ActionEffect;
use App\Action\ActionResults;
use App\Action\Condition\ConditionRegistry;
use App\EffectInstruction\EffectInstructionFactory;
use App\Entity\Action;
use App\Interface\ActionInterface;

class ActionExecutorService
{
    private ConditionRegistry $conditionRegistry;
    private bool $globalConditionsResult;
    private array $conditionResultsArray;
    private array $effectResultsArray;
    private array $conditionsToPay;
    private Player $actor;
    private Player $target;
    private Action $action;
    private PlayerService $playerService;
    // Same for actor ? Possible to loose pv on action and die ?
    private int $initialTargetPv;
    private int $finalTargetPv;
    
    public function __construct(Action $action, Player $actor, Player $target){
        $this->conditionRegistry = new ConditionRegistry();
        $this->conditionResultsArray = array();
        $this->effectResultsArray = array();
        $this->conditionsToPay = array();
        $this->actor = $actor;
        $this->target = $target;
        $this->action = $action;
        $this->playerService = new PlayerService($actor->id);
        $this->initialTargetPv = $target->getRemaining('pv');
    }

    public function executeAction(): ActionResults
    {
        // ajouter des conditions génériques ? posséder une action, ne pas être dans les enfers ?
        
        // 1) Check conditions
        $this->globalConditionsResult = $this->checkConditions();

        // 2) apply each effect
        $this->applyEffects();
        $this->finalTargetPv = $this->target->getRemaining('pv');

        // 3) apply costs
        $costsResultsArray = $this->applyCosts();

        // update Last Action Time (used on new turn to set antiberserk time)
        $this->playerService->updateLastActionTime();

        // 4) calculate XP
        $xpResultsArray = $this->action->calculateXp($this->globalConditionsResult, $this->actor, $this->target);
        // 5) LOG
        $logsArray = $this->action->getLogMessages($this->actor, $this->target);

        // contains conditionsResults, effectsResults, costsResults, xpResults and logs
        return new ActionResults($this->globalConditionsResult, $this->conditionResultsArray, $this->effectResultsArray, $costsResultsArray, $xpResultsArray, $logsArray);
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
        //EffectInstructionFactory::initialize('src/Action/EffectInstruction');
        $effectInstructionService = new EffectInstructionService();
        $instructions = $effectInstructionService->getEffectInstructionsByEffect($effectEntity->getId());

        // Execute instructions in order
        foreach ($instructions as $instruction) {
            $result = $instruction->execute($this->actor, $this->target);
            array_push($this->effectResultsArray, $result);
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
