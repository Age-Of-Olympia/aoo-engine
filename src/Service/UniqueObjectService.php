<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use Classes\View;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Pont carte des objets (docs/design-items-instances.md §3.3) — the map bridge:
 * a UniqueObject wraps an item INSTANCE. The location invariant moves
 * with it: placing releases the owner link (the instance's location IS
 * the map), taking re-links it to the taker and removes the map entity.
 * Identity — wear, name, provenance — survives the round trip.
 */
class UniqueObjectService extends BaseService
{
    /** Base-stats row of a wrapped item on the map (seeded by migration). */
    public const ITEM_RACE = 'objet';

    private EntityManagerInterface $entityManager;

    public function __construct()
    {
        parent::__construct();
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    /**
     * Put an owned instance on the map as a UniqueObject.
     *
     * @return int the new unique object's players.id
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
            "SELECT i.id, i.custom_name, i.destroyed, it.name AS catalog_name, e.slot AS equiped
             FROM item_instances i
             JOIN items it ON it.id = i.item_id
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

        $name = $row['custom_name'] !== '' ? $row['custom_name'] : ucfirst((string) $row['catalog_name']);
        $coordsId = View::get_coords_id($goCoords);

        $avatar = 'img/items/' . $row['catalog_name'] . '.webp';
        if (!is_file(dirname(__DIR__, 2) . '/' . $avatar)) {
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

        $this->addAuditLog("UniqueObjectService::placeInstance #{$instanceId} as item #{$id}");

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
    public function takeInstance(int $uniqueId, int $takerId): ?int
    {
        $conn = $this->entityManager->getConnection();

        $instanceId = self::instanceIdOf($conn, $uniqueId);
        if ($instanceId === null) {
            return null;
        }

        $goCoords = $conn->fetchAssociative(
            'SELECT c.x, c.y, c.z, c.plan FROM coords c JOIN players p ON p.coords_id = c.id WHERE p.id = ?',
            [$uniqueId]
        );

        $conn->transactional(function ($conn) use ($uniqueId, $takerId): void {
            (new \App\Service\Map\EntityLocationService($conn))->putInside($uniqueId, $takerId);
        });

        BuildingService::purgeEntityCaches($uniqueId);
        if ($goCoords !== false) {
            View::refresh_players_svg((object) $goCoords);
        }

        $this->addAuditLog("UniqueObjectService::takeInstance unique #{$uniqueId} -> player #{$takerId}");

        return (int) $instanceId;
    }

    /**
     * Destruction en jeu (0 PV) : l'exemplaire posé tombe BRISÉ sur sa case
     * (durabilité 0). Son identité survit — il ne cesse pas d'exister, il
     * cesse de tenir sa case.
     *
     * @return int|null the broken instance id, null when the target is not an exemplar
     */
    public function destroyToGround(int $uniqueId): ?int
    {
        $conn = $this->entityManager->getConnection();

        $instanceId = self::instanceIdOf($conn, $uniqueId);
        if ($instanceId === null) {
            return null;
        }

        $coordsId = $conn->fetchOne('SELECT coords_id FROM players WHERE id = ?', [$uniqueId]);
        $goCoords = $conn->fetchAssociative(
            'SELECT x, y, z, plan FROM coords WHERE id = ?',
            [(int) $coordsId]
        );

        $conn->transactional(function ($conn) use ($uniqueId, $instanceId, $coordsId): void {
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
            (new \App\Service\Map\EntityLocationService($conn))->dropOnCell($uniqueId, (int) $coordsId);
        });

        BuildingService::purgeEntityCaches($uniqueId);
        if ($goCoords !== false) {
            View::refresh_players_svg((object) $goCoords);
        }

        $this->addAuditLog("UniqueObjectService::destroyToGround unique #{$uniqueId} instance #{$instanceId}");

        return (int) $instanceId;
    }
}
