<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Design feedback on the structures stack (2026-07-16):
 *
 * 1. races.kind ('character' | 'structure') — the races table is now the
 *    catalog of ENTITY base stats, and the playable flag alone can't
 *    separate PNJ races from structure types: both are non-playable. kind
 *    keeps them apart everywhere a list is built (PNJ creation, building
 *    placement, races admin).
 *
 * 2. buildings.archetype / unique_objects.archetype are DROPPED: they
 *    duplicated players.race — one concept, two names. The structure's
 *    type IS its races row (labelled « Type » in the UI).
 *
 * Idempotent: guarded by information_schema checks.
 */
final class Version20260716140000_RaceKindAndArchetypeCleanup extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Add races.kind (character|structure) and drop the redundant archetype columns";
    }

    public function up(Schema $schema): void
    {
        if (!$this->columnExists('races', 'kind')) {
            $this->addSql(
                "ALTER TABLE races ADD kind VARCHAR(20) NOT NULL DEFAULT 'character'"
            );
        }
        $this->addSql("UPDATE races SET kind = 'structure' WHERE name = 'palissade' AND playable = 0");

        if ($this->columnExists('buildings', 'archetype')) {
            $this->addSql('ALTER TABLE buildings DROP COLUMN archetype');
        }
        if ($this->columnExists('unique_objects', 'archetype')) {
            $this->addSql('ALTER TABLE unique_objects DROP COLUMN archetype');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE races DROP COLUMN kind");
        $this->addSql("ALTER TABLE buildings ADD archetype VARCHAR(64) NOT NULL DEFAULT ''");
        $this->addSql("ALTER TABLE unique_objects ADD archetype VARCHAR(64) NOT NULL DEFAULT ''");
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
