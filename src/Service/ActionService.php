<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use App\Entity\Action;
use App\Interface\ActionInterface;
use Doctrine\ORM\Query\ResultSetMapping;
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
    public function getActionByName(string $name): ?ActionInterface
    {
        $type = $this->getType($name);
        $result = $this->getAction($type, $name);
        $result->setOrmType($type);

        return $result;
    }

    private function getType($name)
    {
        $rsm1 = new ResultSetMapping();
        
        // Map only the 'type' field
        $rsm1->addScalarResult('type', 'type');
        
        // Define the native SQL query
        $sql = 'SELECT type FROM actions where name = :name';
        
        // Execute the native query with the ResultSetMapping
        $query1 = $this->entityManager->createNativeQuery($sql, $rsm1);
        $query1->setParameter("name", $name);
        $type = $query1->getSingleScalarResult();

        return $type;
    }

    private function getAction($type, $name) : ActionInterface
    {
        $className = ucfirst(strtolower($type)) . 'Action';
        
        $query2 = $this->entityManager->createQuery(
            'SELECT action FROM App\\Action\\'.$className.' action where action.name = :name'
        );
        $query2->setParameter("name", $name);
        
        $result = $query2->getSingleResult();

        return $result;
    }

}
