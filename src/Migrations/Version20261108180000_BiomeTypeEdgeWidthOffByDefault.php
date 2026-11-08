<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The ragged edge is off by default: a hand-drawn zone keeps its straight
 * edge, the lisière is for generated maps. Types still on the former
 * default (2) go back to 0; a width set by hand stays.
 */
final class Version20261108180000_BiomeTypeEdgeWidthOffByDefault extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'pinceau de biome : lisière désactivée par défaut';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE biome_types ALTER COLUMN edge_width SET DEFAULT 0');
        $this->addSql('UPDATE biome_types SET edge_width = 0 WHERE edge_width = 2');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE biome_types ALTER COLUMN edge_width SET DEFAULT 2');
    }
}
