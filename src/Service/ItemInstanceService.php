<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use Classes\Item;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Items Phase 1a (docs/design-items-instances.md §5c) — the lifecycle
 * of item INSTANCES under the lazy-promotion policy:
 *
 *   - promote(): a pristine unit leaves its stack and becomes an
 *     instance — called by the state events (equip, enchant, wear,
 *     map placement). Transactional: stack −1 + instance + link, or
 *     nothing.
 *   - create(): a brand-new instance born at craft (creator, date,
 *     custom name — the only moment a name can be set).
 *   - demote(): the ROLLBACK path — an instance whose state is still
 *     pristine returns to its stack. This is what makes the whole
 *     Phase 1 reversible while nothing has diverged.
 *
 * Invariant owned here: an instance has exactly ONE location (the
 * players_items_instances link for now; map/bank come with later
 * phases). No read path is switched yet — this service is inert until
 * the dual-read steps land.
 */
class ItemInstanceService extends BaseService
{
    private EntityManagerInterface $entityManager;

    public function __construct()
    {
        parent::__construct();
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    /**
     * Promote one pristine unit of the player's stack into an instance.
     *
     * @return int the new instance id
     *
     * @throws \RuntimeException when the player has no unit in stack
     */
    public function promote(int $playerId, int $itemId): int
    {
        $conn = $this->entityManager->getConnection();

        return $conn->transactional(function ($conn) use ($playerId, $itemId): int {
            // Lock the stack row and check availability atomically.
            $n = $conn->fetchOne(
                'SELECT n FROM players_items WHERE player_id = ? AND item_id = ? FOR UPDATE',
                [$playerId, $itemId]
            );
            if ($n === false || (int) $n < 1) {
                throw new \RuntimeException(
                    "Promotion impossible : le joueur #{$playerId} n'a pas d'exemplaire de l'objet #{$itemId} en pile."
                );
            }

            $conn->executeStatement(
                'UPDATE players_items SET n = n - 1 WHERE player_id = ? AND item_id = ?',
                [$playerId, $itemId]
            );
            $conn->executeStatement(
                'DELETE FROM players_items WHERE player_id = ? AND item_id = ? AND n <= 0 AND equiped = ""',
                [$playerId, $itemId]
            );

            $conn->executeStatement(
                'INSERT INTO item_instances (item_id, created_at) VALUES (?, ?)',
                [$itemId, time()]
            );
            $instanceId = (int) $conn->lastInsertId();

            $conn->executeStatement(
                'INSERT INTO players_items_instances (player_id, instance_id) VALUES (?, ?)',
                [$playerId, $instanceId]
            );

            return $instanceId;
        });
    }

    /**
     * Craft path: a brand-new instance, owned by $playerId. The ONLY
     * moment a custom name can be set (décision équipe 2026-07).
     */
    public function create(int $playerId, int $itemId, ?int $creatorId = null, string $customName = ''): int
    {
        $conn = $this->entityManager->getConnection();

        return $conn->transactional(function ($conn) use ($playerId, $itemId, $creatorId, $customName): int {
            $conn->executeStatement(
                'INSERT INTO item_instances (item_id, custom_name, creator_id, created_at) VALUES (?, ?, ?, ?)',
                [$itemId, $customName, $creatorId, time()]
            );
            $instanceId = (int) $conn->lastInsertId();

            $conn->executeStatement(
                'INSERT INTO players_items_instances (player_id, instance_id) VALUES (?, ?)',
                [$playerId, $instanceId]
            );

            return $instanceId;
        });
    }

    /**
     * Rollback path: return a PRISTINE, unequipped instance to its
     * owner's stack. Refuses as soon as any state diverged (wear,
     * name, alterations, destroyed) — a diverged instance has
     * something to lose, a pristine one by definition does not.
     */
    public function demote(int $instanceId): bool
    {
        $conn = $this->entityManager->getConnection();

        return $conn->transactional(function ($conn) use ($instanceId): bool {
            $row = $conn->fetchAssociative(
                'SELECT i.id, i.item_id, i.durability, i.durability_max, i.quality, i.custom_name,
                        i.params, i.destroyed, i.wear_pending, l.player_id, l.equiped
                 FROM item_instances i
                 JOIN players_items_instances l ON l.instance_id = i.id
                 WHERE i.id = ? FOR UPDATE',
                [$instanceId]
            );
            if ($row === false) {
                return false;
            }

            $pristine = (int) $row['durability'] === (int) $row['durability_max']
                && (int) $row['quality'] === 0
                && (string) $row['custom_name'] === ''
                && ($row['params'] === null || $row['params'] === '')
                && (int) $row['destroyed'] === 0
                && (int) $row['wear_pending'] === 0
                && (string) $row['equiped'] === '';
            if (!$pristine) {
                return false;
            }

            $conn->executeStatement(
                'INSERT INTO players_items (player_id, item_id, n) VALUES (?, ?, 1)
                 ON DUPLICATE KEY UPDATE n = n + 1',
                [(int) $row['player_id'], (int) $row['item_id']]
            );
            $conn->executeStatement('DELETE FROM players_items_instances WHERE instance_id = ?', [$instanceId]);
            $conn->executeStatement('DELETE FROM item_instances WHERE id = ?', [$instanceId]);

            return true;
        });
    }

    /**
     * Equip a catalog item through the instance path (Phase 1c): reuse
     * the player's OLDEST unequipped live instance of that item, else
     * promote one unit from the stack. Returns the equipped instance id.
     *
     * @throws \RuntimeException when the player owns no unit at all
     */
    public function equipCatalogItem(int $playerId, int $itemId, string $emplacement): int
    {
        $conn = $this->entityManager->getConnection();

        $existing = $conn->fetchOne(
            "SELECT l.instance_id
             FROM players_items_instances l
             JOIN item_instances i ON i.id = l.instance_id
             WHERE l.player_id = ? AND i.item_id = ? AND l.equiped = '' AND i.destroyed = 0
             ORDER BY l.instance_id LIMIT 1",
            [$playerId, $itemId]
        );

        $instanceId = $existing !== false ? (int) $existing : $this->promote($playerId, $itemId);

        $conn->executeStatement(
            'UPDATE players_items_instances SET equiped = ? WHERE instance_id = ?',
            [$emplacement, $instanceId]
        );

        return $instanceId;
    }

    /**
     * Unequip an instance; a still-pristine one silently returns to its
     * stack (the invisible round trip that keeps data lean), a diverged
     * one stays as an unequipped instance line.
     */
    public function unequipInstance(int $instanceId): void
    {
        $this->entityManager->getConnection()->executeStatement(
            "UPDATE players_items_instances SET equiped = '' WHERE instance_id = ?",
            [$instanceId]
        );

        $this->demote($instanceId);
    }

    /**
     * Clear the given emplacements for a player on the INSTANCE side —
     * the counterpart of Player::equip()'s legacy players_items clears
     * (target emplacement, deuxmains ↔ mains).
     *
     * @param string[] $emplacements
     */
    public function unequipEmplacements(int $playerId, array $emplacements): void
    {
        if ($emplacements === []) {
            return;
        }

        $ids = $this->entityManager->getConnection()->fetchFirstColumn(
            'SELECT instance_id FROM players_items_instances
             WHERE player_id = ? AND equiped IN (' . implode(',', array_fill(0, count($emplacements), '?')) . ')',
            array_merge([$playerId], $emplacements)
        );

        foreach ($ids as $id) {
            $this->unequipInstance((int) $id);
        }
    }

    /**
     * Instance rows shaped for Item::get_item_list()'s dual-read
     * (Phase 1b): catalog columns + n=1 + the instance meta.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForInventory(int $playerId, bool $equipedOnly): array
    {
        $equipedFilter = $equipedOnly ? "AND l.equiped != ''" : '';

        return $this->entityManager->getConnection()->fetchAllAssociative(
            "SELECT it.*, i.id AS instance_id, i.durability, i.durability_max, i.quality,
                    i.custom_name, i.params AS instance_params, i.creator_id, i.wear_pending,
                    l.equiped, 1 AS n
             FROM players_items_instances l
             JOIN item_instances i ON i.id = l.instance_id
             JOIN items it ON it.id = i.item_id
             WHERE l.player_id = ? AND i.destroyed = 0 {$equipedFilter}
             ORDER BY l.equiped DESC, i.id",
            [$playerId]
        );
    }

    /**
     * Live instances a player owns of one catalog item (optionally only
     * equipped ones) — the instance half of Item::get_n()'s dual count.
     */
    public function countInstances(int $playerId, int $itemId, bool $equipedOnly = false): int
    {
        $equipedFilter = $equipedOnly ? "AND l.equiped != ''" : '';

        return (int) $this->entityManager->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM players_items_instances l
             JOIN item_instances i ON i.id = l.instance_id
             WHERE l.player_id = ? AND i.item_id = ? AND i.destroyed = 0 {$equipedFilter}",
            [$playerId, $itemId]
        );
    }

    /**
     * All of a player's instances with their catalog name, worn first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getInstances(int $playerId): array
    {
        return $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT i.*, l.equiped, it.name AS catalog_name
             FROM players_items_instances l
             JOIN item_instances i ON i.id = l.instance_id
             JOIN items it ON it.id = i.item_id
             WHERE l.player_id = ?
             ORDER BY l.equiped DESC, i.id',
            [$playerId]
        );
    }

    /**
     * Total units a player owns of a catalog item, BOTH representations:
     * stack quantity + live (non-destroyed) instances. The future
     * dual-read shim for Item::get_n() — pinned by tests now so the
     * switch is a drop-in.
     */
    public function countOwned(int $playerId, int $itemId): int
    {
        $conn = $this->entityManager->getConnection();

        $stack = (int) ($conn->fetchOne(
            'SELECT n FROM players_items WHERE player_id = ? AND item_id = ?',
            [$playerId, $itemId]
        ) ?: 0);

        $instances = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM players_items_instances l
             JOIN item_instances i ON i.id = l.instance_id
             WHERE l.player_id = ? AND i.item_id = ? AND i.destroyed = 0',
            [$playerId, $itemId]
        );

        return $stack + $instances;
    }
}
