<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Roads that Version20260831140000_RoadsBecomeEntities skipped become entities.
 *
 * That conversion joined `map_routes` on `races`, where only `route` existed:
 * every `carreaux` and `sentier` line stayed behind, and nothing reads
 * `map_routes` any more — those roads are neither drawn nor walked. Only the
 * plans played this season are recovered (season NULL = every season, or the
 * current one); the lines on past seasons' plans are left for the table drop.
 * A missing road type is created with the row RouteTypeService::ensure() writes.
 */
final class Version20261018140000_UntypedRoadsBecomeEntities extends AbstractMigration
{
    private const SEASON = 3;

    public function getDescription(): string
    {
        return 'routes de map_routes oubliées par la conversion (carreaux, sentier) : deviennent des entités sur les plans de la saison';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TEMPORARY TABLE IF EXISTS tmp_roads');
        // One road per cell; a cell that already holds one is skipped
        $this->addSql(
            "CREATE TEMPORARY TABLE tmp_roads AS
             SELECT MIN(mr.id) AS road_id, MIN(mr.name) AS name, mr.coords_id,
                    MIN(mr.player_id) AS owner_id, c.plan, c.z, c.x, c.y
               FROM map_routes mr
               JOIN coords c ON c.id = mr.coords_id
               JOIN plans pl ON CONVERT(pl.slug USING utf8mb4) = CONVERT(c.plan USING utf8mb4)
                            AND (pl.season IS NULL OR pl.season = " . self::SEASON . ")
              WHERE NOT EXISTS (SELECT 1 FROM players p
                                 WHERE p.player_type = 'route' AND p.coords_id = mr.coords_id)
              GROUP BY mr.coords_id, c.plan, c.z, c.x, c.y"
        );

        $this->addSql(
            "INSERT INTO races (code, name, label, description, playable, hidden, kind, type_kind,
                                structure_nature, bleeds, wound_color, blocks_passage, blocks_projectiles,
                                pv, spd, bgColor, color, faction, plan)
             SELECT UPPER(t.name), t.name, CONCAT(UPPER(LEFT(t.name, 1)), SUBSTRING(REPLACE(t.name, '_', ' '), 2)),
                    '', 0, 1, 'structure', 'route', 'route', '', '#8b4513', 0, 0,
                    60, 16, '#8b4513', 'black', '', ''
               FROM (SELECT DISTINCT name FROM tmp_roads) t
              WHERE NOT EXISTS (
                    SELECT 1 FROM races r WHERE CONVERT(r.name USING utf8mb4) = CONVERT(t.name USING utf8mb4)
                )"
        );

        $this->addSql(
            'ALTER TABLE tmp_roads ADD COLUMN entity_id INT NULL, ADD COLUMN display_id INT NULL,
                                   ADD COLUMN label VARCHAR(255) NULL'
        );
        $this->addSql(
            "UPDATE tmp_roads t
               JOIN races r ON CONVERT(r.name USING utf8mb4) = CONVERT(t.name USING utf8mb4)
                SET t.label = COALESCE(NULLIF(r.label, ''), t.name)"
        );
        // Ids in the roads range, allocated in one pass
        $this->addSql(
            "UPDATE tmp_roads t
               JOIN (SELECT COALESCE(MAX(id), 79999999) AS base FROM players
                      WHERE id BETWEEN 80000000 AND 89999999) m
                SET t.entity_id = m.base + (
                        SELECT COUNT(*) + 1 FROM (SELECT road_id FROM tmp_roads) s
                         WHERE s.road_id < t.road_id
                    )"
        );
        $this->addSql(
            "UPDATE tmp_roads t
               JOIN (SELECT COALESCE(MAX(display_id), 0) AS base FROM players
                      WHERE player_type = 'route') m
                SET t.display_id = m.base + (
                        SELECT COUNT(*) + 1 FROM (SELECT road_id FROM tmp_roads) s
                         WHERE s.road_id < t.road_id
                    )"
        );

        $this->addSql(
            "INSERT INTO players
                (id, player_type, display_id, name, race, avatar, portrait,
                 coords_id, slot, nextTurnTime, registerTime, text, owner_id)
             SELECT t.entity_id, 'route', t.display_id, t.label, t.name,
                    CONCAT('img/routes/', t.name, '.png'),
                    CONCAT('img/routes/', t.name, '.png'),
                    t.coords_id, 'installed', 0, UNIX_TIMESTAMP(), '', t.owner_id
               FROM tmp_roads t"
        );
        $this->addSql(
            "INSERT INTO entity_cells (player_id, coords_id, plan, z, x, y, piece, role)
             SELECT t.entity_id, t.coords_id, t.plan, t.z, t.x, t.y, 0, 'part'
               FROM tmp_roads t"
        );
        // Every line of a recovered cell goes, duplicates included
        $this->addSql('DELETE mr FROM map_routes mr JOIN tmp_roads t ON t.coords_id = mr.coords_id');
        $this->addSql('DROP TEMPORARY TABLE IF EXISTS tmp_roads');
    }

    public function down(Schema $schema): void
    {
        // The roads are now ordinary entities, indistinguishable from the others
    }
}
