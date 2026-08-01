<?php

namespace App\Service\Map;

use App\Factory\EntityManagerFactory;
use App\Service\BuildingService;
use Doctrine\DBAL\Connection;

/**
 * The editor's gestures on a resource, one cell at a time.
 *
 * Sibling of {@see SceneryObjectService}: same office, for the harvestable
 * things. Tiled poses, erases and cycles cell by cell; {@see
 * ResourceReconciler} does it in bulk for a whole plan. Both take resources
 * off the board through `removeEntities()`, so that retiring one means the
 * same thing whoever asks.
 */
final class ResourceObjectService
{
    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /**
     * Put one resource on a cell.
     *
     * @return int the new entity's players.id
     */
    public function placeAt(string $type, int $coordsId, bool $exhausted = false): int
    {
        $label = (string) ($this->conn->fetchOne('SELECT label FROM races WHERE name = ?', [$type]) ?: '');

        $id = (new EntityPlacementService($this->conn))->create(
            'resource',
            $type,
            $coordsId,
            $label !== '' ? $label : $type,
            'img/walls/' . $type . '.png'
        );

        /* An id can be recycled from a resource erased a moment ago, and its
         * cached identity would outlive it. */
        BuildingService::purgeEntityCaches($id);

        if ($exhausted) {
            (new ResourceStateService($this->conn))->exhaust([$id]);
        }

        return $id;
    }

    /**
     * The resources standing on a cell.
     *
     * @return list<int>
     */
    public function idsOn(int $coordsId): array
    {
        return array_map(
            'intval',
            $this->conn->fetchFirstColumn(
                "SELECT id FROM players WHERE player_type = 'resource' AND coords_id = ?",
                [$coordsId]
            )
        );
    }

    /**
     * Take resources off the board, satellite included.
     *
     * `entity_cells` cascades with the entity; `resources` does not — it has
     * no foreign key, so a row left behind would claim a state for whatever
     * id lands there next.
     *
     * @param list<int> $ids
     */
    public function removeEntities(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $this->conn->executeStatement("DELETE FROM resources WHERE player_id IN ({$placeholders})", $ids);
        $this->conn->executeStatement("DELETE FROM players WHERE id IN ({$placeholders})", $ids);

        foreach ($ids as $id) {
            BuildingService::purgeEntityCaches($id);
        }
    }

    /**
     * Turn what stands on the cell from standing to exhausted, and back.
     *
     * The button used to walk a three-state cycle — normal, récoltable,
     * épuisé — because one table held both walls and resources, and `damages`
     * had to say which. An entity of type `resource` IS harvestable; a thing
     * that is not is a structure, and no longer lives here. Two states left,
     * and the gesture is the same click.
     *
     * @return bool|null the new state, or null when no resource stands there
     */
    public function cycleState(int $coordsId): ?bool
    {
        $ids = $this->idsOn($coordsId);

        if ($ids === []) {
            return null;
        }

        $state = new ResourceStateService($this->conn);
        $exhausted = !$state->isExhausted($ids[0]);

        $exhausted ? $state->exhaust($ids) : $state->regrow($ids);

        return $exhausted;
    }
}
