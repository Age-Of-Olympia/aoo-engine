<?php

namespace App\Service\Action;

use App\Entity\Action;
use App\Entity\EntityManagerFactory;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

final class ActionDeleteService
{
    private EntityManagerInterface $entityManager;

    public function __construct(?EntityManagerInterface $entityManager = null)
    {
        $this->entityManager = $entityManager ?? EntityManagerFactory::getEntityManager();
    }

    /**
     * Delete an action and everything it owns: its conditions, outcomes and the
     * outcomes' instructions cascade (orphanRemoval). The race links are detached
     * first through the owning side (Race) so no race_actions rows are orphaned.
     */
    public function delete(int $actionId): void
    {
        $action = EntityFinder::orFail($this->entityManager, Action::class, $actionId, 'Action');

        $this->entityManager->beginTransaction();
        try {
            foreach ($action->getRaces() as $race) {
                $race->removeAction($action);
            }
            $this->entityManager->remove($action);
            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (Throwable $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }
    }
}
