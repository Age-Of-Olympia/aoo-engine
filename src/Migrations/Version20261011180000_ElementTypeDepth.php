<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Stacking level of each element type on the board: 1 sheet, 2 trace,
 * 3 volume or effect. Safe before or after the code: without the column,
 * the code takes fluid types for sheets and the others for volumes.
 */
final class Version20261011180000_ElementTypeDepth extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'element_types.depth : niveau d\'empilement sur le damier (nappe, trace au sol, volume ou effet)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE element_types ADD COLUMN IF NOT EXISTS depth TINYINT UNSIGNED NOT NULL DEFAULT 3');
        $this->addSql("UPDATE element_types SET depth = 1 WHERE name IN ('eau', 'lave', 'styx', 'boue', 'poison', 'eau_cascade')");
        $this->addSql("UPDATE element_types SET depth = 2 WHERE name = 'sang' OR name LIKE 'trace\\\\_pas%'");
        $this->addSql("UPDATE element_types SET depth = 3 WHERE name IN ('feu', 'ronce', 'diamant')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE element_types DROP COLUMN IF EXISTS depth');
    }
}
