<?php

namespace App\Service;

use App\Entity\BuildingDetails;
use App\Entity\EntityManagerFactory;
use Classes\View;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Creation and lookup of buildings — `players` rows with
 * player_type='building' plus their 1:1 `buildings` satellite row
 * (docs/design-buildings-entities.md §4.7).
 *
 * Mirrors Player::put_player(): reserved id range, display id, coords
 * row reuse. The building's max PV comes from its archetype's
 * non-playable pseudo-race (§4.6) through the untouched caracs
 * pipeline, so damage works with zero new code
 * (putBonus / getRemaining on the legacy Player).
 */
class BuildingService extends BaseService
{
    private EntityManagerInterface $entityManager;

    public function __construct()
    {
        parent::__construct();
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    /**
     * Place a building of the given archetype on the map.
     *
     * @param string      $archetype non-playable race name carrying the base
     *                               stats ('palissade', …)
     * @param object      $goCoords  stdClass {x, y, z, plan} — the target tile
     * @param int|null    $ownerId   players.id of the owning character, if any
     * @param string      $faction   faction CODE from the catalog, '' = neutral
     * @param string|null $name      display name; defaults to the race label
     *
     * @return int the new building's players.id (ENTITY_ID_RANGES['building'])
     *
     * @throws \InvalidArgumentException on unknown/playable archetype,
     *                                   unknown faction code or unknown owner
     */
    public function place(
        string $archetype,
        object $goCoords,
        ?int $ownerId = null,
        string $faction = '',
        ?string $name = null
    ): int {
        $race = (new RaceService())->getRaceByName($archetype);
        if ($race === null) {
            throw new \InvalidArgumentException(
                "Archétype inconnu : '{$archetype}' (aucune race de ce nom)."
            );
        }
        if ($race->getPlayable()) {
            throw new \InvalidArgumentException(
                "L'archétype '{$archetype}' est une race jouable — un bâtiment exige une pseudo-race non jouable."
            );
        }

        if ($faction !== '' && (new FactionService())->getFactionByCode($faction) === null) {
            throw new \InvalidArgumentException("Faction inconnue : '{$faction}'.");
        }

        $conn = $this->entityManager->getConnection();

        if ($ownerId !== null) {
            $ownerExists = $conn->fetchOne('SELECT id FROM players WHERE id = ?', [$ownerId]);
            if ($ownerExists === false) {
                throw new \InvalidArgumentException("Propriétaire inconnu : joueur #{$ownerId}.");
            }
        }

        $id = getNextEntityId('building');
        $displayId = getNextDisplayId('building');
        $coordsId = View::get_coords_id($goCoords);

        $conn->executeStatement(
            'INSERT INTO players
                (id, player_type, display_id, name, race, avatar, portrait, coords_id, nextTurnTime, registerTime)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?)',
            [
                $id,
                'building',
                $displayId,
                $name ?? $race->getLabel(),
                $archetype,
                'img/avatars/' . $archetype . '.webp',
                'img/portraits/' . $archetype . '.jpeg',
                $coordsId,
                time(),
            ]
        );

        $conn->executeStatement(
            'INSERT INTO buildings (player_id, archetype, owner_id, faction, build_state)
             VALUES (?, ?, ?, ?, ?)',
            [$id, $archetype, $ownerId, $faction, BuildingDetails::STATE_BUILT]
        );

        $this->addAuditLog("BuildingService::place {$archetype} #{$id} at ({$goCoords->x},{$goCoords->y},{$goCoords->plan})");

        return $id;
    }

    /**
     * The building's satellite row, or null when the id is not a building.
     */
    public function getDetails(int $playerId): ?BuildingDetails
    {
        return $this->entityManager->find(BuildingDetails::class, $playerId);
    }

    /**
     * Remove a building: satellite row + players row. Wounds and other
     * component rows are deleted first so no FK is left dangling. The
     * destruction GAME flow (drop materials, ruin state…) is the death-path
     * branch of roadmap step 6 — this is only the bare removal primitive
     * it will build on.
     */
    public function remove(int $playerId): bool
    {
        $conn = $this->entityManager->getConnection();

        $isBuilding = $conn->fetchOne(
            "SELECT id FROM players WHERE id = ? AND player_type = 'building'",
            [$playerId]
        );
        if ($isBuilding === false) {
            return false;
        }

        $conn->executeStatement('DELETE FROM buildings WHERE player_id = ?', [$playerId]);
        foreach (['players_bonus', 'players_effects', 'players_items'] as $table) {
            $conn->executeStatement("DELETE FROM {$table} WHERE player_id = ?", [$playerId]);
        }
        $conn->executeStatement('DELETE FROM players_logs WHERE player_id = ? OR target_id = ?', [$playerId, $playerId]);
        $conn->executeStatement('DELETE FROM players WHERE id = ?', [$playerId]);

        $this->addAuditLog("BuildingService::remove #{$playerId}");

        return true;
    }
}
