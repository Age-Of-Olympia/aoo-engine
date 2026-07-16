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
 * row reuse. The building's max PV comes from its type's
 * non-playable pseudo-race (§4.6) through the untouched caracs
 * pipeline, so damage works with zero new code
 * (putBonus / getRemaining on the legacy Player).
 */
class BuildingService extends BaseService
{
    /**
     * Tile/portrait image used when the type has no dedicated asset
     * yet — an existing wall sprite, so a freshly placed building renders
     * on the damier without any art step.
     */
    public const DEFAULT_IMAGE = 'img/walls/barricade.png';

    private EntityManagerInterface $entityManager;

    public function __construct()
    {
        parent::__construct();
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    /**
     * Place a building of the given type on the map.
     *
     * The type is a races row of kind 'structure' (the races table is the
     * catalog of entity base stats); it lands in players.race like any
     * entity — no duplicate "archetype" storage.
     *
     * @param string      $type     structure-kind race name ('palissade', …)
     * @param object      $goCoords stdClass {x, y, z, plan} — the target tile
     * @param int|null    $ownerId  players.id of the owning character, if any
     * @param string      $faction  faction CODE from the catalog, '' = neutral
     * @param string|null $name     display name; defaults to the race label
     *
     * @return int the new building's players.id (ENTITY_ID_RANGES['building'])
     *
     * @throws \InvalidArgumentException on unknown/non-structure type,
     *                                   unknown faction code or unknown owner
     */
    public function place(
        string $type,
        object $goCoords,
        ?int $ownerId = null,
        string $faction = '',
        ?string $name = null
    ): int {
        $race = (new RaceService())->getRaceByName($type);
        if ($race === null) {
            throw new \InvalidArgumentException(
                "Type inconnu : '{$type}' (aucune entrée de ce nom au catalogue races)."
            );
        }
        if (!$race->isStructureKind()) {
            throw new \InvalidArgumentException(
                "'{$type}' n'est pas un type de structure (races.kind) — une race de personnage ne peut pas être posée en bâtiment."
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

        // Type-specific art when it exists, working placeholder otherwise
        // (View.php renders players.avatar directly as the tile image).
        $avatar = 'img/avatars/' . $type . '.webp';
        if (!is_file(dirname(__DIR__, 2) . '/' . $avatar)) {
            $avatar = self::DEFAULT_IMAGE;
        }

        $conn->executeStatement(
            'INSERT INTO players
                (id, player_type, display_id, name, race, avatar, portrait, coords_id, nextTurnTime, registerTime)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?)',
            [
                $id,
                'building',
                $displayId,
                $name ?? $race->getLabel(),
                $type,
                $avatar,
                $avatar,
                $coordsId,
                time(),
            ]
        );

        $conn->executeStatement(
            'INSERT INTO buildings (player_id, owner_id, faction, build_state)
             VALUES (?, ?, ?, ?)',
            [$id, $ownerId, $faction, BuildingDetails::STATE_BUILT]
        );

        // Le damier de chaque joueur est un SVG caché : invalider le
        // voisinage pour que le bâtiment apparaisse sans attendre un
        // déplacement.
        View::refresh_players_svg($goCoords);

        $this->addAuditLog("BuildingService::place {$type} #{$id} at ({$goCoords->x},{$goCoords->y},{$goCoords->plan})");

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
     * Every building with its position, state, owner and PV, for the admin
     * dashboard. Current PV = pseudo-race max + the players_bonus 'pv'
     * ledger (buildings have no upgrades/items, so the race base IS max).
     *
     * @return array<int, array{id:int, name:string, type:string, build_state:string,
     *                          faction:string, owner_id:?int, owner_name:?string,
     *                          x:int, y:int, plan:string, max_pv:int, current_pv:int}>
     */
    public function listBuildings(): array
    {
        // races is joined in PHP via the cached RaceService: the table was
        // created under a newer default collation than players and a SQL
        // join on r.name = p.race trips "illegal mix of collations".
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            "SELECT p.id, p.name, p.race, b.build_state, b.faction, b.owner_id,
                    o.name AS owner_name, c.x, c.y, c.plan,
                    COALESCE(pb.n, 0) AS pv_bonus
             FROM buildings b
             JOIN players p ON p.id = b.player_id
             JOIN coords c ON c.id = p.coords_id
             LEFT JOIN players o ON o.id = b.owner_id
             LEFT JOIN players_bonus pb ON pb.player_id = p.id AND pb.name = 'pv'
             ORDER BY c.plan, p.id"
        );

        $raceService = new RaceService();

        return array_map(static function (array $row) use ($raceService): array {
            $race = $raceService->getRaceByName((string) $row['race']);
            $maxPv = $race !== null ? $race->getCarac('pv') : 0;

            return [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'type' => (string) $row['race'],
                'build_state' => (string) $row['build_state'],
                'faction' => (string) $row['faction'],
                'owner_id' => $row['owner_id'] !== null ? (int) $row['owner_id'] : null,
                'owner_name' => $row['owner_name'] !== null ? (string) $row['owner_name'] : null,
                'x' => (int) $row['x'],
                'y' => (int) $row['y'],
                'plan' => (string) $row['plan'],
                'max_pv' => $maxPv,
                'current_pv' => $maxPv + (int) $row['pv_bonus'],
            ];
        }, $rows);
    }

    /**
     * Admin full-restore of any STRUCTURE (building or unique object):
     * clear the PV wound ledger and, for buildings, flip build_state back
     * to 'built'.
     *
     * NOT the future in-game repair: PV restoration is the HEAL mechanic
     * (putBonus(['pv' => +x]) works on structures like on characters), so
     * the player-facing action will be a heal-type action gated by
     * TargetType ['structure'] — no parallel pipeline.
     */
    public function restore(int $playerId): bool
    {
        $conn = $this->entityManager->getConnection();

        $isStructure = $conn->fetchOne(
            "SELECT id FROM players WHERE id = ? AND player_type IN ('building', 'unique')",
            [$playerId]
        );
        if ($isStructure === false) {
            return false;
        }

        $conn->executeStatement(
            "DELETE FROM players_bonus WHERE player_id = ? AND name = 'pv'",
            [$playerId]
        );
        // No-op for unique objects: only buildings carry a lifecycle state.
        $conn->executeStatement(
            'UPDATE buildings SET build_state = ? WHERE player_id = ?',
            [BuildingDetails::STATE_BUILT, $playerId]
        );

        $this->addAuditLog("BuildingService::restore #{$playerId}");

        return true;
    }

    /**
     * Flip the building to its destroyed state (build_state = 'ruin').
     * The players row STAYS: logs keep their FK targets and the ruin
     * still occupies the tile. Death-path callers only — admin removal
     * is remove().
     */
    public function markDestroyed(int $playerId): bool
    {
        $affected = $this->entityManager->getConnection()->executeStatement(
            'UPDATE buildings SET build_state = ? WHERE player_id = ?',
            [BuildingDetails::STATE_RUIN, $playerId]
        );

        if ($affected > 0) {
            $this->addAuditLog("BuildingService::markDestroyed #{$playerId}");
        }

        return $affected > 0;
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

        $goCoords = $conn->fetchAssociative(
            'SELECT c.x, c.y, c.z, c.plan FROM coords c JOIN players p ON p.coords_id = c.id WHERE p.id = ?',
            [$playerId]
        );

        $conn->executeStatement('DELETE FROM buildings WHERE player_id = ?', [$playerId]);
        foreach (['players_bonus', 'players_effects', 'players_items'] as $table) {
            $conn->executeStatement("DELETE FROM {$table} WHERE player_id = ?", [$playerId]);
        }
        $conn->executeStatement('DELETE FROM players_logs WHERE player_id = ? OR target_id = ?', [$playerId, $playerId]);
        $conn->executeStatement('DELETE FROM players WHERE id = ?', [$playerId]);

        if ($goCoords !== false) {
            View::refresh_players_svg((object) $goCoords);
        }

        // refresh_players_svg ne balaie que les lignes ENCORE présentes :
        // purger explicitement les caches du bâtiment supprimé, sinon un id
        // recyclé ressert le vieux SVG.
        foreach (['.json', '.svg', '.turn.json', '.caracs.json'] as $suffix) {
            @unlink(dirname(__DIR__, 2) . '/datas/private/players/' . $playerId . $suffix);
        }

        $this->addAuditLog("BuildingService::remove #{$playerId}");

        return true;
    }
}
