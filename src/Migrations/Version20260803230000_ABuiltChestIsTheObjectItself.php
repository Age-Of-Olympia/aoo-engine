<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A built chest stops being a building and becomes the object it is.
 *
 * Standing chests were `building` entities typed by a `races` row, with no
 * exemplar behind them. Nothing recorded which object they were, so wear, a
 * custom name and — the day containment lands — their contents had nowhere to
 * live. They become installed exemplars: an `item_instances` row bound to the
 * entity that is already there, and the discriminator flipped.
 *
 * THE ENTITY KEEPS ITS ID. The ranges in ENTITY_ID_RANGES allocate, they do not
 * classify: both readers ask for the next free id in a range, none derives a
 * type from one. Re-numbering would buy tidiness and cost the truth of every
 * event that already names #20000217, so the discriminator carries the type and
 * the id stays what it was.
 *
 * `display_id` is not an identity but a per-type counter, so it is re-issued
 * in the exemplar sequence — leaving it would collide with the buildings that
 * kept theirs.
 *
 * Location, open state, owner and name are already on the entity and are not
 * touched: that is the point of the previous phases. No chest carries a life
 * deficit today (`players_bonus` has no `pv` row for any of them), so raising
 * the max from `races.pv` to `items.durability_max` cannot resurrect or wound
 * anything — a deficit would have kept its meaning anyway, being a deficit.
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
        /* Only what CAME from a building goes back to being one. A chest
         * created as an exemplar lives in the item range and never had a
         * satellite; reverting it would invent a building that never was.
         * The range, useless for classifying, is exactly right for saying
         * where a row was born. */
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
