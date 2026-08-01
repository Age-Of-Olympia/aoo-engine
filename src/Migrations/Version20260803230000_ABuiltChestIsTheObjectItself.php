<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Turn standing chest buildings into installed exemplars.
 *
 * The entity keeps its id: ENTITY_ID_RANGES allocates, it does not classify,
 * and events already name these ids. `display_id` is a per-type counter and is
 * re-issued in the exemplar sequence.
 */
final class Version20260803230000_ABuiltChestIsTheObjectItself extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'chests: standing buildings become installed exemplars, keeping their id';
    }

    public function up(Schema $schema): void
    {
        /* The instance first: an exemplar without one is an entity claiming to
         * be an object nobody catalogued. */
        $this->addSql(
            "INSERT INTO item_instances
                    (item_id, quality, custom_name, params, creator_id, created_at,
                     wear_pending, destroyed, entity_id)
             SELECT it.id, 0, '', NULL, NULL, UNIX_TIMESTAMP(), 0, 0, p.id
               FROM players p
               JOIN items it ON CONVERT(it.name USING utf8mb4) = CONVERT(p.race USING utf8mb4)
              WHERE p.player_type = 'building'
                AND p.race LIKE 'coffre%'
                AND NOT EXISTS (SELECT 1 FROM item_instances ii WHERE ii.entity_id = p.id)"
        );

        /* Re-issued before the flip, while the rows are still findable as
         * buildings, and counted from the highest exemplar already out there. */
        $this->addSql(
            "UPDATE players p
               JOIN (
                     SELECT id, ROW_NUMBER() OVER (ORDER BY id) AS rk
                       FROM players
                      WHERE player_type = 'building' AND race LIKE 'coffre%'
                    ) ranked ON ranked.id = p.id
                SET p.display_id = ranked.rk + (
                        SELECT COALESCE(MAX(display_id), 0)
                          FROM (SELECT display_id FROM players WHERE player_type = 'item') seen
                    )"
        );

        $this->addSql(
            "UPDATE players SET player_type = 'item'
              WHERE player_type = 'building' AND race LIKE 'coffre%'"
        );

        /* The building satellite carried build_state and a dialog: a chest has
         * neither, and BuildingService must stop finding one. */
        $this->addSql(
            "DELETE b FROM buildings b
               JOIN players p ON p.id = b.player_id
              WHERE p.player_type = 'item' AND p.race LIKE 'coffre%'"
        );
    }

    public function down(Schema $schema): void
    {
        // Only what came from a building goes back to being one; the range
        // says where a row was born.
        $born = 'p.id BETWEEN 20000000 AND 29999999';

        $this->addSql(
            "INSERT INTO buildings (player_id, build_state, dialog, readable_from_afar)
             SELECT p.id, 'built', '', NULL
               FROM players p
              WHERE p.player_type = 'item' AND p.race LIKE 'coffre%' AND {$born}
                AND NOT EXISTS (SELECT 1 FROM buildings b WHERE b.player_id = p.id)"
        );
        $this->addSql(
            "DELETE ii FROM item_instances ii
               JOIN players p ON p.id = ii.entity_id
              WHERE p.player_type = 'item' AND p.race LIKE 'coffre%' AND {$born}"
        );
        $this->addSql(
            "UPDATE players p SET p.player_type = 'building'
              WHERE p.player_type = 'item' AND p.race LIKE 'coffre%' AND {$born}"
        );
        /* display_id is not restored: the old numbers were re-issued to nobody
         * and the counter is per type, so a fresh one is as true as the first. */
    }
}
