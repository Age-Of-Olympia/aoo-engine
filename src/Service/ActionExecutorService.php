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
use App\Action\OutcomeInstruction\OutcomeResult;

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
    private string $deathOutput = '';

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

            /* Effects before costs — rest and status magnitudes read the
             * LIVE pool, so the debit must not precede them. But an
             * outcome that throws midway must not leave its applied
             * effects unpaid: costs and the anti-berserk mark belong to
             * the ATTEMPT; XP and logs to the completion. */
            try {
                // 2) apply each effect
                $this->applyOutcomes();
            } finally {
                $this->finalTargetPv = $this->target->getRemaining('pv');

                // 3) apply costs
                $costsResultsArray = $this->applyCosts();

                // update Last Action Time (used on new turn to set antiberserk time)
                if (!$this->simulationMode && $this->action->activateAntiBerserk()) {
                    $this->playerService->updateLastActionTime();
                }

                /* Acting on a construction IS using it, and using it holds
                 * it together: the horizon moves, nothing is healed. Silent
                 * for anything not enrolled, so acting on a Tiled wall never
                 * enrols it.
                 *
                 * An ATTACK is not use. The executor already knows the
                 * target's life before and after, so the question needs no
                 * new notion of hostility: a blow that took life does not
                 * maintain what it damaged. */
                if (!$this->simulationMode && $this->finalTargetPv >= $this->initialTargetPv) {
                    (new \App\Service\Decay\StructureDecayService())->touch((int) $this->target->id);
                }
            }

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

        // 6) The arena capture is triggered from action.php, after the Log::put
        //    calls, where the event text is available.

        // 7) Deaths — the engine's business, so every caller gets them.
        $this->resolveDeaths();

        // contains conditionsResults, effectsResults, costsResults, xpResults and logs
        return new ActionResults($this->globalConditionsResult, $this->blocked, $this->conditionResultsArray, $this->outcomeResultsArray, $costsResultsArray, $xpResultsArray, $logsArray);
    }

    /** The target's life once the outcomes were applied — before any death. */
    public function getFinalTargetPv(): int
    {
        return $this->finalTargetPv ?? $this->initialTargetPv;
    }

    /** The kill report, for a caller that renders a page. */
    public function getDeathOutput(): string
    {
        return $this->deathOutput;
    }

    /**
     * Who dies of this action: the target of the blow, with the kill going
     * to the actor; the actor of its own effects; a self-targeted action
     * is a self death.
     *
     * Here rather than in action.php so that every caller of the executor
     * inherits the rule — a move that starts an action, an API, a future
     * NPC turn.
     *
     * ponytail: PlayerService still ECHOES the kill report, so capture it
     * and let the caller place it. Drop the buffer when that report
     * becomes a view.
     */
    private function resolveDeaths(): void
    {
        if ($this->simulationMode) {
            return;
        }

        $onSelf = (int) $this->actor->id === (int) $this->target->id;

        ob_start();

        if (!$onSelf && $this->actor->getRemaining('pv') < 1) {
            PlayerService::processSelfDeath($this->actor, 'à ses propres effets');
            echo '<b><font color="red">Vous succombez à vos propres effets.</font></b>';
            \App\View\OnHideReloadView::render($this->actor);
        }

        $targetPv = $this->target->getRemaining('pv');
        if ($targetPv < 1 && $targetPv !== $this->initialTargetPv) {
            if ($onSelf) {
                PlayerService::processSelfDeath($this->target, 'à ses propres effets');
            } else {
                PlayerService::ProcessTargetDeath($this->actor, $this->target);
            }
        }

        $this->deathOutput = (string) ob_get_clean();
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
            $this->applyEquippedItemsEffects('hit');
        } else {
            foreach ($this->action->getOnSuccessOutcomes(false) as $outcomeEntity) {
                $this->applyActionOutcome($outcomeEntity);
            }
            $this->applyEquippedItemsEffects('miss');
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

            /* Two sources of refusal, never conflated: what the condition IS
             * (its own immutable flag) and what just happened (a blocking
             * precondition refused this particular attempt). */
            if (!$conditionResult->isSuccess() && ($condEntity->isBlocking() || $conditionResult->isBlocking())) {
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
        $blocking = false;
        $successMessages = [];
        $failureMessages = [];
        foreach ($preconditions as $precondition) {
            $preResult = $precondition->handler()->check($this->actor, $this->target, $condEntity, $this->conditionObject);
            if ($preResult->isSuccess()) {
                $successMessages = array_merge($successMessages, $preResult->getConditionSuccessMessages());
            } else {
                $failureMessages = array_merge($failureMessages, $preResult->getConditionFailureMessages());
                // The config row says whether THIS failure refuses the action.
                $blocking = $blocking || $precondition->isBlocking();
            }
            $success = $success && $preResult->isSuccess();
        }

        if (!$success) {
            return new ConditionResult(false, $successMessages, $failureMessages, $blocking);
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

    /**
     * The bearer's weapon effects for this outcome ('hit' or 'miss'), each
     * landed on its receiver: the bearer or the target.
     */
    private function applyEquippedItemsEffects(string $outcome): void
    {
        if ($this->action instanceof \App\Action\MeleeAction || $this->action instanceof \App\Action\DistanceAction) {

            $effectList = $this->conditionObject->getAttackEffects() ?? [];
            $effectService = new EffectService();
            $outcomeSuccessMessages = [];

            foreach ($effectList as $effect) {

                if (($effect->outcome ?? 'hit') !== $outcome) {
                    continue;
                }

                $duration = (int) ($effect->duration ?? 1);

                foreach ($this->strikeReceivers((string) ($effect->target ?? 'target')) as $receiver) {
                    // add_effect, not the raw insert: the cancellation cycle and pv_on_apply come with it.
                    $receiver->add_effect((string) $effect->name, $duration);
                    $outcomeSuccessMessages[] = $effectService->landingMessage((string) $effect->name, $receiver->data->name, $this->actor->data->name, $duration);
                }
            }

            if (!empty($outcomeSuccessMessages)) {
                /* ActionResultsView shows an outcome's failure messages when
                 * the action failed: a miss-triggered effect files its line
                 * there, or the player never reads what just hit them. */
                $this->outcomeResultsArray[] = $outcome === 'hit'
                    ? new OutcomeResult(true, outcomeSuccessMessages: $outcomeSuccessMessages, outcomeFailureMessages: [])
                    : new OutcomeResult(true, outcomeSuccessMessages: [], outcomeFailureMessages: $outcomeSuccessMessages);
            }
        }
    }


    /**
     * Who a weapon effect lands on: the bearer or the target.
     *
     * @return list<Player>
     */
    private function strikeReceivers(string $target): array
    {
        return [$target === 'self' ? $this->actor : $this->target];
    }
}
