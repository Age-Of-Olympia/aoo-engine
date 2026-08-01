<?php

namespace App\Service\Map;

use App\Factory\EntityManagerFactory;
use Doctrine\DBAL\Connection;

/**
 * Sole writer of `entity_cells` — the cells an entity occupies.
 *
 * One method lays them all: the entity's origin is `players.coords_id`, and
 * its type's cut-out says which further cells it holds. Cells the cut-out no
 * longer claims are dropped in the same pass.
 */
final class EntityCellService
{
    /** Cell with no role of its own: the entity type decides whether it blocks. */
    public const ROLE_PART = 'part';

    /**
     * Scenery is a drawing until someone says otherwise.
     *
     * An unmarked cell of a decor is its painted part — walked through, shot
     * through. Only the cells an animator marks make it solid. Defaulting it
     * to `part` instead would defer to the type, and an anvil whose base
     * blocks would screen arrows across its whole height.
     */
    /* A decor is a drawing order; a resource is the wall it came from, so it
     * refuses the step. */
    private const DEFAULT_ROLES = ['scenery' => 'cover', 'resource' => 'block'];

    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /**
     * Lay every cell an entity occupies, from its origin and its cut-out.
     *
     * Idempotent: call it after any write to `players.coords_id`, or after a
     * cut-out changed, without asking what was there before.
     *
     * @return int cells laid; 0 when the entity is gone, and its cells with it
     */
    public function syncCells(int $entityId, ?EntityTypeFootprintService $footprints = null): int
    {
        $origin = $this->conn->fetchAssociative(
            'SELECT p.race, p.player_type, p.coords_id, co.plan, co.z, co.x, co.y
               FROM players p
               JOIN coords co ON co.id = p.coords_id
              WHERE p.id = ? AND p.coords_id > 0',
            [$entityId]
        );

        if ($origin === false) {
            $this->conn->executeStatement('DELETE FROM entity_cells WHERE player_id = ?', [$entityId]);

            return 0;
        }

        $footprint = ($footprints ?? new EntityTypeFootprintService($this->conn))
            ->catalogue()[(string) $origin['race']] ?? null;

        $cells = $footprint === null
            ? [0 => [(int) $origin['x'], (int) $origin['y']]]
            : $footprint->cellsAround(
                (int) array_key_first($footprint->offsets()),
                (int) $origin['x'],
                (int) $origin['y']
            );

        $default = self::DEFAULT_ROLES[(string) $origin['player_type']] ?? self::ROLE_PART;
        $keep = [];

        foreach ($cells as $piece => [$x, $y]) {
            $coordsId = (int) \Classes\View::get_coords_id((object) [
                'x' => $x, 'y' => $y, 'z' => (int) $origin['z'], 'plan' => (string) $origin['plan'],
            ]);

            $this->conn->executeStatement(
                'INSERT INTO entity_cells (player_id, coords_id, plan, z, x, y, piece, role)
                 VALUES (:p, :c, :plan, :z, :x, :y, :piece, :role)
                 ON DUPLICATE KEY UPDATE
                     plan = VALUES(plan), z = VALUES(z), x = VALUES(x), y = VALUES(y),
                     piece = VALUES(piece), role = VALUES(role)',
                [
                    'p'     => $entityId,
                    'c'     => $coordsId,
                    'plan'  => (string) $origin['plan'],
                    'z'     => (int) $origin['z'],
                    'x'     => $x,
                    'y'     => $y,
                    'piece' => (int) $piece,
                    'role'  => $footprint?->roleOf((int) $piece, $default) ?? $default,
                ]
            );

            $keep[] = $coordsId;
        }

        /* A move or a shrunk cut-out leaves cells behind; the primary key is
         * (player_id, coords_id), so they would simply pile up. */
        $this->conn->executeStatement(
            'DELETE FROM entity_cells WHERE player_id = ? AND coords_id NOT IN ('
                . implode(',', $keep) . ')',
            [$entityId]
        );

        return count($keep);
    }

    /**
     * Re-spread every placed instance of a type after its cut-out changed.
     *
     * @return int instances taken up
     */
    public function reapplyForType(string $typeName): int
    {
        $footprints = new EntityTypeFootprintService($this->conn);
        $footprint = $footprints->catalogue()[$typeName] ?? null;

        $reapplied = 0;

        foreach ($this->conn->fetchFirstColumn(
            'SELECT id FROM players WHERE race = ? AND coords_id > 0',
            [$typeName]
        ) as $entityId) {
            $this->syncCells((int) $entityId, $footprints);
            $reapplied++;
        }

        return $reapplied;
    }

    /**
     * Drop every cell of an entity that leaves the board but survives
     * (stored away, picked up). A deleted `players` row cascades on its own.
     */
    public function removeFor(int $playerId): int
    {
        return (int) $this->conn->executeStatement(
            'DELETE FROM entity_cells WHERE player_id = ?',
            [$playerId]
        );
    }

    /** @return list<array{coords_id: int, plan: string, z: int, x: int, y: int, piece: int, role: string}> */
    public function cellsOf(int $playerId): array
    {
        /** @var list<array{coords_id: int, plan: string, z: int, x: int, y: int, piece: int, role: string}> */
        return $this->conn->fetchAllAssociative(
            'SELECT coords_id, plan, z, x, y, piece, role
               FROM entity_cells WHERE player_id = ? ORDER BY piece, coords_id',
            [$playerId]
        );
    }

    /**
     * Who holds a cell. Several entities may: scenery stacked over a trigger
     * is the normal way the world marks a teleporter.
     *
     * @return list<array{player_id: int, piece: int, role: string}>
     */
    public function occupantsOf(int $coordsId): array
    {
        /** @var list<array{player_id: int, piece: int, role: string}> */
        return $this->conn->fetchAllAssociative(
            'SELECT player_id, piece, role FROM entity_cells WHERE coords_id = ? ORDER BY player_id',
            [$coordsId]
        );
    }

    /**
     * Entities holding no cell where they stand. Reported and repaired by the
     * `entity-cells` console command.
     *
     * Only INSTALLED entities are considered. Something merely dropped on a
     * tile holds no cell by definition, so without this filter every piece of
     * loot on the floor would read as corruption — and `reconcile()` would
     * repair it into a figure standing on the board.
     *
     * @return list<array{player_id: int, expected: int, actual: ?int}>
     */
    public function drift(): array
    {
        /** @var list<array{player_id: int, expected: int, actual: ?int}> */
        return $this->conn->fetchAllAssociative(
            'SELECT p.id AS player_id, p.coords_id AS expected, ec.coords_id AS actual
               FROM players p
               LEFT JOIN entity_cells ec
                      ON ec.player_id = p.id AND ec.coords_id = p.coords_id
              WHERE p.coords_id > 0 AND ec.coords_id IS NULL AND p.slot = ?
              ORDER BY p.id',
            [EntityLocationService::SLOT_INSTALLED]
        );
    }

    /** @return int entities whose cells were put back in place */
    public function reconcile(): int
    {
        $repaired = 0;

        foreach ($this->drift() as $row) {
            if ($this->syncCells((int) $row['player_id']) > 0) {
                $repaired++;
            }
        }

        return $repaired;
    }
}
