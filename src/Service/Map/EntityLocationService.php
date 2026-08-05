<?php

namespace App\Service\Map;

use App\Factory\EntityManagerFactory;
use Doctrine\DBAL\Connection;

/**
 * Sole writer of where an entity is — cell, holder, or nowhere.
 *
 * An entity stands on a cell (`coords_id`), or sits inside another entity
 * (`holder_id` + `slot`), or is off the world entirely. Never two at once: the
 * three doors below each close the others, so the invariant has one keeper
 * rather than one per caller.
 *
 * What an entity holds is what points at it, so its inventory is a query, not
 * a table. The cell it is ULTIMATELY on is found by climbing holders — a sword
 * in a bag, the bag on a character, the character on a cell.
 *
 * Nobody writes through this yet: every existing row stands on a cell with no
 * holder, which is what it meant before the columns existed.
 */
final class EntityLocationService
{
    /** Held, with no place of its own: in a bag, in a chest, in a pile. */
    public const SLOT_CARRIED = '';

    /**
     * On a cell and part of it: drawn as a figure, occupies `entity_cells`,
     * can be hit. Every entity that stood on a cell before items arrived.
     */
    public const SLOT_INSTALLED = 'installed';

    /**
     * On a cell but only lying there: a tile marker, picked up freely,
     * occupying nothing.
     *
     * Stored rather than inferred from "has no cells", because that absence
     * already means something else: {@see EntityCellService::drift()} reads it
     * as corruption and `reconcile()` repairs it by laying cells — which would
     * quietly promote every dropped sword into a figure on the board.
     */
    public const SLOT_DROPPED = 'dropped';

    /**
     * How far a holder chain may be climbed.
     *
     * A bag in a chest on a cart is three; the guard exists for the cycle a bug
     * could weave, not for the depth play needs. Reaching it means the chain
     * lies, so it answers "nowhere" rather than spinning.
     */
    private const MAX_DEPTH = 16;

    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /**
     * Install an entity on a cell: it becomes part of the tile.
     *
     * Its cells are re-laid — `entity_cells` is what the board reads, and a
     * figure that moved without them stays drawn where it was.
     */
    public function installOnCell(int $entityId, int $coordsId): void
    {
        $this->moveToCell($entityId, $coordsId, self::SLOT_INSTALLED);

        (new EntityCellService($this->conn))->syncCells($entityId);
    }

    /**
     * Drop an entity on a cell: it lies there without being part of it.
     *
     * No cells: dropped loot is a marker on the tile, not a figure standing on
     * it, and it must not block, screen or be hit.
     */
    public function dropOnCell(int $entityId, int $coordsId): void
    {
        $this->moveToCell($entityId, $coordsId, self::SLOT_DROPPED);

        (new EntityCellService($this->conn))->removeFor($entityId);
    }

    private function moveToCell(int $entityId, int $coordsId, string $slot): void
    {
        $this->conn->executeStatement(
            'UPDATE players SET coords_id = ?, holder_id = NULL, slot = ? WHERE id = ?',
            [$coordsId, $slot, $entityId]
        );
    }

    /**
     * Put an entity inside another one.
     *
     * Off the board is off the board: the cells go, or a picked-up sword keeps
     * a square of the map to itself.
     *
     * @throws \InvalidArgumentException on a chain that would eat its own tail
     *         — a bag inside itself, or inside something it already holds
     */
    public function putInside(int $entityId, int $holderId, string $slot = self::SLOT_CARRIED): void
    {
        if ($entityId === $holderId) {
            throw new \InvalidArgumentException("L'entité #{$entityId} ne peut pas se tenir elle-même.");
        }

        if ($this->holds($entityId, $holderId)) {
            throw new \InvalidArgumentException(
                "L'entité #{$entityId} tient déjà #{$holderId} : le mettre dedans fermerait la boucle."
            );
        }

        $this->conn->executeStatement(
            'UPDATE players SET coords_id = NULL, holder_id = ?, slot = ? WHERE id = ?',
            [$holderId, $slot, $entityId]
        );

        (new EntityCellService($this->conn))->removeFor($entityId);
    }

    /**
     * Take an entity off the world without deleting it: it is nowhere.
     *
     * The row survives so events naming it stay true and its id is never
     * recycled.
     */
    public function shelve(int $entityId): void
    {
        $this->conn->executeStatement(
            "UPDATE players SET coords_id = NULL, holder_id = NULL, slot = '' WHERE id = ?",
            [$entityId]
        );

        (new EntityCellService($this->conn))->removeFor($entityId);
    }

    /** The entity holding this one, or null when it holds itself to no one. */
    public function holderOf(int $entityId): ?int
    {
        $holder = $this->conn->fetchOne('SELECT holder_id FROM players WHERE id = ?', [$entityId]);

        return ($holder === false || $holder === null) ? null : (int) $holder;
    }

    /**
     * The cell this entity is ultimately on, climbing through its holders.
     *
     * Null when nothing in the chain stands anywhere: shelved, or held by
     * something shelved. Zero reads as no cell, the way `EntityCellService`
     * already reads it.
     */
    public function cellOf(int $entityId): ?int
    {
        $seen = [];

        for ($depth = 0; $depth < self::MAX_DEPTH; $depth++) {
            if (isset($seen[$entityId])) {
                return null;
            }
            $seen[$entityId] = true;

            $row = $this->conn->fetchAssociative(
                'SELECT coords_id, holder_id FROM players WHERE id = ?',
                [$entityId]
            );

            if ($row === false) {
                return null;
            }

            if ($row['holder_id'] === null) {
                $coordsId = (int) $row['coords_id'];

                return $coordsId > 0 ? $coordsId : null;
            }

            $entityId = (int) $row['holder_id'];
        }

        return null;
    }

    /**
     * What an entity holds — its inventory, one slot or all of them.
     *
     * @return list<array{id: int, slot: string, player_type: string, race: string, name: string}>
     */
    public function childrenOf(int $entityId, ?string $slot = null): array
    {
        $sql = 'SELECT id, slot, player_type, race, name FROM players WHERE holder_id = ?';
        $params = [$entityId];

        if ($slot !== null) {
            $sql .= ' AND slot = ?';
            $params[] = $slot;
        }

        /** @var list<array{id: int, slot: string, player_type: string, race: string, name: string}> */
        return $this->conn->fetchAllAssociative($sql . ' ORDER BY slot, id', $params);
    }

    /**
     * Does this entity hold anything at all? The question a container is
     * asked before it is picked up — and STACKS count as much as held
     * exemplars: a chest full of wood was pocketed whole because only
     * its children were asked.
     */
    public function holdsAnything(int $entityId): bool
    {
        if ((int) $this->conn->fetchOne(
            'SELECT COUNT(*) FROM players WHERE holder_id = ?',
            [$entityId]
        ) > 0) {
            return true;
        }

        return (int) $this->conn->fetchOne(
            "SELECT COUNT(*) FROM players_items WHERE player_id = ? AND slot = '' AND n > 0",
            [$entityId]
        ) > 0;
    }

    /** Is $descendantId somewhere inside $entityId, at any depth? */
    public function holds(int $entityId, int $descendantId): bool
    {
        $seen = [];

        for ($depth = 0; $depth < self::MAX_DEPTH; $depth++) {
            if (isset($seen[$descendantId])) {
                return false;
            }
            $seen[$descendantId] = true;

            $holder = $this->holderOf($descendantId);

            if ($holder === null) {
                return false;
            }

            if ($holder === $entityId) {
                return true;
            }

            $descendantId = $holder;
        }

        return false;
    }
}
