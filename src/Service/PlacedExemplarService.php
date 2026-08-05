<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use Classes\View;
use Doctrine\ORM\EntityManagerInterface;

/**
 * An exemplar standing ON the board: placing it, taking it back, and what
 * becomes of it at zero life.
 *
 * The exemplar IS the entity — there is no wrapper around it — so placing and
 * taking only move where it is held: a placed one belongs to its cell, a taken
 * one to its taker. Identity (wear, name, provenance) survives the round trip.
 *
 * Its bag-side counterpart is {@see ItemInstanceService}, which creates,
 * equips, banks and collects.
 */
class PlacedExemplarService extends BaseService
{

    private EntityManagerInterface $entityManager;

    public function __construct()
    {
        parent::__construct();
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    /**
     * Put an owned instance on the map, where it stands as itself.
     *
     * @return int the exemplar's players.id
     *
     * @throws \InvalidArgumentException when the instance doesn't exist,
     *         is destroyed, is still equipped, or is not held by a player
     *         (already on the ground or already wrapped — an instance has
     *         exactly UNE localisation)
     */
    public function placeInstance(int $instanceId, object $goCoords): int
    {
        $conn = $this->entityManager->getConnection();

        // Held by somebody: an instance already on a cell, or already wrapped,
        // has no holder and cannot gain a second location.
        $row = $conn->fetchAssociative(
            "SELECT i.id, i.custom_name, i.destroyed, it.name AS catalog_name, e.slot AS equiped,
                    " . ItemInstanceService::WEAR_CURRENT . "
             FROM item_instances i
             JOIN items it ON it.id = i.item_id
             " . ItemInstanceService::WEAR_JOIN . "
             JOIN players e ON e.id = i.entity_id
             WHERE i.id = ? AND e.holder_id IS NOT NULL",
            [$instanceId]
        );
        if ($row === false || (int) $row['destroyed'] === 1) {
            throw new \InvalidArgumentException(
                "Instance #{$instanceId} introuvable, détruite ou non portée par un joueur."
            );
        }
        if (($row['equiped'] ?? '') !== '') {
            throw new \InvalidArgumentException("Instance #{$instanceId} encore équipée — déséquiper d'abord.");
        }
        // What is smashed does not stand back up by being posed: repair first.
        if (ItemInstanceService::isBroken((int) $row['durability'])) {
            throw new \InvalidArgumentException('Brisé, cela ne se pose plus — réparez-le d\'abord.');
        }

        $name = $row['custom_name'] !== '' ? $row['custom_name'] : ucfirst((string) $row['catalog_name']);
        $coordsId = View::get_coords_id($goCoords);

        /* The BOARD sprite rule for a placed object: structure art
         * first (a chest fills its tile), then item art. The initials
         * frame is drawn at render time, never stored — the column
         * carries a path or nothing. */
        $avatar = View::boardExemplarSprite((string) $row['catalog_name'], $name);
        if (!str_starts_with($avatar, 'img/')) {
            $avatar = BuildingService::NO_IMAGE;
        }

        $id = 0;
        $conn->transactional(function ($conn) use (&$id, $name, $avatar, $coordsId, $instanceId): void {
            /* The exemplar's OWN entity is installed: no shell to mint, no
             * bridge to keep. What is placed is the sword itself, so its wear,
             * its name and its contents come along and survive the pickup. */
            $id = ItemInstanceService::ensureEntity($conn, $instanceId);

            $conn->executeStatement(
                'UPDATE players SET name = ?, avatar = ?, portrait = ? WHERE id = ?',
                [$name, $avatar, $avatar, $id]
            );

            (new \App\Service\Map\EntityLocationService($conn))->installOnCell($id, (int) $coordsId);
        });

        View::refresh_players_svg($goCoords);

        $this->addAuditLog("PlacedExemplarService::placeInstance #{$instanceId} as item #{$id}");

        return $id;
    }

    /**
     * The exemplar an INSTALLED entity is, or null when it is not one.
     *
     * The bridge is gone: a placed object no longer wraps an exemplar, it IS
     * one. The question is therefore asked of `item_instances.entity_id`.
     */
    public static function instanceIdOf(\Doctrine\DBAL\Connection $conn, int $entityId): ?int
    {
        $instanceId = $conn->fetchOne(
            'SELECT i.id FROM item_instances i
               JOIN players p ON p.id = i.entity_id
              WHERE p.id = ? AND p.player_type = ?',
            [$entityId, ItemInstanceService::ENTITY_TYPE]
        );

        return ($instanceId === false || $instanceId === null) ? null : (int) $instanceId;
    }

    /**
     * Take a placed exemplar back into a bag.
     *
     * The entity SURVIVES — it is the exemplar. Only its location changes, from
     * a cell to the taker, which is what makes the round trip keep everything
     * the object is.
     *
     * @return int|null the taken instance id, null when the target is not an exemplar
     */
    public function takeInstance(int $exemplarId, int $takerId): ?int
    {
        $conn = $this->entityManager->getConnection();

        $instanceId = self::instanceIdOf($conn, $exemplarId);
        if ($instanceId === null) {
            return null;
        }

        $goCoords = $conn->fetchAssociative(
            'SELECT c.x, c.y, c.z, c.plan FROM coords c JOIN players p ON p.coords_id = c.id WHERE p.id = ?',
            [$exemplarId]
        );

        $conn->transactional(function ($conn) use ($exemplarId, $takerId): void {
            (new \App\Service\Map\EntityLocationService($conn))->putInside($exemplarId, $takerId);
        });

        BuildingService::purgeEntityCaches($exemplarId);
        if ($goCoords !== false) {
            View::refresh_players_svg((object) $goCoords);
        }

        $this->addAuditLog("PlacedExemplarService::takeInstance exemplar #{$exemplarId} -> player #{$takerId}");

        return (int) $instanceId;
    }

    /**
     * At zero life, a placed exemplar falls BROKEN onto its own cell. It does
     * not stop existing, it stops holding the tile — so its identity survives.
     *
     * @return int|null the broken instance id, null when the target is not an exemplar
     */
    public function destroyToGround(int $exemplarId): ?int
    {
        $conn = $this->entityManager->getConnection();

        $instanceId = self::instanceIdOf($conn, $exemplarId);
        if ($instanceId === null) {
            return null;
        }

        $coordsId = $conn->fetchOne('SELECT coords_id FROM players WHERE id = ?', [$exemplarId]);
        $goCoords = $conn->fetchAssociative(
            'SELECT x, y, z, plan FROM coords WHERE id = ?',
            [(int) $coordsId]
        );

        /* Broken open, it spills before anything else: inventory with the
         * CHARACTER loot rules (same service, same rolls), hidden slots
         * (the walls' fabric) included — what it held must not stay shut
         * inside a wreck. */
        (new LootSpillService())->spill(\App\Factory\PlayerFactory::legacy($exemplarId));

        /* Then the item type decides the wreck's fate: vanish_on_break
         * erases the husk — the loot on the ground is all that remains —
         * while the default lies broken on its tile, repairable. */
        $vanishes = (bool) $conn->fetchOne(
            'SELECT it.vanish_on_break FROM item_instances i JOIN items it ON it.id = i.item_id WHERE i.id = ?',
            [(int) $instanceId]
        );

        $conn->transactional(function ($conn) use ($exemplarId, $instanceId, $coordsId, $vanishes): void {
            if ($vanishes) {
                $conn->executeStatement(
                    'UPDATE item_instances SET destroyed = 1 WHERE id = ?',
                    [(int) $instanceId]
                );
                foreach (['players_bonus', 'players_effects', 'players_items'] as $table) {
                    $conn->executeStatement("DELETE FROM {$table} WHERE player_id = ?", [$exemplarId]);
                }
                (new \App\Service\Map\EntityLocationService($conn))->shelve($exemplarId);

                return;
            }

            $conn->executeStatement(
                "INSERT INTO players_bonus (player_id, name, n)
                 SELECT i.entity_id, 'pv', -it.durability_max
                   FROM item_instances i JOIN items it ON it.id = i.item_id
                  WHERE i.id = ?
                 ON DUPLICATE KEY UPDATE n = VALUES(n)",
                [(int) $instanceId]
            );

            /* Smashed, it stops holding its tile and lies on it instead. The
             * same entity throughout: nothing is deleted, nothing re-created. */
            (new \App\Service\Map\EntityLocationService($conn))->dropOnCell($exemplarId, (int) $coordsId);
        });

        BuildingService::purgeEntityCaches($exemplarId);
        if ($goCoords !== false) {
            View::refresh_players_svg((object) $goCoords);
        }

        $this->addAuditLog("PlacedExemplarService::destroyToGround exemplar #{$exemplarId} instance #{$instanceId}");

        return (int) $instanceId;
    }
}
