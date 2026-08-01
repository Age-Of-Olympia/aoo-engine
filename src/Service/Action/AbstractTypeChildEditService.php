<?php

namespace App\Service\Action;

use App\Action\Schema\ParameterSchema;
use App\Factory\EntityManagerFactory;
use App\Interface\TypeChildConfigInterface;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Throwable;

/**
 * Shared add/remove/edit logic for the type-scoped config rows of the
 * type-defaults page (instructions and preconditions). Subclasses supply the
 * entity, the "which condition/instruction" field, the parameter schema and the
 * validation; everything else — list, count, add, remove, save-params — lives
 * here so the two editors don't diverge.
 *
 * @template T of TypeChildConfigInterface
 */
abstract class AbstractTypeChildEditService
{
    protected EntityManagerInterface $entityManager;
    protected ParameterMerger $merger;

    public function __construct(?EntityManagerInterface $entityManager = null, ?ParameterMerger $merger = null)
    {
        $this->entityManager = $entityManager ?? EntityManagerFactory::getEntityManager();
        $this->merger = $merger ?? new ParameterMerger();
    }

    /** @return class-string<T> */
    abstract protected function entityClass(): string;

    /** @param T $entity */
    abstract protected function childTypeOf(TypeChildConfigInterface $entity): string;

    abstract protected function schemaFor(string $childType): ParameterSchema;

    /** @throws InvalidArgumentException when the child type is unknown */
    abstract protected function assertChildType(string $childType): void;

    /** @throws InvalidArgumentException when the scope (type key) is invalid */
    abstract protected function assertScope(string $typeKey): void;

    /** @return T a new, unpersisted row for the given scope/child type */
    abstract protected function makeChild(string $typeKey, string $childType, int $orderIndex): TypeChildConfigInterface;

    /** @return array<int, T> ordered by orderIndex */
    public function forType(string $typeKey): array
    {
        return $this->entityManager->getRepository($this->entityClass())
            ->findBy(['typeKey' => $typeKey], ['orderIndex' => 'ASC']);
    }

    /**
     * How many rows each scope owns directly (for the tree rail's badges).
     *
     * @return array<string, int> typeKey => count
     */
    public function countsByType(): array
    {
        $counts = [];
        foreach ($this->entityManager->getRepository($this->entityClass())->findAll() as $row) {
            /** @var T $row */
            $key = $row->getTypeKey();
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }

    /** @return T */
    public function add(string $typeKey, string $childType): TypeChildConfigInterface
    {
        $this->assertScope($typeKey);
        $this->assertChildType($childType);

        $child = $this->makeChild($typeKey, $childType, count($this->forType($typeKey)));
        $this->entityManager->persist($child);
        $this->entityManager->flush();

        return $child;
    }

    public function remove(int $id): void
    {
        $child = EntityFinder::orFail($this->entityManager, $this->entityClass(), $id, 'Élément');

        $this->entityManager->remove($child);
        $this->entityManager->flush();
    }

    /**
     * Merge posted typed + raw params over each row's existing ones. $extraById
     * carries any per-kind extra field (e.g. precondition blocking), applied via
     * {@see applyExtra()}.
     *
     * @param array<int|string, array<string, mixed>>     $typedById rowId => posted typed params
     * @param array<int|string, array<int|string, mixed>> $rawById   rowId => posted raw rows
     * @param array<int|string, mixed>                    $extraById rowId => extra value
     */
    public function saveParameters(string $typeKey, array $typedById, array $rawById = [], array $extraById = []): void
    {
        $this->entityManager->beginTransaction();
        try {
            foreach ($this->forType($typeKey) as $child) {
                $id = $child->getId();
                $merged = $this->merger->merge(
                    $this->schemaFor($this->childTypeOf($child)),
                    $child->getParameters() ?? [],
                    $typedById[$id] ?? null,
                    $rawById[$id] ?? null,
                );
                if ($merged !== null) {
                    $child->setParameters($merged);
                }
                $this->applyExtra($child, $id, $extraById);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (Throwable $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }
    }

    /**
     * Hook for a subclass-specific per-row field (no-op by default).
     *
     * @param T                        $child
     * @param array<int|string, mixed> $extraById
     */
    protected function applyExtra(TypeChildConfigInterface $child, int|string|null $id, array $extraById): void
    {
    }
}
