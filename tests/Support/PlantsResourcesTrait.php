<?php

namespace Tests\Support;

use App\Service\Map\ResourceStateService;
use Doctrine\DBAL\Connection;

/**
 * Stands a harvestable resource on a cell, the way the world holds one.
 *
 * A resource used to be a row in `map_resources` with a `damages` of -1, and
 * every test that needed one wrote that row. It is an entity now — a player of
 * type `resource`, a cell it refuses the step on, and a satellite that only
 * exists once it runs dry — so a test writing the old row plants something the
 * game no longer sees.
 *
 * Shared rather than copied a seventh time: the shape of a resource is one
 * fact, and when it moves again it should move in one place.
 */
trait PlantsResourcesTrait
{
    /** Well above any converted id, below the range's ceiling. */
    private static int $resourceFixtureNext = 59995000;

    /**
     * @param int $damages legacy dialect kept for the golden masters: -1 stands, -2 dry
     * @return int the entity's id
     */
    protected function plantResource(
        Connection $conn,
        string $name,
        int $coordsId,
        string $plan,
        int $x,
        int $y,
        int $z = 0,
        int $damages = -1
    ): int {
        $id = self::$resourceFixtureNext++;

        $conn->executeStatement(
            "INSERT INTO players (id, name, race, coords_id, player_type)
             VALUES (?, ?, ?, ?, 'resource')",
            [$id, ucfirst($name), $name, $coordsId]
        );

        $conn->executeStatement(
            "INSERT INTO entity_cells (player_id, coords_id, plan, z, x, y, piece, role)
             VALUES (?, ?, ?, ?, ?, ?, 0, 'block')",
            [$id, $coordsId, $plan, $z, $x, $y]
        );

        if ($damages === -2) {
            (new ResourceStateService($conn))->exhaust([$id]);
        }

        return $id;
    }

    /** Removes what plantResource stood up, satellite and cell included. */
    protected function uprootResources(Connection $conn, string $plan): void
    {
        foreach ($conn->fetchFirstColumn(
            "SELECT p.id FROM players p
               JOIN entity_cells ec ON ec.player_id = p.id
              WHERE p.player_type = 'resource' AND ec.plan = ?",
            [$plan]
        ) as $id) {
            $conn->executeStatement('DELETE FROM resources WHERE player_id = ?', [(int) $id]);
            $conn->executeStatement('DELETE FROM entity_cells WHERE player_id = ?', [(int) $id]);
            $conn->executeStatement('DELETE FROM players WHERE id = ?', [(int) $id]);
        }
    }
}
