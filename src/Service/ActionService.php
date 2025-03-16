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
    public function getActionByTypeByName(string $type, ?string $name = null): ?ActionInterface
    {
        $dql = 'SELECT action FROM App\\Action\\'.$type.'Action action';
        if ($name != null) {
            $dql = $dql . ' WHERE action.name = :name';
        }
        $query = $this->entityManager->createQuery($dql);
        if ($name != null) {
            $query->setParameter("name", $name);
        }
        
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
        $Action = $this->getActionByTypeByName($type);
        return $Action ? $Action->getId() : null;
    }
}