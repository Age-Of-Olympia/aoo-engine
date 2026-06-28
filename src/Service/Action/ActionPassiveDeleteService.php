<?php

namespace App\Service\Action;

use App\Entity\ActionPassive;
use App\Entity\EntityManagerFactory;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Delete a passive. ActionPassive owns no relations, so a plain remove suffices
 * (players reference passives by name at runtime, not by FK).
 */
final class ActionPassiveDeleteService
{
    private EntityManagerInterface $entityManager;

    public function __construct(?EntityManagerInterface $entityManager = null)
    {
        $this->entityManager = $entityManager ?? EntityManagerFactory::getEntityManager();
    }

    public function delete(int $id): void
    {
        $passive = EntityFinder::orFail($this->entityManager, ActionPassive::class, $id, 'Passif');

        $this->entityManager->remove($passive);
        $this->entityManager->flush();
    }
}
