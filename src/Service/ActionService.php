<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use App\Interface\ActionInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\ResultSetMapping;

class ActionService
{
    private $entityManager;

    // Getters pour les dépendances

    public function __construct(?Connection $conn = null)
    {
        // Fetch the entity manager from your custom factory
        $this->entityManager = EntityManagerFactory::getEntityManager($conn);
    }

    /**
     * Returns a Action entity that matches the given type, or null if not found.
     */
    public function getActionByName(string $name): ?ActionInterface
    {
        $result = null;
        $type = $this->getType($name);
        if ($type != "") {
            $result = $this->getAction($type, $name);
            $result->setOrmType($type);
        }

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
        try {
            $type = $query1->getSingleScalarResult();
        } catch (NoResultException $e) {
            $type = "";
        }

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

    public function getCostsArray(?string $actionName, ?ActionInterface $action) : array {
        if (!isset($action)) {
            $action = $this->getActionByName($actionName);
        }
        $conditions = $action->getConditions();
        $costArray = array();
        foreach($conditions as $condition) {
            $conditionType = $condition->getConditionType();
            if ($conditionType == 'RequiresTraitValue') {
                $conditionParameters = $condition->getParameters();
                foreach ($conditionParameters as $key => $value) {
                    if ($key == "energie") {
                        continue;
                    }
                    array_push($costArray, $value . CARACS[$key]);
                }
                break;
            }
        }
        return $costArray;
    }

}
