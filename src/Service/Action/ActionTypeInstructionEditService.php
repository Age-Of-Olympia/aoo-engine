<?php

namespace App\Service\Action;

use App\Action\OutcomeInstruction\OutcomeInstructionFactory;
use App\Action\Schema\ActionSchemaCatalog;
use App\Action\Schema\ParameterSchema;
use App\Entity\ActionTypeInstruction;
use App\Entity\TypeChildConfigInterface;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * Add/remove/edit the instructions configured on an action TYPE (the
 * type-defaults editor). The list/add/remove/save plumbing lives in
 * {@see AbstractTypeChildEditService}; this only supplies the instruction
 * specifics (entity, instruction-type field, schema, validation).
 *
 * @extends AbstractTypeChildEditService<ActionTypeInstruction>
 */
final class ActionTypeInstructionEditService extends AbstractTypeChildEditService
{
    private ActionTypeRegistry $registry;
    private ActionSchemaCatalog $catalog;

    public function __construct(
        ?EntityManagerInterface $entityManager = null,
        ?ActionTypeRegistry $registry = null,
        ?ActionSchemaCatalog $catalog = null,
        ?ParameterMerger $merger = null,
    ) {
        parent::__construct($entityManager, $merger);
        $this->registry = $registry ?? new ActionTypeRegistry();
        $this->catalog = $catalog ?? new ActionSchemaCatalog();
    }

    /** @return array<int, ActionTypeInstruction> ordered by orderIndex */
    public function instructionsForType(string $typeKey): array
    {
        return $this->forType($typeKey);
    }

    public function addInstruction(string $typeKey, string $instructionType): ActionTypeInstruction
    {
        return $this->add($typeKey, $instructionType);
    }

    public function removeInstruction(int $id): void
    {
        $this->remove($id);
    }

    protected function entityClass(): string
    {
        return ActionTypeInstruction::class;
    }

    protected function childTypeOf(TypeChildConfigInterface $entity): string
    {
        /** @var ActionTypeInstruction $entity */
        return $entity->getInstructionType();
    }

    protected function schemaFor(string $childType): ParameterSchema
    {
        return $this->catalog->schemaForOutcomeInstruction($childType);
    }

    protected function assertChildType(string $childType): void
    {
        if (!isset(OutcomeInstructionFactory::typeMap()[$childType])) {
            throw new InvalidArgumentException("Type d'instruction inconnu : {$childType}.");
        }
    }

    protected function assertScope(string $typeKey): void
    {
        if (!isset($this->registry->assignableTypes()[$typeKey])) {
            throw new InvalidArgumentException("Type d'action inconnu : {$typeKey}.");
        }
    }

    protected function makeChild(string $typeKey, string $childType, int $orderIndex): TypeChildConfigInterface
    {
        return (new ActionTypeInstruction())
            ->setTypeKey($typeKey)
            ->setInstructionType($childType)
            ->setParameters([])
            ->setOrderIndex($orderIndex);
    }
}
