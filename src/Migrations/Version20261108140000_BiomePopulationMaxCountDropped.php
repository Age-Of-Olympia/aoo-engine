<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Density replaced max_count in Version20261105130000; no code reads the
 * column since that release.
 */
final class Version20261108140000_BiomePopulationMaxCountDropped extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'biome_populations : suppression de max_count';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE biome_populations DROP COLUMN IF EXISTS max_count');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE biome_populations ADD COLUMN IF NOT EXISTS max_count INT NOT NULL DEFAULT 0 AFTER chance');
    }
}
