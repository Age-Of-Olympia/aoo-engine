<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A building takes room: every édifice type starts at 2×2.
 *
 * The footprint machinery already honors it everywhere — cells, blocking,
 * line of fire, adjacency, work × cells — an édifice was 1×1 only because
 * no entity_type_footprints row named it. This seeds the game's floor for
 * the types that lack any declaration; a hand-tuned figure is left alone,
 * and the type editor (or Cartes → Emprises) can change any of them.
 *
 * Obstacles (walls, palissades) deliberately stay 1×1: they draw lines.
 * Entities ALREADY PLACED keep the cells they hold until something syncs
 * them — new placements claim the full box.
 */
final class Version20260804130000_EdificesTakeRoom extends AbstractMigration
{
    private const BOX_2X2 = '[[0,0],[1,0],[0,-1],[1,-1]]';

    public function getDescription(): string
    {
        return "Emprise 2×2 pour les types d'édifice sans déclaration ; les obstacles restent 1×1";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "INSERT INTO entity_type_footprints (type_name, w, h, offsets)
             SELECT r.name, 2, 2, '" . self::BOX_2X2 . "'
               FROM races r
              WHERE r.type_kind = 'building' AND r.structure_nature = 'edifice'
                AND NOT EXISTS (
                    SELECT 1 FROM entity_type_footprints f
                     WHERE CONVERT(f.type_name USING utf8mb4) = CONVERT(r.name USING utf8mb4)
                )"
        );
    }

    public function down(Schema $schema): void
    {
        // Only the untouched seeds go; a figure edited since stays.
        $this->addSql(
            "DELETE f FROM entity_type_footprints f
               JOIN races r ON CONVERT(f.type_name USING utf8mb4) = CONVERT(r.name USING utf8mb4)
              WHERE r.type_kind = 'building' AND r.structure_nature = 'edifice'
                AND f.w = 2 AND f.h = 2 AND f.offsets = '" . self::BOX_2X2 . "' AND f.roles IS NULL"
        );
    }
}
