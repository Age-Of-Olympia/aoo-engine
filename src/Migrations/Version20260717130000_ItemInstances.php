<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Items Phase 1a (docs/design-items-instances.md §3.2 / §5c): the
 * instance tables — SCHEMA ONLY, no data conversion, no behavior
 * change. The equipped-rows conversion is a LATER migration, after the
 * read and write paths understand instances (strangler order).
 *
 * - item_instances: one row per individualized object. Durability
 *   thresholds carry the state story (0 = brisé, < 0 = détruit);
 *   destroyed is a soft-delete so creator/date history survives.
 *   wear_pending implements « le tour est l'unité d'usure » : events
 *   ARM it during the turn, new-turn processing applies the decrement
 *   and clears it.
 * - players_items_instances: ownership/equipment link — the stack PK
 *   (player_id, item_id) cannot hold several individuals, hence a
 *   dedicated table. An instance has at most ONE location (unique on
 *   instance_id); map/bank locations come with later phases.
 */
final class Version20260717130000_ItemInstances extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create item_instances and players_items_instances (schema only, no conversion)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "CREATE TABLE IF NOT EXISTS item_instances (
                id INT AUTO_INCREMENT NOT NULL PRIMARY KEY,
                item_id INT NOT NULL,
                durability INT NOT NULL DEFAULT 100,
                durability_max INT NOT NULL DEFAULT 100,
                quality INT NOT NULL DEFAULT 0,
                custom_name VARCHAR(255) NOT NULL DEFAULT '',
                params LONGTEXT DEFAULT NULL,
                creator_id INT DEFAULT NULL,
                created_at INT NOT NULL DEFAULT 0,
                wear_pending TINYINT(1) NOT NULL DEFAULT 0,
                destroyed TINYINT(1) NOT NULL DEFAULT 0,
                INDEX idx_item_instances_item (item_id),
                CONSTRAINT fk_item_instances_item FOREIGN KEY (item_id) REFERENCES items (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );

        $this->addSql(
            "CREATE TABLE IF NOT EXISTS players_items_instances (
                player_id INT NOT NULL,
                instance_id INT NOT NULL PRIMARY KEY,
                equiped VARCHAR(255) NOT NULL DEFAULT '',
                INDEX idx_pii_player (player_id),
                CONSTRAINT fk_pii_player FOREIGN KEY (player_id) REFERENCES players (id),
                CONSTRAINT fk_pii_instance FOREIGN KEY (instance_id) REFERENCES item_instances (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS players_items_instances');
        $this->addSql('DROP TABLE IF EXISTS item_instances');
    }
}
