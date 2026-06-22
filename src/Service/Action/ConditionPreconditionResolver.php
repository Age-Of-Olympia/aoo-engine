<?php

namespace App\Service\Action;

use App\Action\Condition\ConditionRegistry;
use App\Entity\ActionConditionPrecondition;
use App\Entity\EntityManagerFactory;
use App\Interface\ConditionInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Resolves the preconditions a CONDITION type carries, as ready-to-run condition
 * handlers — the data-driven replacement for the preconditions the *Compute
 * conditions array_push into their preConditions in code. For a parent condition
 * type (e.g. "MeleeCompute") it loads the configured {@see ActionConditionPrecondition}
 * rows in order and resolves each precondition type to its handler via the
 * {@see ConditionRegistry}.
 *
 * The handlers read their parameters from the parent condition they precede (as
 * Dodge/NoBerserk/Obstacle/AntiSpell always have), so only the type + order are
 * needed to reproduce the current behaviour.
 */
final class ConditionPreconditionResolver
{
    private EntityManagerInterface $entityManager;
    private ConditionRegistry $registry;

    public function __construct(?EntityManagerInterface $entityManager = null, ?ConditionRegistry $registry = null)
    {
        $this->entityManager = $entityManager ?? EntityManagerFactory::getEntityManager();
        $this->registry = $registry ?? new ConditionRegistry();
    }

    /**
     * @return array<int, ConditionInterface>
     */
    public function resolve(string $conditionType): array
    {
        /** @var array<int, ActionConditionPrecondition> $rows */
        $rows = $this->entityManager->getRepository(ActionConditionPrecondition::class)
            ->findBy(['parentConditionType' => $conditionType], ['orderIndex' => 'ASC']);

        $handlers = [];
        foreach ($rows as $row) {
            $handler = $this->registry->getCondition($row->getPreconditionType());
            if ($handler !== null) {
                $handlers[] = $handler;
            }
        }

        return $handlers;
    }
}
