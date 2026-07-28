<?php

namespace App\Service\Map;

use App\Entity\EntityManagerFactory;
use App\Service\RaceService;
use Doctrine\DBAL\Connection;

/**
 * Single source for "can a step land on this tile?", and its two neighbours
 * "can something land here?" and "can something be built here?".
 *
 * Core rule: a thing blocks the step if it blocks AND the mover can see it.
 * Structures are scenery and always seen; characters follow the plan's
 * `player_visibility` and their own hidden mode.
 */
final class TileOccupancyService
{
    /**
     * Roles that override the entity type's own passability. Roles absent
     * here — `part` — leaves the type to decide.
     */
    private const ROLE_VERDICTS = [
        'block' => true,
        'cover' => false,
    ];

    private Connection $conn;
    private RaceService $raceService;

    public function __construct(?Connection $conn = null, ?RaceService $raceService = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
        $this->raceService = $raceService ?? new RaceService();
    }

    /**
     * @param int  $moverId who is moving (players id; negative = NPC)
     * @param bool $charactersVisible plan's `player_visibility`; false when the
     *             plan has no JSON at all, matching the render rule
     *
     * @return string|null refusal reason, or null when the tile is walkable
     */
    public function stepRefusal(int $coordsId, int $moverId, bool $charactersVisible): ?string
    {
        return $this->blockedForStep([$coordsId], $moverId, $charactersVisible)[$coordsId] ?? null;
    }

    /**
     * Same rule over a whole field of view — three queries for the lot,
     * rather than three per tile. `stepRefusal()` calls it with one element.
     *
     * @param list<int> $coordsIds
     * @return array<int, string> coords_id => reason, blocked tiles only
     */
    public function blockedForStep(array $coordsIds, int $moverId, bool $charactersVisible): array
    {
        $coordsIds = array_values(array_unique(array_map('intval', $coordsIds)));
        if ($coordsIds === []) {
            return [];
        }

        $in = implode(',', $coordsIds);
        $blocked = [];

        foreach ($this->conn->fetchFirstColumn(
            "SELECT coords_id FROM map_triggers WHERE name = 'forbidden' AND coords_id IN ({$in})"
        ) as $id) {
            $blocked[(int) $id] = 'Impossible de se rendre à cet endroit.';
        }

        foreach ($this->conn->fetchFirstColumn(
            "SELECT coords_id FROM map_resources WHERE coords_id IN ({$in})"
        ) as $id) {
            $blocked[(int) $id] ??= 'Quelque chose obstrue ton chemin.';
        }

        $passable = $this->raceService->getPassableStructureNames();

        foreach ($this->occupations($in) as $row) {
            if ((int) $row['id'] === $moverId) {
                continue;
            }

            $verdict = self::ROLE_VERDICTS[(string) $row['role']] ?? null;

            if ($verdict === false) {
                continue; /* walkable whatever the type says */
            }

            if ($verdict === null && in_array((string) $row['race'], $passable, true)) {
                continue;
            }

            $isStructure = in_array($row['player_type'] ?? 'real', ['building', 'unique'], true);

            if (!$isStructure) {
                if ($row['invisible'] !== null || !$charactersVisible) {
                    continue;
                }
            }

            $blocked[(int) $row['coords_id']] ??= 'Quelque chose obstrue ton chemin.';
        }

        return $blocked;
    }

    /**
     * Which entity holds which of the given tiles, cell by cell.
     *
     * @param string $in coords_id list, already cast to integers
     * @return list<array{id: int|string, coords_id: int|string, role: string, race: string, player_type: ?string, invisible: ?int}>
     */
    private function occupations(string $in): array
    {
        $rows = $this->conn->fetchAllAssociative(
            "SELECT p.id, occupied.coords_id, occupied.role, p.race, p.player_type,
                    (SELECT 1 FROM players_options o
                      WHERE o.player_id = p.id AND o.name = 'invisibleMode') AS invisible
               FROM (" . self::heldSql($in) . ") AS occupied
               JOIN players p ON p.id = occupied.player_id"
        );

        /* Both sources list the origin cell, so the same (entity, tile) pair
         * shows up twice; an explicit role wins over `part`, which decides nothing. */
        $occupations = [];

        foreach ($rows as $row) {
            $pair = $row['id'] . ':' . $row['coords_id'];

            if (!isset($occupations[$pair]) || (string) $row['role'] !== EntityCellService::ROLE_PART) {
                $occupations[$pair] = $row;
            }
        }

        return array_values($occupations);
    }

    /**
     * "Does an entity hold this tile?" — one definition, shared by the three
     * verbs. `entity_cells` and `players.coords_id` ADD UP: an entity moved
     * without `syncCells()` keeps stale cells, and dropping either source
     * would make it walk-through where it actually stands.
     *
     * @param string $in coords_id list, already cast to integers
     */
    private static function heldSql(string $in): string
    {
        return "SELECT player_id, coords_id, role
                  FROM entity_cells
                 WHERE coords_id IN ({$in})
                 UNION ALL
                SELECT id, coords_id, '" . EntityCellService::ROLE_PART . "'
                  FROM players
                 WHERE coords_id IN ({$in})";
    }

    /** Any entity, at any title, on this tile. */
    private function heldByAnEntity(int $coordsId): bool
    {
        /* Scenery is excluded on purpose: decor never counted for landing or
         * building, and converting it into entities must not change that on
         * its own. Making decor unbuildable is a decision of its own lot. */
        return (bool) $this->conn->fetchOne(
            'SELECT 1
               FROM (' . self::heldSql((string) $coordsId) . ') AS held
               JOIN players p ON p.id = held.player_id
              WHERE p.player_type <> \'scenery\'
              LIMIT 1'
        );
    }

    /**
     * Landing question: is the tile EMPTY? Stricter than the step — every
     * trigger counts, and neither hidden mode nor plan visibility applies.
     */
    public function isVacant(int $coordsId): bool
    {
        if ($this->heldByAnEntity($coordsId)) {
            return false;
        }

        return !(bool) $this->conn->fetchOne(
            'SELECT 1 FROM (
                 SELECT coords_id FROM map_resources WHERE coords_id = :c
                 UNION ALL
                 SELECT coords_id FROM map_triggers  WHERE coords_id = :c
             ) AS occupants LIMIT 1',
            ['c' => $coordsId]
        );
    }

    /**
     * Building question: counts elements (water, lava, blood) unless the
     * catalogue declares them buildable over, and ignores triggers — so a
     * teleporter can be built on but not landed on. Legacy behaviour, kept.
     *
     * @return string|null refusal reason, or null when buildable
     */
    public function buildRefusal(int $coordsId): ?string
    {
        if ($this->heldByAnEntity($coordsId)) {
            return 'Case occupée par une entité.';
        }

        if ($this->hasResource($coordsId)) {
            return 'Case occupée par un mur.';
        }

        $effectService = new \App\Service\EffectService();
        foreach ($this->conn->fetchFirstColumn('SELECT name FROM map_elements WHERE coords_id = ?', [$coordsId]) as $element) {
            if (!$effectService->isBuildableOver((string) $element)) {
                return 'Case occupée par un élément (' . $element . ').';
            }
        }

        return null;
    }

    /** Any resource blocks, exhausted or not. Legacy behaviour, kept. */
    private function hasResource(int $coordsId): bool
    {
        return (bool) $this->conn->fetchOne(
            'SELECT 1 FROM map_resources WHERE coords_id = ? LIMIT 1',
            [$coordsId]
        );
    }

    /** Same condition as the render (`Classes/View.php`): hidden characters do not block. */
    public static function charactersVisibleOn(?object $planJson): bool
    {
        if (!$planJson) {
            return false;
        }

        return !(isset($planJson->player_visibility) && $planJson->player_visibility === false);
    }
}
