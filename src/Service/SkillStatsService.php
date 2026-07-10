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
    /** Activity filter values accepted by the stats queries. */
    public const STATUS_ALL = 'all';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    /**
     * Full WHERE predicate for the population a stats query covers: player kind
     * (real, or real + PNJ when opted in) plus an optional activity filter
     * (active / inactive, using the same INACTIVE_TIME cutoff as
     * PlayerService::isInactive). Single source of truth so the count, the
     * per-player table and the averages always agree on the population.
     *
     * @param string $prefix Column prefix ('' for the count query, 'p.' when joined)
     */
    private function whereClause(bool $includeNpcs, string $status, string $prefix = ''): string
    {
        $types = $includeNpcs ? "'real', 'npc'" : "'real'";
        $where = $prefix . 'player_type IN (' . $types . ')';

        if ($status === self::STATUS_ACTIVE || $status === self::STATUS_INACTIVE) {
            // Active = logged in within INACTIVE_TIME; inactive = not. The cutoff
            // is an int computed here (never user input), so it is safe to inline.
            $cutoff = time() - INACTIVE_TIME;
            $op = $status === self::STATUS_ACTIVE ? '>=' : '<';
            $where .= ' AND ' . $prefix . 'lastLoginTime ' . $op . ' ' . $cutoff;
        }

        return $where;
    }

    public function realPlayerCount(bool $includeNpcs = false, string $status = self::STATUS_ALL): int
    {
        $res = (new Db())->exe(
            'SELECT COUNT(*) AS n FROM players WHERE ' . $this->whereClause($includeNpcs, $status)
        );

        return (int) $res->fetch_assoc()['n'];
    }

    /**
     * Players with their action count (0 included), most-equipped first.
     *
     * @return array<int, array{id:int, name:string, count:int}>
     */
    public function playerActionCounts(bool $includeNpcs = false, string $status = self::STATUS_ALL): array
    {
        $sql = "SELECT p.id, p.name, COUNT(pa.name) AS n
                FROM players p
                LEFT JOIN players_actions pa ON pa.player_id = p.id
                WHERE " . $this->whereClause($includeNpcs, $status, 'p.') . "
                GROUP BY p.id, p.name
                ORDER BY n DESC, p.name ASC";

        return $this->rows((new Db())->exe($sql));
    }

    /**
     * Players with their passive count (0 included), most-equipped first.
     *
     * @return array<int, array{id:int, name:string, count:int}>
     */
    public function playerPassiveCounts(bool $includeNpcs = false, string $status = self::STATUS_ALL): array
    {
        $sql = "SELECT p.id, p.name, COUNT(pp.passive_id) AS n
                FROM players p
                LEFT JOIN players_passives pp ON pp.player_id = p.id
                WHERE " . $this->whereClause($includeNpcs, $status, 'p.') . "
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
