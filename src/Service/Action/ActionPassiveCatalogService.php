<?php

namespace App\Service\Action;

use App\Entity\ActionPassive;
use App\Factory\EntityManagerFactory;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Read access to passives for the passive workbench (list + single load).
 */
final class ActionPassiveCatalogService
{
    private EntityManagerInterface $entityManager;

    public function __construct(?EntityManagerInterface $entityManager = null)
    {
        $this->entityManager = $entityManager ?? EntityManagerFactory::getEntityManager();
    }

    /**
     * @return array<int, ActionPassive>
     */
    public function listPassives(): array
    {
        return $this->entityManager->getRepository(ActionPassive::class)
            ->findBy([], ['category' => 'ASC', 'name' => 'ASC']);
    }

    public function getById(int $id): ?ActionPassive
    {
        return $this->entityManager->find(ActionPassive::class, $id);
    }

    /**
     * Look up a passive by its natural key (name). Identity used by import.
     */
    public function findByName(string $name): ?ActionPassive
    {
        return $this->entityManager->getRepository(ActionPassive::class)->findOneBy(['name' => $name]);
    }
}
