<?php

namespace App\Service;

use Classes\Db;

/**
 * Reverse of PlayerSkillsService: given an action or passive, how many — and
 * which — players own it. Powers the "Qui a ça ?" counts on the Actions /
 * Passifs admin pages, the owner roster, and the adoption bars of the
 * Compétences statistics page.
 *
 * The population is the one SkillStatsService defines (real players by default,
 * PNJs on opt-in, optional active / inactive filter): both services share the
 * same predicate so a "9 joueurs" summary can never sit next to a "315
 * détenteurs" adoption row.
 */
class SkillOwnershipService
{
    private SkillStatsService $population;

    public function __construct(?SkillStatsService $population = null)
    {
        $this->population = $population ?? new SkillStatsService();
    }

    /**
     * Catalog action name => number of players (in the filtered population) who
     * own it.
     *
     * Plus aucun alias à replier : depuis
     * Version20260725110000_SplitAttaquerIntoMeleeAndDistance, ce que le joueur
     * possède porte le nom du catalogue. Le nom fantôme « attaquer », qui valait
     * melee ET distance selon la portée, n'existe plus.
     *
     * @return array<string, int>
     */
    public function actionOwnerCounts(
        bool $includeNpcs = false,
        string $status = SkillStatsService::STATUS_ALL
    ): array {
        $sql = 'SELECT pa.name AS k, COUNT(DISTINCT p.id) AS n
                FROM players_actions pa
                JOIN players p ON p.id = pa.player_id
                WHERE ' . $this->population->populationWhere($includeNpcs, $status, 'p.') . '
                GROUP BY k';

        return $this->countMap((new Db())->exe($sql));
    }

    /**
     * Passive id => number of players (in the filtered population) who own it.
     *
     * @return array<int, int>
     */
    public function passiveOwnerCounts(
        bool $includeNpcs = false,
        string $status = SkillStatsService::STATUS_ALL
    ): array {
        $sql = 'SELECT pp.passive_id AS k, COUNT(DISTINCT p.id) AS n
                FROM players_passives pp
                JOIN players p ON p.id = pp.player_id
                WHERE ' . $this->population->populationWhere($includeNpcs, $status, 'p.') . '
                GROUP BY pp.passive_id';

        return $this->countMap((new Db())->exe($sql));
    }

    /**
     * Players who own the given action, name-ordered.
     *
     * @return array<int, array{id:int, name:string, race:string}>
     */
    public function actionOwners(
        string $actionName,
        bool $includeNpcs = false,
        string $status = SkillStatsService::STATUS_ALL
    ): array {
        $sql = 'SELECT DISTINCT p.id, p.name, p.race
                FROM players_actions pa
                JOIN players p ON p.id = pa.player_id
                WHERE pa.name = ?
                  AND ' . $this->population->populationWhere($includeNpcs, $status, 'p.') . '
                ORDER BY p.name ASC';

        return $this->ownerRows((new Db())->exe($sql, [$actionName]));
    }

    /**
     * Players who own the given passive, name-ordered.
     *
     * @return array<int, array{id:int, name:string, race:string}>
     */
    public function passiveOwners(
        int $passiveId,
        bool $includeNpcs = false,
        string $status = SkillStatsService::STATUS_ALL
    ): array {
        $sql = 'SELECT DISTINCT p.id, p.name, p.race
                FROM players_passives pp
                JOIN players p ON p.id = pp.player_id
                WHERE pp.passive_id = ?
                  AND ' . $this->population->populationWhere($includeNpcs, $status, 'p.') . '
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
