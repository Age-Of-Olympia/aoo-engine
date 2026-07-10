<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Generic key/value store for admin-configurable settings (AdminSettingsService).
 * First user: the PNJ retirement plan (pnj_retire_plan).
 *
 * Idempotent (CREATE TABLE IF NOT EXISTS) so it co-exists with the service's
 * lazy ensureTable() fallback.
 */
final class Version20260709130000_AddAdminSettings extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add admin_settings (key/value admin settings store)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS admin_settings (
                name VARCHAR(64) NOT NULL PRIMARY KEY,
                value VARCHAR(255) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // Seed the PNJ retirement plan with its default so the row is visible in
        // the table (self-documenting). INSERT IGNORE never overwrites a value an
        // admin has already set.
        $this->addSql(
            "INSERT IGNORE INTO admin_settings (name, value) VALUES ('pnj_retire_plan', 'pnjs')"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS admin_settings');
    }
}
