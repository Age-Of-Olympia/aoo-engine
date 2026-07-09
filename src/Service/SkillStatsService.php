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
    /**
     * SQL predicate for the player kinds a stats query covers. Real players by
     * default; PNJs (player_type = 'npc') are folded in when the caller opts in
     * via the "Inclure les PNJ" toggle. Kept as a single source of truth so the
     * count and the per-player queries always agree on the population.
     */
    private function playerTypeClause(bool $includeNpcs): string
    {
        return $includeNpcs
            ? "player_type IN ('real', 'npc')"
            : "player_type = 'real'";
    }

    public function realPlayerCount(bool $includeNpcs = false): int
    {
        $res = (new Db())->exe(
            'SELECT COUNT(*) AS n FROM players WHERE ' . $this->playerTypeClause($includeNpcs)
        );

        return (int) $res->fetch_assoc()['n'];
    }

    /**
     * Players with their action count (0 included), most-equipped first.
     *
     * @return array<int, array{id:int, name:string, count:int}>
     */
    public function playerActionCounts(bool $includeNpcs = false): array
    {
        $sql = "SELECT p.id, p.name, COUNT(pa.name) AS n
                FROM players p
                LEFT JOIN players_actions pa ON pa.player_id = p.id
                WHERE p." . $this->playerTypeClause($includeNpcs) . "
                GROUP BY p.id, p.name
                ORDER BY n DESC, p.name ASC";

        return $this->rows((new Db())->exe($sql));
    }

    /**
     * Players with their passive count (0 included), most-equipped first.
     *
     * @return array<int, array{id:int, name:string, count:int}>
     */
    public function playerPassiveCounts(bool $includeNpcs = false): array
    {
        $sql = "SELECT p.id, p.name, COUNT(pp.passive_id) AS n
                FROM players p
                LEFT JOIN players_passives pp ON pp.player_id = p.id
                WHERE p." . $this->playerTypeClause($includeNpcs) . "
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
