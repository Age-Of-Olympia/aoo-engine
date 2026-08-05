<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260806090000_MapItemsUniqueStack extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'une pile par (case, objet) : doublons map_items fusionnés puis clé unique';
    }

    public function up(Schema $schema): void
    {
        /* Duplicates are the table's normal state (every drop appended a
         * row), so merge them into the oldest row instead of aborting like
         * coords did. Both statements share the migration transaction: a
         * failure between them rolls the merge back whole. Rerun after
         * success is a no-op — HAVING COUNT(*) > 1 no longer matches. */
        $this->addSql(
            'UPDATE map_items mi
               JOIN (SELECT MIN(id) AS keep_id, coords_id, item_id, SUM(n) AS total
                       FROM map_items
                      GROUP BY coords_id, item_id
                     HAVING COUNT(*) > 1) d ON mi.id = d.keep_id
                SET mi.n = d.total'
        );
        $this->addSql(
            'DELETE mi FROM map_items mi
               JOIN (SELECT MIN(id) AS keep_id, coords_id, item_id
                       FROM map_items
                      GROUP BY coords_id, item_id
                     HAVING COUNT(*) > 1) d
                 ON mi.coords_id = d.coords_id
                AND mi.item_id = d.item_id
                AND mi.id <> d.keep_id'
        );
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uk_coords_item ON map_items (coords_id, item_id)');
    }

    public function down(Schema $schema): void
    {
        /* The merge is not undone: summed quantities are the truth. */
        $this->addSql('DROP INDEX IF EXISTS uk_coords_item ON map_items');
    }
}
