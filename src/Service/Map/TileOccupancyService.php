<?php

namespace App\Service\Map;

use App\Factory\EntityManagerFactory;
use App\Enum\EntityCategory;
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
    /**
     * Cells something PERMANENT already refuses the step on.
     *
     * Answers what a `forbidden` trigger adds: a fence duplicating a wall is
     * dead weight now that the wall refuses by itself, and it lies — remove
     * the wall and the fence keeps standing, invisible.
     *
     * Characters are deliberately out: someone standing in a doorway blocks
     * it for a turn, which is no reason to call a fence redundant.
     *
     * @param list<int> $coordsIds
     * @return array<int, string> coords_id => what blocks it
     */
    public function permanentlyBlocked(array $coordsIds): array
    {
        $coordsIds = array_values(array_unique(array_map('intval', $coordsIds)));

        if ($coordsIds === []) {
            return [];
        }

        $in = implode(',', $coordsIds);
        $blocked = [];

        /* Les deux catalogues, gardés séparés : c'est le discriminant qui dit
         * lequel interroger, un nom seul ne le dirait pas. */
        $passableBy = (new \App\Service\ObstructionService($this->conn, $this->raceService))
            ->passableTypeNames();

        foreach ($this->occupations($in) as $row) {
            $verdict = self::ROLE_VERDICTS[(string) $row['role']] ?? null;

            if ($verdict === false) {
                continue; /* a drawing order screens nothing */
            }

            $passable = (string) ($row['player_type'] ?? '') === \App\Service\ObstructionService::ITEM_TYPE
                ? $passableBy['item']
                : $passableBy['race'];

            if ($verdict === null && in_array((string) $row['race'], $passable, true)) {
                continue;
            }

            if (!EntityCategory::fromPlayerType($row['player_type'] ?? null)->isStructure()) {
                continue; /* a character is not a wall */
            }

            $blocked[(int) $row['coords_id']] ??= (string) $row['race'];
        }

        return $blocked;
    }

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

        /* Les deux catalogues, gardés séparés : c'est le discriminant qui dit
         * lequel interroger, un nom seul ne le dirait pas. */
        $passableBy = (new \App\Service\ObstructionService($this->conn, $this->raceService))
            ->passableTypeNames();
        $doors = $this->raceService->getDoorRaceNames();

        foreach ($this->occupations($in) as $row) {
            if ((int) $row['id'] === $moverId) {
                continue;
            }

            // An open door is walked through; a chest's lid decides its
            // contents, not passage. Doors are race types only, hence the
            // discriminator check against homonymous items.
            if ((string) ($row['player_type'] ?? '') !== \App\Service\ObstructionService::ITEM_TYPE
                && in_array((string) $row['race'], $doors, true)
                && (int) $row['is_open'] === 1) {
                continue;
            }

            $verdict = self::ROLE_VERDICTS[(string) $row['role']] ?? null;

            if ($verdict === false) {
                continue; /* walkable whatever the type says */
            }

            $passable = (string) ($row['player_type'] ?? '') === \App\Service\ObstructionService::ITEM_TYPE
                ? $passableBy['item']
                : $passableBy['race'];

            if ($verdict === null && in_array((string) $row['race'], $passable, true)) {
                continue;
            }

            $isStructure = EntityCategory::fromPlayerType($row['player_type'] ?? null)->isStructure();

            if (!$isStructure) {
                /* Un plan qui masque les personnages (isolation du tutoriel)
                 * masque les AUTRES joueurs — les PNJ y restent dessinés
                 * (View.php n'écarte que id > 0), donc ils barrent le pas :
                 * on ne traverse pas la Gaïa qu'on a devant soi. */
                $isNpc = (string) ($row['player_type'] ?? '') === 'npc';

                if ($row['invisible'] !== null || (!$charactersVisible && !$isNpc)) {
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
     * @return list<array{id: int|string, coords_id: int|string, role: string, race: string, player_type: ?string, is_open: int|string, invisible: ?int}>
     */
    private function occupations(string $in): array
    {
        $rows = $this->conn->fetchAllAssociative(
            "SELECT p.id, occupied.coords_id, occupied.role, p.race, p.player_type, p.is_open,
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
     * What is merely DROPPED is excluded: it lies on the tile without holding
     * it. Counting it would make a sword on the floor block construction and
     * bar the step, which no ground loot has ever done.
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
                 WHERE coords_id IN ({$in})
                   AND slot <> '" . EntityLocationService::SLOT_DROPPED . "'";
    }

    /** Any entity, at any title, on this tile. */
    private function heldByAnEntity(int $coordsId, bool $countScenery = false): bool
    {
        $exceptScenery = $countScenery ? '' : " AND p.player_type <> 'scenery'";

        return (bool) $this->conn->fetchOne(
            'SELECT 1
               FROM (' . self::heldSql((string) $coordsId) . ') AS held
               JOIN players p ON p.id = held.player_id
              WHERE 1' . $exceptScenery . '
              LIMIT 1'
        );
    }

    /**
     * Landing question: is the tile EMPTY? Stricter than the step — every
     * trigger counts, and neither hidden mode nor plan visibility applies.
     */
    public function isVacant(int $coordsId): bool
    {
        /* Decor does not fill a tile: one walks on it, so one lands on it. */
        if ($this->heldByAnEntity($coordsId)) {
            return false;
        }

        return !(bool) $this->conn->fetchOne(
            'SELECT 1 FROM map_triggers WHERE coords_id = ? LIMIT 1',
            [$coordsId]
        );
    }

    /**
     * Building question: counts elements (water, lava, blood) unless the
     * catalogue declares them buildable over, and ignores triggers — so a
     * teleporter can be built on but not landed on. Legacy behaviour, kept.
     *
     * @return string|null refusal reason, or null when buildable
     */
    public function buildRefusal(int $coordsId, bool $overScenery = false): ?string
    {
        /* Decor counts as occupied for a PLAYER: one does not raise a wall
         * through a statue. An animator placing from the editor may, to tuck
         * something behind it — hence the flag rather than a blanket rule. */
        if ($this->heldByAnEntity($coordsId, !$overScenery)) {
            return 'Case occupée par une entité.';
        }

        $effectService = new \App\Service\EffectService();
        foreach ($this->conn->fetchFirstColumn('SELECT name FROM map_elements WHERE coords_id = ?', [$coordsId]) as $element) {
            if (!$effectService->isBuildableOver((string) $element)) {
                return 'Case occupée par un élément (' . $element . ').';
            }
        }

        return null;
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
