<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A faction respawns at chosen coordinates on its respawn plan, not only
 * around 0,0,0. The default keeps the old landing spot.
 */
final class Version20261016120000_FactionRespawnCoords extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Coordonnées de réapparition des factions (respawnX/Y/Z, 0 par défaut)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE factions ADD COLUMN IF NOT EXISTS respawnX INT NOT NULL DEFAULT 0 AFTER respawnPlan');
        $this->addSql('ALTER TABLE factions ADD COLUMN IF NOT EXISTS respawnY INT NOT NULL DEFAULT 0 AFTER respawnX');
        $this->addSql('ALTER TABLE factions ADD COLUMN IF NOT EXISTS respawnZ INT NOT NULL DEFAULT 0 AFTER respawnY');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE factions DROP COLUMN IF EXISTS respawnX');
        $this->addSql('ALTER TABLE factions DROP COLUMN IF EXISTS respawnY');
        $this->addSql('ALTER TABLE factions DROP COLUMN IF EXISTS respawnZ');
    }
}
