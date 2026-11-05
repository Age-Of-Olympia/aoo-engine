<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * What grows in a biome is capped by a density (plants of that type per
 * 100 cells with ground, on the population's levels) instead of a fixed
 * number. Existing rows keep their cap: density = max_count × 100 / cells
 * with ground, rounded up to the hundredth, at most 100 (one per cell).
 *
 * max_count stays, with a default, while code reading it may still run;
 * a later migration drops it.
 */
final class Version20261105130000_BiomePopulationDensity extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'biome_populations : densité pour 100 cases au lieu d\'un maximum';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE biome_populations ADD COLUMN IF NOT EXISTS density DECIMAL(5,2) NULL AFTER chance');
        $this->addSql('
            UPDATE biome_populations bp
            SET bp.density = LEAST(100, COALESCE(CEIL(bp.max_count * 10000 / NULLIF((
                SELECT COUNT(*) FROM biome_cells bc
                JOIN plans pl ON pl.id = bc.plan_id
                JOIN coords c ON c.plan = pl.slug AND c.x = bc.x AND c.y = bc.y
                WHERE bc.biome_id = bp.biome_id AND c.z BETWEEN bp.z_min AND bp.z_max
                  AND EXISTS (SELECT 1 FROM map_tiles t WHERE t.coords_id = c.id AND t.foreground = 0)
            ), 0)) / 100, bp.max_count))
            WHERE bp.density IS NULL
        ');
        $this->addSql('ALTER TABLE biome_populations MODIFY density DECIMAL(5,2) NOT NULL');
        $this->addSql('ALTER TABLE biome_populations MODIFY max_count INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE biome_populations DROP COLUMN IF EXISTS density');
    }
}
