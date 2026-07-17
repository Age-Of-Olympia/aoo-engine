<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use Classes\View;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Items Phase 3 (docs/design-items-instances.md §3.3) — the map bridge:
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

        // INNER JOIN sur le lien de possession : une instance déjà au sol
        // (map_items_instances) ou déjà enveloppée (unique_objects) n'a
        // pas de ligne ici et ne peut pas gagner une seconde localisation.
        $row = $conn->fetchAssociative(
            "SELECT i.id, i.custom_name, i.destroyed, it.name AS catalog_name, l.equiped
             FROM item_instances i
             JOIN items it ON it.id = i.item_id
             JOIN players_items_instances l ON l.instance_id = i.id
             WHERE i.id = ?",
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

        $id = getNextEntityId('unique');
        // Id recyclé : purge des caches par-entité, sinon l'objet posé
        // ressert l'identité de l'ancienne entité (même garde que
        // BuildingService::place).
        BuildingService::purgeEntityCaches($id);
        $displayId = getNextDisplayId('unique');
        $coordsId = View::get_coords_id($goCoords);

        $avatar = 'img/items/' . $row['catalog_name'] . '.webp';
        if (!is_file(dirname(__DIR__, 2) . '/' . $avatar)) {
            $avatar = BuildingService::DEFAULT_IMAGE;
        }

        $conn->transactional(function ($conn) use ($id, $displayId, $name, $avatar, $coordsId, $instanceId): void {
            $conn->executeStatement(
                "INSERT INTO players
                    (id, player_type, display_id, name, race, avatar, portrait, coords_id, nextTurnTime, registerTime)
                 VALUES (?, 'unique', ?, ?, ?, ?, ?, ?, 0, ?)",
                [$id, $displayId, $name, self::ITEM_RACE, $avatar, $avatar, $coordsId, time()]
            );
            $conn->executeStatement(
                'INSERT INTO unique_objects (player_id, item_instance_id) VALUES (?, ?)',
                [$id, $instanceId]
            );
            // The instance's location is now the map: release the owner link.
            $conn->executeStatement('DELETE FROM players_items_instances WHERE instance_id = ?', [$instanceId]);
        });

        View::refresh_players_svg($goCoords);

        $this->addAuditLog("UniqueObjectService::placeInstance #{$instanceId} as unique #{$id}");

        return $id;
    }

    /**
     * Take a wrapped instance back: link it to the taker's inventory and
     * remove the map entity (component rows first, cache files purged —
     * same hygiene as BuildingService::remove()).
     *
     * @return int|null the taken instance id, null when the target wraps nothing
     */
    public function takeInstance(int $uniqueId, int $takerId): ?int
    {
        $conn = $this->entityManager->getConnection();

        $instanceId = $conn->fetchOne(
            "SELECT u.item_instance_id
             FROM unique_objects u JOIN players p ON p.id = u.player_id
             WHERE u.player_id = ? AND p.player_type = 'unique'",
            [$uniqueId]
        );
        if ($instanceId === false || $instanceId === null) {
            return null;
        }

        $goCoords = $conn->fetchAssociative(
            'SELECT c.x, c.y, c.z, c.plan FROM coords c JOIN players p ON p.coords_id = c.id WHERE p.id = ?',
            [$uniqueId]
        );

        $conn->transactional(function ($conn) use ($uniqueId, $takerId, $instanceId): void {
            $conn->executeStatement(
                'INSERT INTO players_items_instances (player_id, instance_id) VALUES (?, ?)',
                [$takerId, (int) $instanceId]
            );
            $conn->executeStatement('DELETE FROM unique_objects WHERE player_id = ?', [$uniqueId]);
            foreach (['players_bonus', 'players_effects', 'players_items'] as $table) {
                $conn->executeStatement("DELETE FROM {$table} WHERE player_id = ?", [$uniqueId]);
            }
            $conn->executeStatement('DELETE FROM players_logs WHERE player_id = ? OR target_id = ?', [$uniqueId, $uniqueId]);
            $conn->executeStatement('DELETE FROM players WHERE id = ?', [$uniqueId]);
        });

        foreach (['.json', '.svg', '.turn.json', '.caracs.json'] as $suffix) {
            @unlink(dirname(__DIR__, 2) . '/datas/private/players/' . $uniqueId . $suffix);
        }
        if ($goCoords !== false) {
            View::refresh_players_svg((object) $goCoords);
        }

        $this->addAuditLog("UniqueObjectService::takeInstance unique #{$uniqueId} -> player #{$takerId}");

        return (int) $instanceId;
    }

    /**
     * Destruction en jeu (0 PV) : l'entité disparaît de la carte et
     * l'instance enveloppée tombe BRISÉE au sol (durabilité 0, bourse)
     * — l'identité survit et reste réparable, cohérent avec les deux
     * états posé/construit.
     *
     * @return int|null the broken instance id, null when the target wraps nothing
     */
    public function destroyToGround(int $uniqueId): ?int
    {
        $conn = $this->entityManager->getConnection();

        $instanceId = $conn->fetchOne(
            "SELECT u.item_instance_id
             FROM unique_objects u JOIN players p ON p.id = u.player_id
             WHERE u.player_id = ? AND p.player_type = 'unique'",
            [$uniqueId]
        );
        if ($instanceId === false || $instanceId === null) {
            return null;
        }

        $coordsId = $conn->fetchOne('SELECT coords_id FROM players WHERE id = ?', [$uniqueId]);
        $goCoords = $conn->fetchAssociative(
            'SELECT x, y, z, plan FROM coords WHERE id = ?',
            [(int) $coordsId]
        );

        $conn->transactional(function ($conn) use ($uniqueId, $instanceId, $coordsId): void {
            $conn->executeStatement(
                'UPDATE item_instances SET durability = 0 WHERE id = ? AND durability > 0',
                [(int) $instanceId]
            );
            $conn->executeStatement(
                'INSERT INTO map_items_instances (coords_id, instance_id) VALUES (?, ?)',
                [(int) $coordsId, (int) $instanceId]
            );
            $conn->executeStatement('DELETE FROM unique_objects WHERE player_id = ?', [$uniqueId]);
            foreach (['players_bonus', 'players_effects', 'players_items'] as $table) {
                $conn->executeStatement("DELETE FROM {$table} WHERE player_id = ?", [$uniqueId]);
            }
            $conn->executeStatement('DELETE FROM players_logs WHERE player_id = ? OR target_id = ?', [$uniqueId, $uniqueId]);
            $conn->executeStatement('DELETE FROM players WHERE id = ?', [$uniqueId]);
        });

        foreach (['.json', '.svg', '.turn.json', '.caracs.json'] as $suffix) {
            @unlink(dirname(__DIR__, 2) . '/datas/private/players/' . $uniqueId . $suffix);
        }
        if ($goCoords !== false) {
            View::refresh_players_svg((object) $goCoords);
        }

        $this->addAuditLog("UniqueObjectService::destroyToGround unique #{$uniqueId} instance #{$instanceId}");

        return (int) $instanceId;
    }
}
