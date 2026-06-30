<?php

namespace App\Service;

use Classes\Db;

/**
 * Reverse of PlayerSkillsService: given an action or passive, how many — and
 * which — players own it. Powers the "Qui a ça ?" counts on the Actions /
 * Passifs admin pages and the owner roster.
 *
 * Counts and rosters are restricted to real players (player_type = 'real'), so
 * PNJs and tutorial characters don't inflate the figures.
 */
class SkillOwnershipService
{
    /**
     * Action name => number of real players who own it.
     *
     * @return array<string, int>
     */
    public function actionOwnerCounts(): array
    {
        $sql = 'SELECT pa.name AS k, COUNT(*) AS n
                FROM players_actions pa
                JOIN players p ON p.id = pa.player_id
                WHERE p.player_type = "real"
                GROUP BY pa.name';

        return $this->countMap((new Db())->exe($sql));
    }

    /**
     * Passive id => number of real players who own it.
     *
     * @return array<int, int>
     */
    public function passiveOwnerCounts(): array
    {
        $sql = 'SELECT pp.passive_id AS k, COUNT(*) AS n
                FROM players_passives pp
                JOIN players p ON p.id = pp.player_id
                WHERE p.player_type = "real"
                GROUP BY pp.passive_id';

        return $this->countMap((new Db())->exe($sql));
    }

    /**
     * Real players who own the given action, name-ordered.
     *
     * @return array<int, array{id:int, name:string, race:string}>
     */
    public function actionOwners(string $actionName): array
    {
        $sql = 'SELECT p.id, p.name, p.race
                FROM players_actions pa
                JOIN players p ON p.id = pa.player_id
                WHERE pa.name = ? AND p.player_type = "real"
                ORDER BY p.name ASC';

        return $this->ownerRows((new Db())->exe($sql, [$actionName]));
    }

    /**
     * Real players who own the given passive, name-ordered.
     *
     * @return array<int, array{id:int, name:string, race:string}>
     */
    public function passiveOwners(int $passiveId): array
    {
        $sql = 'SELECT p.id, p.name, p.race
                FROM players_passives pp
                JOIN players p ON p.id = pp.player_id
                WHERE pp.passive_id = ? AND p.player_type = "real"
                ORDER BY p.name ASC';

        return $this->ownerRows((new Db())->exe($sql, [$passiveId]));
    }

    /**
     * @return array<int|string, int>
     */
    private function countMap(\mysqli_result $res): array
    {
        $map = [];
        while ($row = $res->fetch_assoc()) {
            $map[$row['k']] = (int) $row['n'];
        }

        return $map;
    }

    /**
     * @return array<int, array{id:int, name:string, race:string}>
     */
    private function ownerRows(\mysqli_result $res): array
    {
        $players = [];
        while ($row = $res->fetch_assoc()) {
            $players[] = [
                'id'   => (int) $row['id'],
                'name' => (string) $row['name'],
                'race' => (string) $row['race'],
            ];
        }

        return $players;
    }
}
