<?php

namespace App\Service\Action;

use App\Action\Schema\ActionSchemaCatalog;
use App\Entity\Action;
use App\Entity\EntityManagerFactory;
use App\Service\OutcomeInstructionService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use ReflectionClass;
use Throwable;

final class ActionSaveService
{
    private EntityManagerInterface $entityManager;
    private ActionSchemaCatalog $catalog;
    private ActionParameterValidator $validator;
    private OutcomeInstructionService $instructionService;

    public function __construct(
        ?EntityManagerInterface $entityManager = null,
        ?ActionSchemaCatalog $catalog = null,
        ?ActionParameterValidator $validator = null,
        ?OutcomeInstructionService $instructionService = null,
    ) {
        $this->entityManager = $entityManager ?? EntityManagerFactory::getEntityManager();
        $this->catalog = $catalog ?? new ActionSchemaCatalog();
        $this->validator = $validator ?? new ActionParameterValidator();
        $this->instructionService = $instructionService ?? new OutcomeInstructionService();
    }

    /**
     * Update the parameters of an action's existing conditions and outcome
     * instructions in place (slice 1: parameters only, no add/remove/reorder).
     *
     * @param array<int|string, array<string, mixed>> $conditionParams  conditionId => posted params
     * @param array<int|string, array<string, mixed>> $instructionParams instructionId => posted params
     */
    public function saveParameters(int $actionId, array $conditionParams, array $instructionParams): void
    {
        $action = $this->entityManager->find(Action::class, $actionId);
        if ($action === null) {
            throw new InvalidArgumentException("Action introuvable : {$actionId}.");
        }

        $this->entityManager->beginTransaction();
        try {
            foreach ($action->getConditions() as $condition) {
                $posted = $conditionParams[$condition->getId()] ?? null;
                $schema = $this->catalog->schemaForCondition($condition->getConditionType());
                if ($posted === null || $schema->isEmpty()) {
                    continue;
                }
                $condition->setParameters(array_merge(
                    $condition->getParameters() ?? [],
                    $this->validator->coerce($schema, $posted)
                ));
            }

            foreach ($action->getOutcomes() as $outcome) {
                foreach ($this->instructionService->getOutcomeInstructionsByOutcome((int) $outcome->getId()) as $instruction) {
                    $posted = $instructionParams[$instruction->getId()] ?? null;
                    $schema = $this->catalog->schemaForOutcomeInstruction($this->instructionType($instruction));
                    if ($posted === null || $schema->isEmpty()) {
                        continue;
                    }
                    $instruction->setParameters(array_merge(
                        $instruction->getParameters() ?? [],
                        $this->validator->coerce($schema, $posted)
                    ));
                }
            }

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (Throwable $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }
    }

    private function instructionType(object $instruction): string
    {
        return strtolower(substr((new ReflectionClass($instruction))->getShortName(), 0, -18));
    }
}
