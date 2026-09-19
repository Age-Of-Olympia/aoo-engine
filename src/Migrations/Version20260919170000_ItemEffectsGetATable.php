<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The effects a weapon lands on a strike leave items.add_effects (JSON,
 * hand-written, only name/duration ever read) for a table of their own,
 * with WHEN they land (hit or miss) and ON WHOM (self or target).
 * The JSON column stays untouched and is no longer read.
 */
final class Version20260919170000_ItemEffectsGetATable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'item_effects: effect, duration, outcome (hit/miss), target (self/target) per item';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "CREATE TABLE IF NOT EXISTS item_effects (
                id INT AUTO_INCREMENT NOT NULL PRIMARY KEY,
                item_id INT NOT NULL,
                effect VARCHAR(100) NOT NULL,
                duration INT NOT NULL DEFAULT 1,
                outcome ENUM('hit', 'miss') NOT NULL DEFAULT 'hit',
                target ENUM('self', 'target') NOT NULL DEFAULT 'target',
                INDEX idx_item_effects_item (item_id),
                CONSTRAINT fk_item_effects_item FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    public function postUp(Schema $schema): void
    {
        // Copy the legacy JSON rows once: an item already listed is left alone.
        $rows = $this->connection->fetchAllAssociative(
            "SELECT i.id, i.add_effects FROM items i
              WHERE i.add_effects IS NOT NULL AND i.add_effects <> '' AND i.add_effects <> '[]'
                AND NOT EXISTS (SELECT 1 FROM item_effects e WHERE e.item_id = i.id)"
        );
        foreach ($rows as $row) {
            foreach ((array) json_decode((string) $row['add_effects'], true) as $entry) {
                $effect = trim((string) ($entry['name'] ?? ''));
                if ($effect === '') {
                    continue;
                }
                // Legacy keys were hand-written: absent or unknown values take the defaults.
                $target = (string) ($entry['on'] ?? 'target');
                $this->connection->executeStatement(
                    'INSERT INTO item_effects (item_id, effect, duration, outcome, target) VALUES (?, ?, ?, ?, ?)',
                    [
                        (int) $row['id'],
                        $effect,
                        max(-1, (int) ($entry['duration'] ?? 1)),
                        ($entry['when'] ?? 'win') === 'lose' ? 'miss' : 'hit',
                        in_array($target, ['self', 'target'], true) ? $target : 'target',
                    ]
                );
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS item_effects');
    }
}
