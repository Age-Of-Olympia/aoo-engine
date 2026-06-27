<?php

namespace App\Service\Action;

use App\Action\Condition\ConditionRegistry;
use App\Action\Schema\ActionSchemaCatalog;
use App\Action\Schema\ParameterSchema;
use App\Entity\ActionTypePrecondition;
use App\Entity\TypeChildConfig;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * Add/remove/edit the preconditions configured on an action TYPE — or globally
 * (an empty type key applies to every action, e.g. the "Plan: enfers" rule).
 * Shares its plumbing with the instructions editor via
 * {@see AbstractTypeChildEditService}; adds a per-row blocking flag and allows the
 * global scope.
 *
 * @extends AbstractTypeChildEditService<ActionTypePrecondition>
 */
final class ActionTypePreconditionEditService extends AbstractTypeChildEditService
{
    public const GLOBAL_SCOPE = '';

    private ActionTypeRegistry $registry;
    private ActionSchemaCatalog $catalog;
    private ConditionRegistry $conditions;

    public function __construct(
        ?EntityManagerInterface $entityManager = null,
        ?ActionTypeRegistry $registry = null,
        ?ActionSchemaCatalog $catalog = null,
        ?ConditionRegistry $conditions = null,
        ?ParameterMerger $merger = null,
    ) {
        parent::__construct($entityManager, $merger);
        $this->registry = $registry ?? new ActionTypeRegistry();
        $this->catalog = $catalog ?? new ActionSchemaCatalog();
        $this->conditions = $conditions ?? new ConditionRegistry();
    }

    /** @return array<int, ActionTypePrecondition> ordered by orderIndex */
    public function preconditionsForType(string $typeKey): array
    {
        return $this->forType($typeKey);
    }

    public function addPrecondition(string $typeKey, string $conditionType): ActionTypePrecondition
    {
        return $this->add($typeKey, $conditionType);
    }

    public function removePrecondition(int $id): void
    {
        $this->remove($id);
    }

    protected function entityClass(): string
    {
        return ActionTypePrecondition::class;
    }

    protected function childTypeOf(TypeChildConfig $entity): string
    {
        /** @var ActionTypePrecondition $entity */
        return $entity->getConditionType();
    }

    protected function schemaFor(string $childType): ParameterSchema
    {
        return $this->catalog->schemaForCondition($childType);
    }

    protected function assertChildType(string $childType): void
    {
        if ($this->conditions->getCondition($childType) === null) {
            throw new InvalidArgumentException("Type de condition inconnu : {$childType}.");
        }
    }

    protected function assertScope(string $typeKey): void
    {
        if ($typeKey !== self::GLOBAL_SCOPE && !isset($this->registry->assignableTypes()[$typeKey])) {
            throw new InvalidArgumentException("Type d'action inconnu : {$typeKey}.");
        }
    }

    protected function makeChild(string $typeKey, string $childType, int $orderIndex): TypeChildConfig
    {
        return (new ActionTypePrecondition())
            ->setTypeKey($typeKey)
            ->setConditionType($childType)
            ->setParameters([])
            ->setBlocking(true)
            ->setOrderIndex($orderIndex);
    }

    /**
     * Apply the per-row "bloquant" flag. Checkboxes only post when checked, so an
     * absent id means not blocking.
     *
     * @param array<int|string, mixed> $extraById
     */
    protected function applyExtra(TypeChildConfig $child, int|string|null $id, array $extraById): void
    {
        /** @var ActionTypePrecondition $child */
        $child->setBlocking(!empty($extraById[$id]));
    }
}
