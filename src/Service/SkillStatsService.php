<?php

namespace App\Service;

use Classes\Db;

/**
 * Aggregate figures for the Compétences statistics page: how many real players
 * there are, and how many actions / passives each one holds (for averages and
 * outliers). Restricted to real players (player_type = 'real').
 *
 * Adoption-per-skill (how many players own each action/passive) is read from
 * SkillOwnershipService; this service covers the per-player side.
 */
class SkillStatsService
{
    public function realPlayerCount(): int
    {
        $res = (new Db())->exe("SELECT COUNT(*) AS n FROM players WHERE player_type = 'real'");

        return (int) $res->fetch_assoc()['n'];
    }

    /**
     * Real players with their action count (0 included), most-equipped first.
     *
     * @return array<int, array{id:int, name:string, count:int}>
     */
    public function playerActionCounts(): array
    {
        $sql = "SELECT p.id, p.name, COUNT(pa.name) AS n
                FROM players p
                LEFT JOIN players_actions pa ON pa.player_id = p.id
                WHERE p.player_type = 'real'
                GROUP BY p.id, p.name
                ORDER BY n DESC, p.name ASC";

        return $this->rows((new Db())->exe($sql));
    }

    /**
     * Real players with their passive count (0 included), most-equipped first.
     *
     * @return array<int, array{id:int, name:string, count:int}>
     */
    public function playerPassiveCounts(): array
    {
        $sql = "SELECT p.id, p.name, COUNT(pp.passive_id) AS n
                FROM players p
                LEFT JOIN players_passives pp ON pp.player_id = p.id
                WHERE p.player_type = 'real'
                GROUP BY p.id, p.name
                ORDER BY n DESC, p.name ASC";

        return $this->rows((new Db())->exe($sql));
    }

    /**
     * @return array<int, array{id:int, name:string, count:int}>
     */
    private function rows(\mysqli_result $res): array
    {
        $out = [];
        while ($row = $res->fetch_assoc()) {
            $out[] = [
                'id'    => (int) $row['id'],
                'name'  => (string) $row['name'],
                'count' => (int) $row['n'],
            ];
        }

        return $out;
    }
}
