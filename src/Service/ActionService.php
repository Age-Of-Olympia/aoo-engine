<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use App\Entity\Action;
use App\Interface\ActionInterface;
use Exception;

class ActionService
{
    private $entityManager;

    public function __construct()
    {
        // Fetch the entity manager from your custom factory
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    /**
     * Returns a Action entity that matches the given type, or null if not found.
     */
    public function getActionByType(string $type): ?ActionInterface
    {
        //$query = $this->entityManager->createQuery('SELECT action FROM App\\Entity\\Action action WHERE action INSTANCE OF App\\Action\\'.$type.'Action');
        $query = $this->entityManager->createQuery('SELECT action FROM App\\Action\\'.$type.'Action action');
        $log = $query->getSQL();
        $action = $query->getSingleResult();
        
        if (!$action) {
            throw new Exception("Action not found for type: $type");
        }
        return $action;
    }

    /**
     * Returns the ID of the Action that matches the given type, or null if not found.
     */
    public function getActionIdByType(string $type): ?int
    {
        $Action = $this->getActionByType($type);
        return $Action ? $Action->getId() : null;
    }
}