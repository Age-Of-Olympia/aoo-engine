<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Roads go back to being ground, and the ones already laid are rescued.
 *
 * A road is walked ON. It belongs to `map_routes`, the layer the map editor
 * writes and that everything reads: the running bonus (`courir` →
 * TileTypeOutcomeInstruction → `MapService::getTileTypeAtCoord`), the drawn
 * map, `observe`, and the rule keeping plants off roads.
 *
 * When the placement action moved to `placestructure`, road items followed
 * the object path with it and got INSTALLED on the cell instead. The board
 * then drew an object where a road should lie, and no reader of roads saw
 * one — so laying a road stopped granting the +1 MVT that is the whole
 * reason to lay one.
 *
 * Two things here:
 *
 * - road items carry `subtype = 'routes'`, which is how the placement now
 *   tells a layer from an object (and the vocabulary the workbench field
 *   already documents);
 * - every road already installed as an object becomes a `map_routes` row on
 *   its cell, and its exemplar goes. Players keep what they built, in the
 *   form it should always have taken.
 */
final class Version20260831100000_RoadsAreGroundAgain extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'une route posée redevient du sol (map_routes) : sous-type « routes » sur les objets, et les routes déjà posées en objet sont récupérées';
    }

    /**
     * The road exemplars this migration retires: installed, and of a type the
     * catalogue calls a ground layer. Written once — the rescue reads it, then
     * every dependent row is cleared through it, then the rows themselves.
     */
    private const ROAD_EXEMPLARS = "SELECT e.id
           FROM players e
           JOIN item_instances i ON i.entity_id = e.id
           JOIN items it ON it.id = i.item_id
          WHERE e.slot = 'installed'
            AND CONVERT(it.subtype USING utf8mb4) = CONVERT('routes' USING utf8mb4)";

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE items SET subtype = 'routes'
              WHERE type = 'constructible'
                AND subtype = ''
                AND name IN ('route')"
        );

        /* Rescue what was laid the wrong way. The owner comes from the
           exemplar's owner, falling back to its creator: a road laid by a
           player must stay theirs. */
        $this->addSql(
            "INSERT INTO map_routes (name, coords_id, player_id)
             SELECT it.name, e.coords_id, COALESCE(e.owner_id, i.creator_id)
               FROM players e
               JOIN item_instances i ON i.entity_id = e.id
               JOIN items it ON it.id = i.item_id
              WHERE e.slot = 'installed'
                AND e.coords_id IS NOT NULL
                AND i.destroyed = 0
                AND CONVERT(it.subtype USING utf8mb4) = CONVERT('routes' USING utf8mb4)
                AND NOT EXISTS (
                    SELECT 1 FROM map_routes mr WHERE mr.coords_id = e.coords_id
                )"
        );

        /* Then the exemplars go, dependents first.
         *
         * An entity row is referenced from a dozen tables, so a bare DELETE on
         * `players` fails on a foreign key — which is what happened on the
         * experimental server. The sweep that followed was worse: it removed
         * every installed exemplar with no instance row, not just the roads,
         * and a chest keeps its contents in `players_items`. Both faults are
         * the same one — the delete was not scoped to what this migration
         * touches. It is now, and it clears each dependent table the way
         * BuildingService::deleteEntityRows does. */
        $ids = 'IN (SELECT id FROM (' . self::ROAD_EXEMPLARS . ') AS road_ids)';

        foreach (['players_bonus', 'players_effects', 'players_items', 'players_options', 'entity_cells'] as $table) {
            $this->addSql("DELETE FROM {$table} WHERE player_id {$ids}");
        }

        foreach (['players_logs', 'players_kills', 'players_assists'] as $table) {
            $this->addSql("DELETE FROM {$table} WHERE player_id {$ids} OR target_id {$ids}");
        }

        $this->addSql("DELETE FROM item_instances WHERE entity_id {$ids}");

        /* The exemplar rows last.
         *
         * Found by "installed, no instance left, standing on a road cell"
         * rather than through ROAD_EXEMPLARS, because this has to survive a
         * PARTIAL previous run: the failed attempt on experimental may have
         * deleted the instance rows before dying on the foreign key, and
         * those exemplars no longer join `items` to say they were roads. The
         * `map_routes` cell is what still identifies them. */
        $this->addSql(
            "DELETE FROM players
              WHERE player_type = 'item'
                AND slot = 'installed'
                AND id NOT IN (SELECT entity_id FROM item_instances WHERE entity_id IS NOT NULL)
                AND coords_id IN (SELECT coords_id FROM map_routes)"
        );
    }

    /**
     * The subtype goes back; the rescued roads stay where they belong. Turning
     * them back into objects would restore a state nobody wants.
     */
    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE items SET subtype = '' WHERE subtype = 'routes'");
    }
}
