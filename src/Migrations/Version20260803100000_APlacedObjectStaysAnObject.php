<?php

declare(strict_types=1);

namespace App\Migrations;

use App\Service\ItemInstanceService;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A placed object stops wrapping an exemplar and starts being one.
 *
 * `unique_objects` bridged a map entity to an exemplar, which meant a placed
 * sword existed twice: a shell standing on the tile, and the exemplar it
 * pointed at. Picking it up deleted the shell — the reason the shell could
 * never carry anything, life included.
 *
 * The shell is now the exemplar itself. Its `players` row is REUSED, id and
 * all: keeping it alive is what lets the events naming it stay true, and
 * minting a new one would throw that away for nothing.
 *
 * Its type stops being the phantom race `objet`, which no `races` row ever
 * defined, and becomes the item's own catalogue name — so it finally answers
 * for its own life through {@see \App\Entity\OwnsCaracsInterface} instead of falling
 * into the "race not found" branch with a stat block of zeros.
 *
 * `unique_objects` SURVIVES for what it also is: the satellite of animator
 * objects — crystals, gates, artifacts — whose `item_instance_id` is null and
 * which wrap nothing. Only the bridging rows go.
 */
final class Version20260803100000_APlacedObjectStaysAnObject extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'placed exemplars reclaim their players row: player_type item, real type, no bridge';
    }

    public function up(Schema $schema): void
    {
        /* The row is reused as-is: cells, coordinates, sprite and name all stay
         * where they are. Only what it IS changes. */
        $this->addSql(
            'UPDATE players p
               JOIN unique_objects u ON u.player_id = p.id
               JOIN item_instances i ON i.id = u.item_instance_id
               JOIN items it ON it.id = i.item_id
                SET p.player_type = ?, p.race = it.name
              WHERE u.item_instance_id IS NOT NULL',
            [ItemInstanceService::ENTITY_TYPE]
        );

        $this->addSql(
            'UPDATE item_instances i
               JOIN unique_objects u ON u.item_instance_id = i.id
                SET i.entity_id = u.player_id
              WHERE u.item_instance_id IS NOT NULL'
        );

        /* An exemplar that already had an entity of its own AND a wrapper would
         * now have two rows claiming to be it. The backfill skipped wrapped
         * exemplars precisely so this cannot happen; refuse rather than pick. */
        $doubled = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM players p
              WHERE p.player_type = ?
                AND NOT EXISTS (SELECT 1 FROM item_instances i WHERE i.entity_id = p.id)',
            [ItemInstanceService::ENTITY_TYPE]
        );
        if ($doubled > 0) {
            throw new \RuntimeException(
                "{$doubled} entité(s) « item » sans exemplaire : réconcilier avant de retirer le pont."
            );
        }

        $this->addSql('DELETE FROM unique_objects WHERE item_instance_id IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "INSERT INTO unique_objects (player_id, item_instance_id)
             SELECT i.entity_id, i.id
               FROM item_instances i
               JOIN players p ON p.id = i.entity_id
              WHERE p.player_type = ? AND p.slot = 'installed'",
            [ItemInstanceService::ENTITY_TYPE]
        );
        $this->addSql(
            "UPDATE players p
               JOIN unique_objects u ON u.player_id = p.id
                SET p.player_type = 'unique', p.race = 'objet'
              WHERE u.item_instance_id IS NOT NULL"
        );
    }
}
