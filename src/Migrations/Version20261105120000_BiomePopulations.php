<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * What grows in a biome: per biome and per type (a `races` row, plant or
 * resource), a chance of 1 in `chance` per free cell and per night, up to
 * `max_count` of that type in the biome, on the levels z_min..z_max.
 */
final class Version20261105120000_BiomePopulations extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'table biome_populations : ce qui pousse dans chaque biome';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE IF NOT EXISTS biome_populations (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                biome_id INT NOT NULL,
                race_id INT NOT NULL,
                chance INT NOT NULL,
                max_count INT NOT NULL,
                z_min INT NOT NULL DEFAULT 0,
                z_max INT NOT NULL DEFAULT 0,
                UNIQUE KEY uniq_biome_populations (biome_id, race_id),
                KEY idx_biome_populations_race (race_id),
                CONSTRAINT fk_biome_populations_biome FOREIGN KEY (biome_id) REFERENCES biomes (id) ON DELETE CASCADE,
                CONSTRAINT fk_biome_populations_race FOREIGN KEY (race_id) REFERENCES races (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS biome_populations');
    }
}
