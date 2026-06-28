<?php

namespace App\Service\Action;

use App\Action\Condition\ConditionRegistry;
use App\Entity\Action;
use App\Entity\ActionCondition;
use App\Entity\EntityManagerFactory;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

final class ActionConditionEditService
{
    private EntityManagerInterface $entityManager;
    private ConditionRegistry $registry;

    public function __construct(?EntityManagerInterface $entityManager = null, ?ConditionRegistry $registry = null)
    {
        $this->entityManager = $entityManager ?? EntityManagerFactory::getEntityManager();
        $this->registry = $registry ?? new ConditionRegistry();
    }

    /**
     * The condition types that can be added, sourced from the ConditionRegistry.
     *
     * @return array<int, string>
     */
    public function availableTypes(): array
    {
        return $this->registry->getTypes();
    }

    /**
     * Add an empty condition of the given type to an action; its parameters are
     * configured afterwards via the normal save flow.
     */
    public function addCondition(int $actionId, string $conditionType): ActionCondition
    {
        $action = EntityFinder::orFail($this->entityManager, Action::class, $actionId, 'Action');
        if (!in_array($conditionType, $this->registry->getTypes(), true)) {
            throw new InvalidArgumentException("Type de condition inconnu : {$conditionType}.");
        }

        $condition = new ActionCondition();
        $condition->setConditionType($conditionType);
        $condition->setParameters([]);
        $condition->setBlocking(false);
        $condition->setAction($action);
        $action->addCondition($condition);

        $this->entityManager->persist($condition);
        $this->entityManager->flush();

        return $condition;
    }

    public function removeCondition(int $conditionId): void
    {
        $condition = EntityFinder::orFail($this->entityManager, ActionCondition::class, $conditionId, 'Condition');

        // Detach from the owning action so orphanRemoval deletes the row.
        $action = $condition->getAction();
        if ($action !== null) {
            $action->removeCondition($condition);
        } else {
            $this->entityManager->remove($condition);
        }

        $this->entityManager->flush();
    }
}
