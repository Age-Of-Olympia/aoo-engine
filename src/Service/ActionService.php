<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use App\Interface\ActionInterface;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\ResultSetMapping;

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
                    if ($key == "energie" || !is_numeric($value) || !isset(CARACS[$key])) {
                        continue;
                    }
                    array_push($costArray, $value . CARACS[$key]);
                }
                break;
            }
        }
        return $costArray;
    }

    public function getPrice($level) : int
        {

        switch ($level) {
            case 1:
                return 50;
            case 2:
                return 100;
            case 3:
                return 200;
            case 4:
                return 300;
            case 5:
                return 300;
            default:
                return 50;
        }
    }

    public function getActionsByCategory(string $category): array
    {
        $query = $this->entityManager->createQuery(
        'SELECT a FROM App\Entity\Action a
         WHERE a.category LIKE :cat
         ORDER BY a.level ASC, a.name ASC'
        )
        ->setParameter('cat', $category . '%');

        return $query->getResult();
    }

    /**
     * Every action name the game knows, for admin pickers/autocomplete:
     * the configured actions (with their type as label) plus the legacy
     * names that only exist as granted rows or race-list entries (e.g.
     * 'attaquer', which has no `actions` row).
     *
     * Merged in PHP: the four tables carry mixed collations, a SQL UNION
     * on them throws.
     *
     * @return array<string, string> name => type label ('' when unknown)
     */
    public function getKnownActionNames(): array
    {
        $connection = $this->entityManager->getConnection();

        $names = [];
        foreach ($connection->fetchAllAssociative('SELECT name, type FROM actions') as $row) {
            $names[$row['name']] = (string) $row['type'];
        }

        $legacySources = [
            'SELECT DISTINCT name FROM players_actions',
            'SELECT DISTINCT name FROM race_starter_actions',
            'SELECT DISTINCT name FROM race_spells',
        ];
        foreach ($legacySources as $sql) {
            foreach ($connection->fetchFirstColumn($sql) as $name) {
                $names[$name] ??= '';
            }
        }

        ksort($names);

        return $names;
    }

}
