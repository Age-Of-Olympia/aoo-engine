<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The ground palette of a biome type: the terrains (terrain_colors names
 * of the ground layer) its plants grow on. No row: any ground, as before.
 * The future biome brush paints with the same palette.
 */
final class Version20261107100000_BiomeTypeGrounds extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'table biome_type_grounds : sols acceptés par chaque type de biome';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE IF NOT EXISTS biome_type_grounds (
                biome_type_id INT NOT NULL,
                terrain VARCHAR(191) NOT NULL,
                PRIMARY KEY (biome_type_id, terrain),
                CONSTRAINT fk_biome_type_grounds_type FOREIGN KEY (biome_type_id) REFERENCES biome_types (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS biome_type_grounds');
    }
}
