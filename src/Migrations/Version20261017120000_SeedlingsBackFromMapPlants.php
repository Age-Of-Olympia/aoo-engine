<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The daily seed cron kept writing seedlings into map_plants after plants became
 * entities. Rows carry no date, so only season 3 plans are recovered: those rows
 * can only come from the cron since the season opened. Same SQL as
 * Version20260802000000_PlantsBecomeEntities; one plant per cell, a cell that
 * already holds one is skipped.
 */
final class Version20261017120000_SeedlingsBackFromMapPlants extends AbstractMigration
{
    private const SEASON = 3;

    public function getDescription(): string
    {
        return 'map_plants rows written by the seed cron on season 3 plans become plant entities';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TEMPORARY TABLE IF EXISTS tmp_seedlings');
        $this->addSql(
            "CREATE TEMPORARY TABLE tmp_seedlings AS
             SELECT MIN(p.id) AS plant_id, MIN(p.name) AS name, p.coords_id,
                    c.plan, c.z, c.x, c.y
               FROM map_plants p
               JOIN coords c ON c.id = p.coords_id
               JOIN plans pl ON CONVERT(pl.slug USING utf8mb4) = CONVERT(c.plan USING utf8mb4)
                            AND pl.season = " . self::SEASON . "
               LEFT JOIN players e ON e.coords_id = p.coords_id AND e.player_type = 'plant'
              WHERE e.id IS NULL
              GROUP BY p.coords_id, c.plan, c.z, c.x, c.y"
        );
        // Unknown plant types stay out: no guessing a family
        $this->addSql(
            "DELETE t FROM tmp_seedlings t
               LEFT JOIN races r ON CONVERT(r.name USING utf8mb4) = CONVERT(t.name USING utf8mb4)
                                AND r.type_kind = 'plant'
              WHERE r.name IS NULL"
        );
        $this->addSql(
            'ALTER TABLE tmp_seedlings ADD COLUMN entity_id INT NULL, ADD COLUMN display_id INT NULL,
                                       ADD COLUMN label VARCHAR(255) NULL'
        );
        $this->addSql(
            "UPDATE tmp_seedlings t
               JOIN races r ON CONVERT(r.name USING utf8mb4) = CONVERT(t.name USING utf8mb4)
                SET t.label = COALESCE(NULLIF(r.label, ''), t.name)"
        );
        // Ids in the plants range, allocated in one pass
        $this->addSql(
            "UPDATE tmp_seedlings t
               JOIN (SELECT COALESCE(MAX(id), 59999999) AS base FROM players
                      WHERE id BETWEEN 60000000 AND 69999999) m
                SET t.entity_id = m.base + (
                        SELECT COUNT(*) + 1 FROM (SELECT plant_id FROM tmp_seedlings) s
                         WHERE s.plant_id < t.plant_id
                    )"
        );
        $this->addSql(
            "UPDATE tmp_seedlings t
               JOIN (SELECT COALESCE(MAX(display_id), 0) AS base FROM players
                      WHERE player_type = 'plant') m
                SET t.display_id = m.base + (
                        SELECT COUNT(*) + 1 FROM (SELECT plant_id FROM tmp_seedlings) s
                         WHERE s.plant_id < t.plant_id
                    )"
        );
        $this->addSql(
            "INSERT INTO players
                (id, player_type, display_id, name, race, avatar, portrait,
                 coords_id, slot, nextTurnTime, registerTime, text)
             SELECT t.entity_id, 'plant', t.display_id, t.label, t.name,
                    CONCAT('img/plants/', t.name, '.png'),
                    CONCAT('img/plants/', t.name, '.png'),
                    t.coords_id, 'installed', 0, UNIX_TIMESTAMP(), ''
               FROM tmp_seedlings t"
        );
        $this->addSql(
            "INSERT INTO entity_cells (player_id, coords_id, plan, z, x, y, piece, role)
             SELECT t.entity_id, t.coords_id, t.plan, t.z, t.x, t.y, 0, 'part'
               FROM tmp_seedlings t"
        );
        // Every row of a recovered cell goes, duplicates included
        $this->addSql(
            'DELETE p FROM map_plants p JOIN tmp_seedlings t ON t.coords_id = p.coords_id'
        );
        $this->addSql('DROP TEMPORARY TABLE IF EXISTS tmp_seedlings');
    }

    public function down(Schema $schema): void
    {
        // The plants are now ordinary entities, indistinguishable from the others
    }
}
