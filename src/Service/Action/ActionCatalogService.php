<?php

namespace App\Service\Action;

use App\Entity\Action;
use App\Factory\EntityManagerFactory;
use Doctrine\ORM\EntityManagerInterface;

final class ActionCatalogService
{
    private EntityManagerInterface $entityManager;

    public function __construct(?EntityManagerInterface $entityManager = null)
    {
        $this->entityManager = $entityManager ?? EntityManagerFactory::getEntityManager();
    }

    /**
     * @return array<int, Action>
     */
    public function listActions(): array
    {
        return $this->entityManager->createQuery(
            'SELECT a FROM App\Entity\Action a ORDER BY a.category ASC, a.level ASC, a.name ASC'
        )->getResult();
    }

    public function getActionById(int $id): ?Action
    {
        return $this->entityManager->find(Action::class, $id);
    }

    /**
     * Look up an action by its natural key (name). Identity used by import.
     */
    public function findByName(string $name): ?Action
    {
        return $this->entityManager->getRepository(Action::class)->findOneBy(['name' => $name]);
    }
}
