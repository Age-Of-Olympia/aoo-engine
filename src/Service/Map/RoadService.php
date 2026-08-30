<?php

namespace App\Service\Map;

use App\Factory\EntityManagerFactory;

/**
 * Is there a road on this cell?
 *
 * Roads come from two places, and `courir` only ever knew one of them:
 *
 * - the map editor writes a `map_routes` row;
 * - a player crafts a `route`, then places it, which installs an exemplar
 *   on the cell ({@see \App\Service\ItemInstanceService::installFromCatalogAt}).
 *
 * The second path arrived when the placement action moved to
 * `placestructure`, and nothing taught `courir` about it: a road a player
 * had crafted, carried and laid gave no bonus at all, which is the whole
 * reason to build one. Hence this single question, asked in one place.
 *
 * Road items are recognised by `items.subtype = 'routes'` — the vocabulary
 * the workbench already documents ("melee, tir, jet, walls, routes…"), and
 * one that holds for the several road types to come.
 *
 * When roads become entities of their own, only this class changes.
 */
final class RoadService
{
    /** The workbench subtype that marks an item as a road. */
    public const SUBTYPE = 'routes';

    public function hasRoadAt(int $coordsId): bool
    {
        $conn = EntityManagerFactory::getEntityManager()->getConnection();

        if ((bool) $conn->fetchOne('SELECT 1 FROM map_routes WHERE coords_id = ? LIMIT 1', [$coordsId])) {
            return true;
        }

        return (bool) $conn->fetchOne(
            "SELECT 1
               FROM players e
               JOIN item_instances i ON i.entity_id = e.id
               JOIN items it ON it.id = i.item_id
              WHERE e.coords_id = ?
                AND e.slot = ?
                AND i.destroyed = 0
                AND CONVERT(it.subtype USING utf8mb4) = CONVERT(? USING utf8mb4)
              LIMIT 1",
            [$coordsId, EntityLocationService::SLOT_INSTALLED, self::SUBTYPE]
        );
    }
}
