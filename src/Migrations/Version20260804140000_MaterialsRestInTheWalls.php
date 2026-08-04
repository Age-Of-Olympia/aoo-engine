<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The materials rest in the walls — as a SLOT of the one inventory.
 *
 * `players_items` gains the dimension the entity relation already had:
 * `slot`, '' being the bag every legacy reader means. 'fabric' holds
 * what construire poured into the walls (and what the admin hides
 * there); future modes have their column ready. Bag-meaning readers
 * were scoped to slot = '' in the same change.
 *
 * LootSpillService rolls the fabric with the same per-unit loot rules
 * when the walls fall.
 */
final class Version20260804140000_MaterialsRestInTheWalls extends AbstractMigration
{
    public function getDescription(): string
    {
        return "players_items.slot : les matériaux dans les murs ('fabric'), répandus à la chute";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE players_items ADD COLUMN IF NOT EXISTS slot VARCHAR(32) NOT NULL DEFAULT ''"
        );

        // The key learns the dimension once: (player_id, item_id) → + slot.
        $pkColumns = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
              WHERE table_schema = DATABASE() AND table_name = 'players_items' AND index_name = 'PRIMARY'"
        );
        if ($pkColumns === 2) {
            $this->addSql(
                'ALTER TABLE players_items DROP PRIMARY KEY, ADD PRIMARY KEY (player_id, item_id, slot)'
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM players_items WHERE slot <> ''");
        $this->addSql('ALTER TABLE players_items DROP PRIMARY KEY, ADD PRIMARY KEY (player_id, item_id)');
        $this->addSql('ALTER TABLE players_items DROP COLUMN IF EXISTS slot');
    }
}
