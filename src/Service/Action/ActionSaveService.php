<?php

namespace App\Service\Action;

use App\Action\OutcomeInstruction\OutcomeInstructionFactory;
use App\Action\Schema\ActionSchemaCatalog;
use App\Entity\Action;
use App\Entity\EntityManagerFactory;
use App\Service\OutcomeInstructionService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Throwable;

final class ActionSaveService
{
    private EntityManagerInterface $entityManager;
    private ActionSchemaCatalog $catalog;
    private ParameterMerger $merger;
    private OutcomeInstructionService $instructionService;

    public function __construct(
        ?EntityManagerInterface $entityManager = null,
        ?ActionSchemaCatalog $catalog = null,
        ?ActionParameterValidator $validator = null,
        ?OutcomeInstructionService $instructionService = null,
    ) {
        $this->entityManager = $entityManager ?? EntityManagerFactory::getEntityManager();
        $this->catalog = $catalog ?? new ActionSchemaCatalog();
        $this->merger = new ParameterMerger($validator ?? new ActionParameterValidator());
        $this->instructionService = $instructionService ?? new OutcomeInstructionService();
    }

    /**
     * Update the parameters of an action's existing conditions and outcome
     * instructions in place (slice 1: parameters only, no add/remove/reorder).
     *
     * @param array<int|string, array<string, mixed>>     $conditionParams   conditionId => posted typed params
     * @param array<int|string, array<string, mixed>>     $instructionParams instructionId => posted typed params
     * @param array<int|string, array<int|string, mixed>> $conditionRaw      conditionId => posted raw rows
     * @param array<int|string, array<int|string, mixed>> $instructionRaw    instructionId => posted raw rows
     */
    public function saveParameters(
        int $actionId,
        array $conditionParams,
        array $instructionParams,
        array $conditionRaw = [],
        array $instructionRaw = [],
    ): void {
        $action = $this->entityManager->find(Action::class, $actionId);
        if ($action === null) {
            throw new InvalidArgumentException("Action introuvable : {$actionId}.");
        }

        $this->entityManager->beginTransaction();
        try {
            foreach ($action->getConditions() as $condition) {
                $merged = $this->merger->merge(
                    $this->catalog->schemaForCondition($condition->getConditionType()),
                    $condition->getParameters() ?? [],
                    $conditionParams[$condition->getId()] ?? null,
                    $conditionRaw[$condition->getId()] ?? null
                );
                if ($merged !== null) {
                    $condition->setParameters($merged);
                }
            }

            foreach ($action->getOutcomes() as $outcome) {
                foreach ($this->instructionService->getOutcomeInstructionsByOutcome((int) $outcome->getId()) as $instruction) {
                    $merged = $this->merger->merge(
                        $this->catalog->schemaForOutcomeInstruction(OutcomeInstructionFactory::typeOf($instruction)),
                        $instruction->getParameters() ?? [],
                        $instructionParams[$instruction->getId()] ?? null,
                        $instructionRaw[$instruction->getId()] ?? null
                    );
                    if ($merged !== null) {
                        $instruction->setParameters($merged);
                    }
                }
            }

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (Throwable $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }
    }

    /**
     * Set each outcome's apply_to_self flag (who it applies to: the actor when
     * true, otherwise the target). Drives the action's derived targeting scope
     * (see App\Service\Action\ActionTargeting). Outcomes absent from the payload
     * are left untouched; flushes only when something changed.
     *
     * @param array<int|string, mixed> $outcomeSelf outcomeId => "0"|"1"
     */
    public function saveOutcomeTargets(int $actionId, array $outcomeSelf): void
    {
        $action = $this->entityManager->find(Action::class, $actionId);
        if ($action === null) {
            throw new InvalidArgumentException("Action introuvable : {$actionId}.");
        }

        $changed = false;
        foreach ($action->getOutcomes() as $outcome) {
            $id = (int) $outcome->getId();
            if (!array_key_exists($id, $outcomeSelf)) {
                continue;
            }
            $applyToSelf = (bool) (int) $outcomeSelf[$id];
            if ($applyToSelf !== $outcome->getApplyToSelf()) {
                $outcome->setApplyToSelf($applyToSelf);
                $changed = true;
            }
        }

        if ($changed) {
            $this->entityManager->flush();
        }
    }

    /**
     * Set an action's display icon (an RPG-Awesome class such as
     * ra-crossed-swords, stored without the leading "ra-" requirement — the
     * value is taken verbatim). A no-op when the icon is unchanged.
     */
    public function saveIcon(int $actionId, string $icon): void
    {
        $action = $this->entityManager->find(Action::class, $actionId);
        if ($action === null) {
            throw new InvalidArgumentException("Action introuvable : {$actionId}.");
        }

        $icon = trim($icon);
        if ($icon === $action->getIcon()) {
            return;
        }

        $action->setIcon($icon);
        $this->entityManager->flush();
    }
}
