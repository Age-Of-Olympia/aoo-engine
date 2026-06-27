<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add the per-action icon colour token (nullable; null = default colour). The
 * value is a token from ActionIconPalette, resolved to a hex at render time.
 *
 * Idempotent: MariaDB 10.2+ supports ADD/DROP COLUMN IF [NOT] EXISTS, so a
 * partial-failure re-run is a no-op.
 */
final class Version20260628130000_AddActionIconColor extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add actions.icon_color (nullable colour token)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE actions ADD COLUMN IF NOT EXISTS icon_color VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE actions DROP COLUMN IF EXISTS icon_color');
    }
}
