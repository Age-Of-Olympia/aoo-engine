<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use App\Entity\Action;

class ActionService
{
    private $entityManager;

    public function __construct()
    {
        // Fetch the entity manager from your custom factory
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    /**
     * Returns a Action entity that matches the given name, or null if not found.
     */
    public function getActionByName(string $name): ?Action
    {
        $repo = $this->entityManager->getRepository(Action::class);
        return $repo->findOneBy(['name' => $name]);
    }

    /**
     * Returns the ID of the Action that matches the given name, or null if not found.
     */
    public function getActionIdByName(string $name): ?int
    {
        $Action = $this->getActionByName($name);
        return $Action ? $Action->getId() : null;
    }
}