<?php

namespace App\Service\Map;

use App\Factory\EntityManagerFactory;
use App\Service\BuildingService;
use Doctrine\DBAL\Connection;

/**
 * Empties a cell of the map editor, one layer at a time (the info panel's
 * « Supprimer ») or all of them (the eraser) — one list, so the two gestures
 * cannot drift apart again. Characters and ground items are not map content:
 * no layer holds them.
 */
final class TileEraserService
{
    /** Layers that are plain rows of a `map_*` table. */
    private const TABLE_LAYERS = ['map_tiles', 'map_triggers', 'map_dialogs', 'map_elements', 'map_marks'];

    /** Every layer a cell can be emptied of, in the order the eraser runs. */
    public const LAYERS = [...self::TABLE_LAYERS, 'map_foregrounds', 'buildings', 'ressource', 'plante', 'route'];

    /** Lightens the cell by one shade step: undone the way it was painted. */
    public const SHADE_STEP = 'ombre';

    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /**
     * @return list<string> notices for what was left on purpose
     */
    public function eraseAll(int $coordsId): array
    {
        $notices = [];

        foreach (self::LAYERS as $layer) {
            $notices = array_merge($notices, $this->eraseLayer($coordsId, $layer));
        }

        return $notices;
    }

    /**
     * @param bool $force also remove buildings held by a player, a faction or still being built
     * @return list<string> notices for what was left on purpose
     * @throws \InvalidArgumentException on a layer this cell cannot be emptied of
     */
    public function eraseLayer(int $coordsId, string $layer, bool $force = false): array
    {
        $resources = new ResourceObjectService($this->conn);

        return match (true) {
            in_array($layer, self::TABLE_LAYERS, true) => $this->deleteRows($layer, $coordsId),
            $layer === 'map_foregrounds' => $this->eraseScenery($coordsId),
            $layer === 'buildings' => $this->eraseBuildings($coordsId, $force),
            $layer === 'ressource' => $this->removeEntities($resources->idsOn($coordsId)),
            $layer === 'plante' => $this->removeEntities($this->plantsOn($coordsId)),
            $layer === 'route' => $this->removeEntities((new GroundLayerService())->roadsOn($coordsId)),
            $layer === self::SHADE_STEP => $this->lightenShade($coordsId),
            default => throw new \InvalidArgumentException('Couche inconnue : ' . $layer),
        };
    }

    /** @return list<string> */
    private function deleteRows(string $table, int $coordsId): array
    {
        $this->conn->executeStatement('DELETE FROM ' . $table . ' WHERE coords_id = ?', [$coordsId]);

        return [];
    }

    /**
     * @param list<int> $ids
     * @return list<string>
     */
    private function removeEntities(array $ids): array
    {
        (new ResourceObjectService($this->conn))->removeEntities($ids);

        return [];
    }

    /** @return list<int> */
    private function plantsOn(int $coordsId): array
    {
        return array_map('intval', $this->conn->fetchFirstColumn(
            "SELECT id FROM players WHERE player_type = 'plant' AND coords_id = ?",
            [$coordsId]
        ));
    }

    /** @return list<string> */
    private function lightenShade(int $coordsId): array
    {
        $this->conn->executeStatement('UPDATE coords SET shade = GREATEST(shade - 1, 0) WHERE id = ?', [$coordsId]);

        return [];
    }

    /** @return list<string> */
    private function eraseBuildings(int $coordsId, bool $force): array
    {
        $buildings = new BuildingService();
        $notices = [];

        foreach ($buildings->holdingCell($coordsId) as $building) {
            $held = $building['owner_id'] !== null || $building['faction'] !== '' || $building['build_state'] !== 'built';

            if ($held && !$force) {
                $notices[] = 'bâtiment #' . $building['player_id'] . ' protégé (propriétaire/faction/état)';
                continue;
            }

            // Shelved, not deleted: remove() would clear its players_logs history
            $buildings->vanish($building['player_id']);
        }

        return $notices;
    }

    /**
     * A scenery piece takes its whole figure with it, and the cell's shade.
     *
     * @return list<string>
     */
    private function eraseScenery(int $coordsId): array
    {
        $objects = new SceneryObjectService();
        $cells = [$coordsId];

        foreach ($this->conn->fetchFirstColumn('SELECT name FROM map_foregrounds WHERE coords_id = ?', [$coordsId]) as $name) {
            $cells = array_merge($cells, $objects->objectCellsAt($coordsId, (string) $name));
        }

        $cells = array_values(array_unique(array_map('intval', $cells)));

        $this->conn->executeStatement('DELETE FROM map_foregrounds WHERE coords_id IN (' . implode(',', $cells) . ')');
        $objects->removeEntitiesOn($cells);
        $this->conn->executeStatement('UPDATE coords SET shade = 0 WHERE id = ?', [$coordsId]);

        return [];
    }
}
