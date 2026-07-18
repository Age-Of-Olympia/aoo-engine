<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Items Phase 2 — wear configuration ON THE CATALOG
 * (docs/design-items-instances.md §3.4):
 *
 * - wear_triggers: CSV subset of attack,defense,move,usage — the events
 *   that ARM this item's wear during a turn;
 * - wear_rate: durability points lost per turn in which at least one
 *   armed trigger fired.
 *
 * Ships INERT (rate 0, no triggers, décision 2026-07-17): which items
 * wear and how fast is admin/balance content, tuned later in the items
 * admin. Idempotent via information_schema guard.
 */
final class Version20260717150000_WearConfig extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add items.wear_triggers / items.wear_rate (inert defaults)';
    }

    public function up(Schema $schema): void
    {
        if (!$this->columnExists('items', 'wear_triggers')) {
            $this->addSql("ALTER TABLE items ADD wear_triggers VARCHAR(64) NOT NULL DEFAULT ''");
        }
        if (!$this->columnExists('items', 'wear_rate')) {
            $this->addSql('ALTER TABLE items ADD wear_rate INT NOT NULL DEFAULT 0');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE items DROP COLUMN wear_triggers');
        $this->addSql('ALTER TABLE items DROP COLUMN wear_rate');
    }

    private function columnExists(string $table, string $column): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
    }
}
