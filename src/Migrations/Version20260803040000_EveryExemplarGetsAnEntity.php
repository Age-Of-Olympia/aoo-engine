<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * An item exemplar becomes an entity — identity only, no location yet.
 *
 * Anything with an `item_instances` row has an identity to keep (wear, name,
 * maker), which is exactly what makes it an individual rather than a unit of a
 * stack. Stacks stay stacks: `players_items` is untouched.
 *
 * The entity carries the identity and NOTHING else. It is nowhere and holds
 * nothing, so no query reaches it and no write has to keep two truths in step.
 * Location still lives in `players_items_instances` / `map_items_instances`;
 * moving it is the next step, and life the one after.
 *
 * Exemplars already wrapped by a `unique_objects` row are skipped: they are
 * ALREADY an entity on the map, and giving them a second one would put the same
 * sword in two places. Reconciling those is part of the location move, which is
 * where their `players` row gets reused rather than replaced.
 */
final class Version20260803040000_EveryExemplarGetsAnEntity extends AbstractMigration
{
    private const ITEM_RANGE_START = 70000000;

    public function getDescription(): string
    {
        return 'item_instances.entity_id: every exemplar gets an entity row, with no location';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE item_instances ADD COLUMN IF NOT EXISTS entity_id INT(11) NULL DEFAULT NULL');

        $this->addSql('DROP TEMPORARY TABLE IF EXISTS tmp_exemplar_entities');
        $this->addSql(
            "CREATE TEMPORARY TABLE tmp_exemplar_entities AS
             SELECT i.id AS instance_id,
                    it.name AS type_name,
                    COALESCE(
                        NULLIF(i.custom_name, ''),
                        CONCAT(UCASE(LEFT(it.name, 1)), SUBSTRING(it.name, 2))
                    ) AS label
               FROM item_instances i
               JOIN items it ON it.id = i.item_id
              WHERE i.entity_id IS NULL
                AND i.id NOT IN (
                    SELECT item_instance_id FROM unique_objects WHERE item_instance_id IS NOT NULL
                )"
        );

        /* Ids handed out in one pass: a MAX per row would be both wrong and
         * slow. Same shape as the plants conversion. */
        $this->addSql('ALTER TABLE tmp_exemplar_entities ADD COLUMN entity_id INT NULL, ADD COLUMN display_id INT NULL');
        $this->addSql(
            'UPDATE tmp_exemplar_entities t
               JOIN (SELECT COALESCE(MAX(id), ' . (self::ITEM_RANGE_START - 1) . ') AS base FROM players
                      WHERE id BETWEEN ' . self::ITEM_RANGE_START . ' AND 79999999) m
                SET t.entity_id = m.base + (
                        SELECT COUNT(*) + 1 FROM (SELECT instance_id FROM tmp_exemplar_entities) s
                         WHERE s.instance_id < t.instance_id
                    )'
        );
        $this->addSql(
            "UPDATE tmp_exemplar_entities t
               JOIN (SELECT COALESCE(MAX(display_id), 0) AS base FROM players
                      WHERE player_type = 'item') m
                SET t.display_id = m.base + (
                        SELECT COUNT(*) + 1 FROM (SELECT instance_id FROM tmp_exemplar_entities) s
                         WHERE s.instance_id < t.instance_id
                    )"
        );

        /* No avatar: an exemplar that is nowhere is never drawn. The sprite is
         * settled when it lands on a cell, which is where it gets looked at. */
        $this->addSql(
            "INSERT INTO players
                (id, player_type, display_id, name, race, avatar, portrait,
                 coords_id, holder_id, slot, nextTurnTime, registerTime, text)
             SELECT t.entity_id, 'item', t.display_id, t.label, t.type_name, '', '',
                    NULL, NULL, '', 0, UNIX_TIMESTAMP(), ''
               FROM tmp_exemplar_entities t"
        );

        $this->addSql(
            'UPDATE item_instances i
               JOIN tmp_exemplar_entities t ON t.instance_id = i.id
                SET i.entity_id = t.entity_id'
        );

        $this->addSql('DROP TEMPORARY TABLE IF EXISTS tmp_exemplar_entities');

        $this->addSql(
            'ALTER TABLE item_instances ADD UNIQUE INDEX IF NOT EXISTS uniq_item_instances_entity (entity_id)'
        );

        /* RESTRICT: an entity whose exemplar still exists must not be deleted
         * out from under it. The exemplar goes first, then its entity. */
        $this->addConstraintIfMissing(
            'item_instances',
            'fk_item_instances_entity',
            'ALTER TABLE item_instances
             ADD CONSTRAINT fk_item_instances_entity FOREIGN KEY (entity_id)
             REFERENCES players (id) ON DELETE RESTRICT'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE item_instances DROP FOREIGN KEY IF EXISTS fk_item_instances_entity');
        $this->addSql(
            "DELETE p FROM players p
               JOIN item_instances i ON i.entity_id = p.id
              WHERE p.player_type = 'item'"
        );
        $this->addSql('ALTER TABLE item_instances DROP INDEX IF EXISTS uniq_item_instances_entity');
        $this->addSql('ALTER TABLE item_instances DROP COLUMN IF EXISTS entity_id');
    }

    /** MariaDB has no `ADD CONSTRAINT IF NOT EXISTS`: look first, add if missing. */
    private function addConstraintIfMissing(string $table, string $name, string $sql): void
    {
        $exists = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
              WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$table, $name]
        );

        if ($exists === 0) {
            $this->addSql($sql);
        }
    }
}
