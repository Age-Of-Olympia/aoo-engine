<?php

namespace App\Service\Map;

use App\Entity\EntityManagerFactory;
use Doctrine\DBAL\Connection;

/**
 * Sole writer of `entity_cells` — the cells an entity occupies.
 *
 * Every entity has exactly one `anchor` cell, mirroring `players.coords_id`;
 * a footprint adds the others around it. `syncAnchor()` owns the anchor,
 * `syncFootprint()` owns the rest, so neither undoes the other.
 */
final class EntityCellService
{
    /** Cell with no role of its own: the entity type decides whether it blocks. */
    public const ROLE_PART = 'part';

    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /**
     * Move the anchor onto the cell `players` declares. Idempotent.
     *
     * @return bool false when the entity no longer exists; its anchor is then dropped
     */
    public function syncAnchor(int $playerId): bool
    {
        $row = $this->conn->fetchAssociative(
            'SELECT p.coords_id, co.plan, co.z, co.x, co.y
               FROM players p
               JOIN coords co ON co.id = p.coords_id
              WHERE p.id = ? AND p.coords_id > 0',
            [$playerId]
        );

        if ($row === false) {
            $this->conn->executeStatement(
                "DELETE FROM entity_cells WHERE player_id = ? AND role = 'anchor'",
                [$playerId]
            );

            return false;
        }

        /* Primary key is (player_id, coords_id): inserting alone would leave
         * a second anchor behind whenever the entity moved. */
        $this->conn->executeStatement(
            "DELETE FROM entity_cells WHERE player_id = ? AND role = 'anchor' AND coords_id <> ?",
            [$playerId, (int) $row['coords_id']]
        );

        $this->conn->executeStatement(
            "INSERT INTO entity_cells (player_id, coords_id, plan, z, x, y, piece, role)
             VALUES (:p, :c, :plan, :z, :x, :y, 0, 'anchor')
             ON DUPLICATE KEY UPDATE
                 plan = VALUES(plan), z = VALUES(z), x = VALUES(x), y = VALUES(y),
                 role = 'anchor'",
            [
                'p'    => $playerId,
                'c'    => (int) $row['coords_id'],
                'plan' => (string) $row['plan'],
                'z'    => (int) $row['z'],
                'x'    => (int) $row['x'],
                'y'    => (int) $row['y'],
            ]
        );

        return true;
    }

    /**
     * Spread an entity over its type's declared cut-out, around its anchor.
     *
     * Idempotent: cells the cut-out dropped are released, new ones added.
     *
     * @return int cells laid around the anchor
     */
    public function syncFootprint(int $entityId, ?EntityTypeFootprintService $footprints = null): int
    {
        $anchor = $this->conn->fetchAssociative(
            'SELECT p.race, p.coords_id, co.plan, co.z, co.x, co.y
               FROM players p
               JOIN coords co ON co.id = p.coords_id
              WHERE p.id = ? AND p.coords_id > 0',
            [$entityId]
        );

        if ($anchor === false) {
            $this->forgetSpread($entityId, []);

            return 0;
        }

        $footprint = ($footprints ?? new EntityTypeFootprintService($this->conn))
            ->catalogue()[(string) $anchor['race']] ?? null;

        if ($footprint === null || $footprint->isSingleCell()) {
            $this->forgetSpread($entityId, []);

            return 0;
        }

        /* Offsets are relative to the first piece, and the anchor sits on it. */
        $anchorPiece = array_key_first($footprint->offsets());
        $keep = [(int) $anchor['coords_id']];
        $placed = 0;

        $around = $footprint->cellsAround($anchorPiece, (int) $anchor['x'], (int) $anchor['y']);

        foreach ($around as $piece => [$x, $y]) {
            /* Already laid by syncAnchor(), and it must keep the anchor role. */
            if ($piece === $anchorPiece) {
                continue;
            }

            $coordsId = (int) \Classes\View::get_coords_id((object) [
                'x' => $x, 'y' => $y, 'z' => (int) $anchor['z'], 'plan' => (string) $anchor['plan'],
            ]);

            $this->conn->executeStatement(
                "INSERT INTO entity_cells (player_id, coords_id, plan, z, x, y, piece, role)
                 VALUES (:p, :c, :plan, :z, :x, :y, :piece, :role)
                 ON DUPLICATE KEY UPDATE
                     plan = VALUES(plan), z = VALUES(z), x = VALUES(x), y = VALUES(y),
                     piece = VALUES(piece), role = VALUES(role)",
                [
                    'p'     => $entityId,
                    'c'     => $coordsId,
                    'plan'  => (string) $anchor['plan'],
                    'z'     => (int) $anchor['z'],
                    'x'     => $x,
                    'y'     => $y,
                    'piece' => (int) $piece,
                    'role'  => $footprint->roleOf((int) $piece, self::ROLE_PART),
                ]
            );

            $keep[] = $coordsId;
            $placed++;
        }

        $this->forgetSpread($entityId, $keep);

        return $placed;
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

        /* Back to a single cell: nothing to spread, only cells to release —
         * in one statement rather than one per instance. */
        if ($footprint === null || $footprint->isSingleCell()) {
            return (int) $this->conn->executeStatement(
                "DELETE ec FROM entity_cells ec
                   JOIN players p ON p.id = ec.player_id
                  WHERE p.race = ? AND ec.role <> 'anchor'",
                [$typeName]
            );
        }

        $reapplied = 0;

        foreach ($this->conn->fetchFirstColumn(
            'SELECT id FROM players WHERE race = ? AND coords_id > 0',
            [$typeName]
        ) as $entityId) {
            $this->syncFootprint((int) $entityId, $footprints);
            $reapplied++;
        }

        return $reapplied;
    }

    /**
     * Drop spread cells the cut-out no longer claims. Never the anchor.
     *
     * @param list<int> $keep
     */
    private function forgetSpread(int $entityId, array $keep): void
    {
        $sql = "DELETE FROM entity_cells WHERE player_id = ? AND role <> 'anchor'";
        $params = [$entityId];

        if ($keep !== []) {
            $sql .= ' AND coords_id NOT IN (' . implode(',', array_map('intval', $keep)) . ')';
        }

        $this->conn->executeStatement($sql, $params);
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

    /** @return list<array{coords_id: int, plan: string, z: int, x: int, y: int, piece: int, role: string}> anchor included */
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
     * Entities whose anchor no longer matches `players.coords_id`. Reported
     * and repaired by the `entity-cells` console command.
     *
     * @return list<array{player_id: int, expected: int, actual: ?int}>
     */
    public function drift(): array
    {
        /** @var list<array{player_id: int, expected: int, actual: ?int}> */
        return $this->conn->fetchAllAssociative(
            "SELECT p.id AS player_id, p.coords_id AS expected, ec.coords_id AS actual
               FROM players p
               LEFT JOIN entity_cells ec ON ec.player_id = p.id AND ec.role = 'anchor'
              WHERE p.coords_id > 0
                AND (ec.coords_id IS NULL OR ec.coords_id <> p.coords_id)
              ORDER BY p.id"
        );
    }

    /** @return int entities whose anchor was put back in place */
    public function reconcile(): int
    {
        $repaired = 0;

        foreach ($this->drift() as $row) {
            if ($this->syncAnchor((int) $row['player_id'])) {
                $repaired++;
            }
        }

        return $repaired;
    }
}
