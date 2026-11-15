<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use Doctrine\DBAL\Connection;

/** Shared rules for deciding which plans are controlled by consecrated altars. */
final class AltarControlService
{
    private Connection $connection;

    public function __construct()
    {
        $this->connection = EntityManagerFactory::getEntityManager()->getConnection();
    }

    /** @param list<string>|null $plans @return list<string> */
    public function contestedPlans(?array $plans = null): array
    {
        $filter = '';
        $parameters = [];
        if ($plans !== null) {
            if ($plans === []) {
                return [];
            }
            $filter = ' AND ec.plan IN (' . implode(', ', array_fill(0, count($plans), '?')) . ')';
            $parameters = $plans;
        }

        return array_map('strval', $this->connection->fetchFirstColumn(
            "SELECT ec.plan
               FROM players a
               JOIN entity_cells ec ON ec.player_id = a.id
              WHERE a.race = 'altar' AND a.godId != 0{$filter}
              GROUP BY ec.plan
             HAVING COUNT(DISTINCT a.godId) > 1",
            $parameters
        ));
    }
}
