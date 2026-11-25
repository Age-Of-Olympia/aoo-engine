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

    /**
     * @param list<string>|null $plans
     * @return list<string>
     */
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

    /**
     * Plans of the current game season (plus the every-season ones): the only
     * ones whose altars count for rankings and rewards.
     *
     * @return list<string>
     */
    public function seasonPlans(?int $season = null): array
    {
        return array_map('strval', array_keys((new PlanService())->forSeason($season)));
    }

    /**
     * Consecrated altars per god on the given plans (season plans by default).
     * A naked altar (godId 0) is neutral and never counted.
     *
     * @param list<string>|null $plans
     * @return array<int, int> godId => altar count
     */
    public function altarCountsByGod(?array $plans = null): array
    {
        $plans ??= $this->seasonPlans();
        if ($plans === []) {
            return [];
        }
        $marks = implode(', ', array_fill(0, count($plans), '?'));
        $counts = [];
        foreach ($this->connection->fetchAllAssociative(
            "SELECT a.godId, COUNT(DISTINCT a.id) AS total
               FROM players a
               JOIN entity_cells ec ON ec.player_id = a.id
              WHERE a.race = 'altar' AND a.godId != 0 AND ec.plan IN ({$marks})
              GROUP BY a.godId",
            $plans
        ) as $row) {
            $counts[(int) $row['godId']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Plans held by exactly one god, counted per god: a plan with altars of two
     * gods is contested and belongs to nobody.
     *
     * @param list<string>|null $plans season plans by default
     * @return array<int, int> godId => controlled plan count
     */
    public function controlledPlanCountsByGod(?array $plans = null): array
    {
        $plans ??= $this->seasonPlans();
        if ($plans === []) {
            return [];
        }
        $marks = implode(', ', array_fill(0, count($plans), '?'));
        $counts = [];
        foreach ($this->connection->fetchAllAssociative(
            "SELECT MIN(a.godId) AS godId
               FROM players a
               JOIN entity_cells ec ON ec.player_id = a.id
              WHERE a.race = 'altar' AND a.godId != 0 AND ec.plan IN ({$marks})
              GROUP BY ec.plan
             HAVING COUNT(DISTINCT a.godId) = 1",
            $plans
        ) as $row) {
            $godId = (int) $row['godId'];
            $counts[$godId] = ($counts[$godId] ?? 0) + 1;
        }

        return $counts;
    }
}
