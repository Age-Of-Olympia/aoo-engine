<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * What the biome brush paints with: a weight per terrain of the type's
 * palette ("60 herbe, 25 mousse, 15 terre"), the size of the patches on the
 * type, and a seed on each biome (another seed, another draw). Existing
 * biomes take their id as seed, so each one already draws its own pattern.
 */
final class Version20261107110000_BiomeBrushSettings extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'pinceau de biome : poids des sols, taille des plaques, graine des biomes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE biome_type_grounds ADD COLUMN IF NOT EXISTS weight INT NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE biome_types ADD COLUMN IF NOT EXISTS patch_scale INT NOT NULL DEFAULT 6');
        $this->addSql('ALTER TABLE biomes ADD COLUMN IF NOT EXISTS seed INT NOT NULL DEFAULT 0');
        $this->addSql('UPDATE biomes SET seed = id WHERE seed = 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE biomes DROP COLUMN IF EXISTS seed');
        $this->addSql('ALTER TABLE biome_types DROP COLUMN IF EXISTS patch_scale');
        $this->addSql('ALTER TABLE biome_type_grounds DROP COLUMN IF EXISTS weight');
    }
}
