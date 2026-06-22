<?php

namespace App\Service\Action;

use App\Action\OutcomeInstruction\OutcomeInstructionFactory;
use App\Action\Schema\ActionSchemaCatalog;
use App\Entity\ActionTypeInstruction;
use App\Entity\EntityManagerFactory;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Throwable;

/**
 * Add/remove/edit the instructions configured on an action TYPE (the
 * type-defaults editor). Mirrors the per-action editor but keyed on a type key.
 */
final class ActionTypeInstructionEditService
{
    private EntityManagerInterface $entityManager;
    private ActionTypeRegistry $registry;
    private ActionSchemaCatalog $catalog;
    private ParameterMerger $merger;

    public function __construct(
        ?EntityManagerInterface $entityManager = null,
        ?ActionTypeRegistry $registry = null,
        ?ActionSchemaCatalog $catalog = null,
        ?ParameterMerger $merger = null,
    ) {
        $this->entityManager = $entityManager ?? EntityManagerFactory::getEntityManager();
        $this->registry = $registry ?? new ActionTypeRegistry();
        $this->catalog = $catalog ?? new ActionSchemaCatalog();
        $this->merger = $merger ?? new ParameterMerger();
    }

    /**
     * @return array<int, ActionTypeInstruction> ordered by orderIndex
     */
    public function instructionsForType(string $typeKey): array
    {
        return $this->entityManager->getRepository(ActionTypeInstruction::class)
            ->findBy(['typeKey' => $typeKey], ['orderIndex' => 'ASC']);
    }

    public function addInstruction(string $typeKey, string $instructionType): ActionTypeInstruction
    {
        if (!isset($this->registry->assignableTypes()[$typeKey])) {
            throw new InvalidArgumentException("Type d'action inconnu : {$typeKey}.");
        }
        if (!isset(OutcomeInstructionFactory::typeMap()[$instructionType])) {
            throw new InvalidArgumentException("Type d'instruction inconnu : {$instructionType}.");
        }

        $instruction = (new ActionTypeInstruction())
            ->setTypeKey($typeKey)
            ->setInstructionType($instructionType)
            ->setParameters([])
            ->setOrderIndex(count($this->instructionsForType($typeKey)));

        $this->entityManager->persist($instruction);
        $this->entityManager->flush();

        return $instruction;
    }

    public function removeInstruction(int $id): void
    {
        $instruction = $this->entityManager->find(ActionTypeInstruction::class, $id);
        if ($instruction === null) {
            throw new InvalidArgumentException("Instruction introuvable : {$id}.");
        }

        $this->entityManager->remove($instruction);
        $this->entityManager->flush();
    }

    /**
     * @param array<int|string, array<string, mixed>>     $typedById instructionId => posted typed params
     * @param array<int|string, array<int|string, mixed>> $rawById   instructionId => posted raw rows
     */
    public function saveParameters(string $typeKey, array $typedById, array $rawById = []): void
    {
        $this->entityManager->beginTransaction();
        try {
            foreach ($this->instructionsForType($typeKey) as $instruction) {
                $merged = $this->merger->merge(
                    $this->catalog->schemaForOutcomeInstruction($instruction->getInstructionType()),
                    $instruction->getParameters() ?? [],
                    $typedById[$instruction->getId()] ?? null,
                    $rawById[$instruction->getId()] ?? null,
                );
                if ($merged !== null) {
                    $instruction->setParameters($merged);
                }
            }

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (Throwable $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }
    }
}
