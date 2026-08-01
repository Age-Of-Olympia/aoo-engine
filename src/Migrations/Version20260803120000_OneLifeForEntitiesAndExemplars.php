<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * An exemplar's wear stops being a number of its own and becomes its life.
 *
 * Two lives had the same shape and no shared contract: an entity's, a deficit
 * in `players_bonus` against a maximum its type gives; an exemplar's, an
 * absolute `durability` against a maximum frozen at creation. Which is why
 * repairing a dropped object healed nothing and one point of damage destroyed
 * it — the code reached for a life it did not have.
 *
 * Now every exemplar is an entity, so its wear moves where every other wound
 * already lives. `durability_max` disappears with it: the maximum comes from
 * the type, recomputed, exactly as `races.pv` does for everyone else.
 *
 * Pristine means NO ROW, as it does for an unwounded character. On this
 * database only two exemplars carry wear; every frozen maximum equalled its
 * type's, so the snapshot never diverged and nothing is lost by dropping it.
 *
 * `items.durability_max` keeps its name for now. It is the type's own life and
 * would read better as `durability`, but that rename reaches the admin screens,
 * the wiki and the JSON bundle keys — a compatibility question of its own.
 */
final class Version20260803120000_OneLifeForEntitiesAndExemplars extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'item wear becomes a players_bonus deficit; item_instances loses its durability columns';
    }

    public function up(Schema $schema): void
    {
        /* Refuse rather than lose: an exemplar with no entity has nowhere to
         * put its wear, and silently dropping the column would erase it. */
        $stranded = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM item_instances WHERE entity_id IS NULL'
        );
        if ($stranded > 0) {
            throw new \RuntimeException(
                "{$stranded} exemplaire(s) sans entité : leur usure n'a nulle part où aller."
            );
        }

        $this->addSql(
            "INSERT INTO players_bonus (player_id, name, n)
             SELECT i.entity_id, 'pv', i.durability - i.durability_max
               FROM item_instances i
              WHERE i.durability < i.durability_max
             ON DUPLICATE KEY UPDATE n = VALUES(n)"
        );

        $this->addSql('ALTER TABLE item_instances DROP COLUMN IF EXISTS durability');
        $this->addSql('ALTER TABLE item_instances DROP COLUMN IF EXISTS durability_max');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE item_instances ADD COLUMN IF NOT EXISTS durability INT(11) NOT NULL DEFAULT 100'
        );
        $this->addSql(
            'ALTER TABLE item_instances ADD COLUMN IF NOT EXISTS durability_max INT(11) NOT NULL DEFAULT 100'
        );

        $this->addSql(
            'UPDATE item_instances i
               JOIN items it ON it.id = i.item_id
               LEFT JOIN players_bonus b ON b.player_id = i.entity_id AND b.name = "pv"
                SET i.durability_max = it.durability_max,
                    i.durability = it.durability_max + COALESCE(b.n, 0)'
        );

        $this->addSql(
            'DELETE b FROM players_bonus b
               JOIN item_instances i ON i.entity_id = b.player_id
              WHERE b.name = "pv"'
        );
    }
}
