<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The width of a biome type's ragged edge, in cells: near the edge, the
 * brush lets the ground across it reach into the biome. 0 keeps the
 * straight edge of the zone.
 */
final class Version20261108160000_BiomeTypeEdgeWidth extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'pinceau de biome : largeur de la lisière des types';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE biome_types ADD COLUMN IF NOT EXISTS edge_width INT NOT NULL DEFAULT 2 AFTER patch_scale');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE biome_types DROP COLUMN IF EXISTS edge_width');
    }
}
