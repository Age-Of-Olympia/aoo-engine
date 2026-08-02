<?php
namespace App\Service;

use Classes\Player;
use App\Entity\ActionOutcome;
use App\Action\ActionResults;
use App\Action\Condition\ConditionRegistry;
use App\Action\Condition\ConditionObject;
use App\Action\Condition\ConditionResult;
use App\Entity\Action;
use App\Entity\ActionCondition;
use App\Entity\OutcomeInstruction;
use App\Interface\ConditionInterface;
use App\Service\Action\ActionLogResolver;
use App\Service\Action\ActionXpResolver;
use App\Service\Action\ActionTypeInstructionResolver;
use App\Service\Action\ActionTypePreconditionResolver;
use App\Service\Action\ConditionPreconditionResolver;
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
    private ?PlayerService $playerService;
    private ActionTypeInstructionResolver $typeInstructionResolver;
    private ActionTypePreconditionResolver $preconditionResolver;
    private ConditionPreconditionResolver $conditionPreconditionResolver;
    private ActionLogResolver $logResolver;
    private ActionXpResolver $xpResolver;
    private bool $simulationMode = false;
    // Same for actor ? Possible to loose pv on action and die ?
    private int $initialTargetPv;
    private int $finalTargetPv;
    private bool $blocked = false;
    private ConditionObject $conditionObject;

    public function __construct(Action $action, Player $actor, Player $target, bool $simulationMode = false, ?ActionTypeInstructionResolver $typeInstructionResolver = null, ?ActionTypePreconditionResolver $preconditionResolver = null, ?ConditionPreconditionResolver $conditionPreconditionResolver = null, ?ActionLogResolver $logResolver = null, ?ActionXpResolver $xpResolver = null){
        $this->conditionRegistry = new ConditionRegistry();
        $this->typeInstructionResolver = $typeInstructionResolver ?? new ActionTypeInstructionResolver();
        $this->logResolver = $logResolver ?? new ActionLogResolver();
        $this->xpResolver = $xpResolver ?? new ActionXpResolver();
        $this->preconditionResolver = $preconditionResolver ?? new ActionTypePreconditionResolver();
        $this->conditionPreconditionResolver = $conditionPreconditionResolver ?? new ConditionPreconditionResolver();
        $this->conditionResultsArray = array();
        $this->outcomeResultsArray = array();
        $this->conditionsToPay = array();
        $this->actor = $actor;
        $this->target = $target;
        $this->action = $action;
        $this->simulationMode = $simulationMode;
        // PlayerService only drives the updateLastActionTime persistence side-effect,
        // which is skipped in simulation — so don't construct it (it would hit the DB).
        $this->playerService = $simulationMode ? null : new PlayerService($actor->id);
        $this->initialTargetPv = $target->getRemaining('pv');
        $this->conditionObject = new ConditionObject();
        $this->conditionObject->setAction($this->action);
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
            if (!$this->simulationMode && $this->action->activateAntiBerserk()) {
                $this->playerService->updateLastActionTime();
            }

            // 3) apply costs
            $costsResultsArray = $this->applyCosts();

            // 4) calculate XP — from the action's per-type rule (action_type_xp).
            $xpResultsArray = $this->xpResolver->calculate($this->action, $this->globalConditionsResult, $this->actor, $this->target);
            if(!empty($xpResultsArray["actor"])){            
                $this->actor->put_xp($xpResultsArray["actor"]);
            }
            
            if(!empty($xpResultsArray["target"])){
                $this->target->put_xp($xpResultsArray["target"]);
            }

            // 4b) apply the rule's actor mutations (e.g. training spends one
            // energie per side) — kept out of the pure XP calculation.
            $this->xpResolver->applyMutations($this->action, $this->globalConditionsResult, $this->actor, $this->target);

        }
        
        // 5) LOG — from the action's per-type templates (action_type_logs).
        $logsArray = $this->logResolver->resolve($this->action, $this->actor, $this->target);

        // La capture d'arène n'est PLUS déclenchée ici. Elle l'était avant que
        // action.php n'écrive les logs de l'action, si bien que l'image existait
        // avant la ligne qui l'explique et que rien ne les reliait. Elle se
        // déclenche donc désormais depuis action.php, après les Log::put, où le
        // texte des events est disponible sans requête ni jointure.

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

        // Inherited type-level instructions (data-driven defaults for the action
        // type, e.g. an attack's adrenaline).
        foreach ($this->typeInstructionResolver->resolve($this->action) as $outcomeInstruction) {
            $this->applyActionOutcomeInstruction($outcomeInstruction);
        }

        // Instructions added dynamically during this execution — notably the
        // MalusOutcomeInstruction a compute condition adds on a miss. These are
        // distinct from the type-level defaults, so both must run.
        foreach ($this->action->getAutomaticOutcomeInstructions() as $outcomeInstruction) {
            $this->applyActionOutcomeInstruction($outcomeInstruction);
        }
    }

    private function checkConditions(): bool
    {
        // Type-level / global preconditions (e.g. the enfers block) run first,
        // resolved from config through the action's type ancestry — the
        // data-driven replacement for what BaseCondition used to inject in code.
        // They run even when the action has no conditions of its own.
        $preconditions = $this->preconditionResolver->resolve($this->action);
        $globalConditionsResult = $this->runConditions($preconditions);
        if ($this->blocked) {
            return $globalConditionsResult;
        }

        return $this->runConditions($this->action->getConditions()->toArray()) && $globalConditionsResult;
    }

    /**
     * @param iterable<\App\Entity\ActionCondition> $conditions
     */
    private function runConditions(iterable $conditions): bool
    {
        $result = true;
        foreach ($conditions as $condEntity) {
            $condition = $this->conditionRegistry->getCondition($condEntity->getConditionType());
            if (!$condition) {
                error_log("Condition not found : ". $condEntity->getConditionType());
                return false;
            }

            $conditionResult = $this->checkWithPreconditions($condition, $condEntity);
            $result = $result && $conditionResult->isSuccess();
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

        return $result;
    }

    /**
     * Runs the condition-keyed preconditions (Dodge/NoBerserk/Obstacle/AntiSpell)
     * resolved from config for $condEntity's type, then the condition itself —
     * the data-driven replacement for what the *Compute conditions used to
     * array_push into their own preConditions. Mirrors the old
     * BaseCondition::checkPreconditions: every precondition runs and the messages
     * aggregate, but a failure short-circuits the condition's check (so a failed
     * Dodge skips the roll and its miss-malus). Conditions with no preconditions
     * configured (everything but the compute family) just run their own check.
     */
    private function checkWithPreconditions(ConditionInterface $condition, ActionCondition $condEntity): ConditionResult
    {
        $preconditions = $this->conditionPreconditionResolver->resolve($condEntity->getConditionType());
        if ($preconditions === []) {
            return $condition->check($this->actor, $this->target, $condEntity, $this->conditionObject);
        }

        $success = true;
        $successMessages = [];
        $failureMessages = [];
        foreach ($preconditions as $precondition) {
            $preResult = $precondition->check($this->actor, $this->target, $condEntity, $this->conditionObject);
            if ($preResult->isSuccess()) {
                $successMessages = array_merge($successMessages, $preResult->getConditionSuccessMessages());
            } else {
                $failureMessages = array_merge($failureMessages, $preResult->getConditionFailureMessages());
            }
            $success = $success && $preResult->isSuccess();
        }

        if (!$success) {
            return new ConditionResult(false, $successMessages, $failureMessages);
        }

        return $condition->check($this->actor, $this->target, $condEntity, $this->conditionObject);
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
        $result = $outcomeInstruction->execute($this->actor, $this->target, $this->conditionObject);
        array_push($this->outcomeResultsArray, $result);
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
