<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Structure types (buildings, scenery) kept out of the tiled / Tiled
 * palettes: the flag lives on the type, so it follows a rename or a delete.
 */
final class Version20261110100000_RaceTiledHidden extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'races.tiled_hidden : types de bâtiment et de décor masqués des palettes d\'éditeur';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE races ADD COLUMN IF NOT EXISTS tiled_hidden TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE races DROP COLUMN IF EXISTS tiled_hidden');
    }
}
