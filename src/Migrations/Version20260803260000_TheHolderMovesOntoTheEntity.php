<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Backfill `holder_id` + `slot` from the ownership link.
 *
 * Same mapping as ItemInstanceService::syncHolder(). Both halves are written
 * from now on; the readers move next, and the link table goes last.
 */
final class Version20260803260000_TheHolderMovesOntoTheEntity extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'players.holder_id/slot: backfilled from players_items_instances';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE players p
               JOIN item_instances ii ON ii.entity_id = p.id
               JOIN players_items_instances l ON l.instance_id = ii.id
                SET p.coords_id = NULL,
                    p.holder_id = l.player_id,
                    p.slot = CASE
                                 WHEN l.equiped <> '' THEN l.equiped
                                 WHEN l.location <> 'inventory' THEN l.location
                                 ELSE ''
                             END
              WHERE p.holder_id IS NULL"
        );
    }

    public function down(Schema $schema): void
    {
        /* Only what the link still explains goes back: an exemplar held by an
         * entity with no link row was never derived from one. */
        $this->addSql(
            "UPDATE players p
               JOIN item_instances ii ON ii.entity_id = p.id
               JOIN players_items_instances l ON l.instance_id = ii.id
                SET p.holder_id = NULL, p.slot = ''
              WHERE p.holder_id = l.player_id"
        );
    }
}
