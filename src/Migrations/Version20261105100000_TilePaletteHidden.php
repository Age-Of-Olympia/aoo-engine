<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Images kept in the tile inventory but left out of the editor palettes
 * (tiled.php and the Tiled extension): sky, underground, loot… One row per
 * hidden (layer, name); an image without a row is offered as before.
 */
final class Version20261105100000_TilePaletteHidden extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'tile_palette_hidden : images masquées des palettes d\'éditeur';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE IF NOT EXISTS tile_palette_hidden (
                layer VARCHAR(32) NOT NULL,
                name VARCHAR(191) NOT NULL,
                PRIMARY KEY (layer, name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS tile_palette_hidden');
    }
}
