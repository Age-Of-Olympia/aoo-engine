<?php

namespace App\Service\Action;

use App\Action\OutcomeInstruction\OutcomeInstructionFactory;
use App\Action\Schema\ActionSchemaCatalog;
use App\Action\Schema\ParameterSchema;
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
                $merged = $this->mergedParameters(
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
                    $merged = $this->mergedParameters(
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
     * Combine the typed schema fields with the free-form raw key→value editor for
     * keys the schema doesn't model. Raw keys come first so handlers that key off
     * the first parameter (e.g. ApplyStatus's effect-as-first-key) keep their lead
     * key. Returns null when neither part was submitted, leaving the entity alone.
     *
     * @param array<string, mixed>          $existing
     * @param array<string, mixed>|null     $typedPosted
     * @param array<int|string, mixed>|null $rawPosted
     * @return array<string, mixed>|null
     */
    private function mergedParameters(ParameterSchema $schema, array $existing, ?array $typedPosted, ?array $rawPosted): ?array
    {
        if ($typedPosted === null && $rawPosted === null) {
            return null;
        }

        $reserved = array_map(static fn($field): string => $field->key, $schema->fields());

        $typed = (!$schema->isEmpty() && $typedPosted !== null)
            ? $this->validator->coerce($schema, $typedPosted)
            : [];

        // When the raw editor wasn't posted, preserve any pre-existing keys the
        // schema doesn't own so a typed-only save never drops them.
        $raw = $rawPosted !== null
            ? $this->validator->coerceRaw($rawPosted, $reserved)
            : $this->leftover($existing, $reserved);

        return array_merge($raw, $typed);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<int, string>   $reserved
     * @return array<string, mixed>
     */
    private function leftover(array $params, array $reserved): array
    {
        return array_filter(
            $params,
            static fn($key): bool => !in_array((string) $key, $reserved, true),
            ARRAY_FILTER_USE_KEY
        );
    }
}
