<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The "Masquer la ligne de tir" (`hideLineOfFire`) option is obsolete:
 * the trace is now drawn only on explicit demand (right-click / long
 * press on a tile), so nobody sees it unless they ask for it. The
 * option no longer has any effect, so the leftover preference rows are
 * purged.
 */
final class Version20260722130000_RemoveHideLineOfFireOption extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove obsolete hideLineOfFire option rows (trace is on-demand via right-click)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DELETE FROM players_options WHERE name = 'hideLineOfFire'");
    }

    public function down(Schema $schema): void
    {
        // Deleted rows were per-player preferences; they cannot be restored.
    }
}
