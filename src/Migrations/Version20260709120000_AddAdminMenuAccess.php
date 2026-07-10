<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Per-menu admin access overrides. Each row overrides the registry-default
 * required level (admin / superadmin) for one admin menu; absence means the
 * menu tracks its AdminMenuAccessService default. Superadmin-only config page:
 * admin/access-control.php.
 *
 * Idempotent (CREATE TABLE IF NOT EXISTS) so it co-exists with the service's
 * lazy ensureTable() fallback.
 */
final class Version20260709120000_AddAdminMenuAccess extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add admin_menu_access (per-menu required level overrides)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS admin_menu_access (
                page VARCHAR(64) NOT NULL PRIMARY KEY,
                required_level VARCHAR(16) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS admin_menu_access');
    }
}
