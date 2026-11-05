<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Biomes: zones of a plan with their own rules (App\Module\Module::Biomes).
 *
 * - biome_types: what a biome is ("forêt"), its colour in the editor.
 * - biomes: one zone of a plan ("Forêt du Nord"), outline as JSON vertices
 *   on cell corners; where zones overlap, the highest stack_order wins.
 * - biome_cells: the cells of each biome, rebuilt from the outlines on
 *   every save; a transition cell has one row per biome covering it.
 *
 * No FK to coords: coords only holds occupied cells, a forest is mostly
 * empty ones. Deleting a plan deletes its biomes; a type still used by a
 * biome cannot be deleted.
 */
final class Version20261105110000_BiomeTables extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'tables biome_types, biomes et biome_cells';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE IF NOT EXISTS biome_types (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(64) NOT NULL,
                label VARCHAR(100) NOT NULL,
                color CHAR(7) NOT NULL DEFAULT '#3f6b4f',
                UNIQUE KEY uniq_biome_types_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addSql("
            CREATE TABLE IF NOT EXISTS biomes (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                plan_id INT NOT NULL,
                biome_type_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                zone JSON NOT NULL,
                stack_order INT NOT NULL DEFAULT 0,
                KEY idx_biomes_plan (plan_id),
                CONSTRAINT fk_biomes_plan FOREIGN KEY (plan_id) REFERENCES plans (id) ON DELETE CASCADE,
                CONSTRAINT fk_biomes_type FOREIGN KEY (biome_type_id) REFERENCES biome_types (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addSql("
            CREATE TABLE IF NOT EXISTS biome_cells (
                plan_id INT NOT NULL,
                x INT NOT NULL,
                y INT NOT NULL,
                biome_id INT NOT NULL,
                PRIMARY KEY (plan_id, x, y, biome_id),
                KEY idx_biome_cells_biome (biome_id),
                CONSTRAINT fk_biome_cells_biome FOREIGN KEY (biome_id) REFERENCES biomes (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS biome_cells');
        $this->addSql('DROP TABLE IF EXISTS biomes');
        $this->addSql('DROP TABLE IF EXISTS biome_types');
    }
}
