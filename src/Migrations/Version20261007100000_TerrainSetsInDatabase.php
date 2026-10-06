<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The Tiled terrain sets move from tools/tiled/terrains.json into the
 * database.
 *
 * terrain_colors keeps each colour at its 1-based position: wangIds point
 * to colours by index, so a position never moves. terrain_tiles maps a full
 * tile to its colour, or a transition to its wangId. The file is imported
 * from the admin page (Transitions de terrain), never here: migrations run
 * from the git checkout, not from the docroot where each server keeps its
 * own file.
 */
final class Version20261007100000_TerrainSetsInDatabase extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'terrains: terrain_colors + terrain_tiles (ex-terrains.json)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE IF NOT EXISTS terrain_colors (
                layer VARCHAR(32) NOT NULL,
                position SMALLINT UNSIGNED NOT NULL,
                name VARCHAR(191) NOT NULL,
                PRIMARY KEY (layer, position)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addSql("
            CREATE TABLE IF NOT EXISTS terrain_tiles (
                layer VARCHAR(32) NOT NULL,
                name VARCHAR(191) NOT NULL,
                color VARCHAR(191) DEFAULT NULL,
                wang_id VARCHAR(64) DEFAULT NULL,
                PRIMARY KEY (layer, name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS terrain_tiles');
        $this->addSql('DROP TABLE IF EXISTS terrain_colors');
    }
}
