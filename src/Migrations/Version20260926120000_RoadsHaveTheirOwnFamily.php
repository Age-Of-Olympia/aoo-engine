<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926120000_RoadsHaveTheirOwnFamily extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'road types get their own family (nature route), out of the decor: they never block';
    }

    private const DERIVATION = "CASE
        WHEN NEW.kind <> 'structure' THEN 'character'
        WHEN NEW.structure_nature = 'decor' THEN 'scenery'
        WHEN NEW.structure_nature = 'ressource' THEN 'resource'
        WHEN NEW.structure_nature = 'plante' THEN 'plant'
        WHEN NEW.structure_nature = 'route' THEN 'route'
        ELSE 'building'
    END";

    private const DERIVATION_BEFORE = "CASE
        WHEN NEW.kind <> 'structure' THEN 'character'
        WHEN NEW.structure_nature = 'decor' THEN 'scenery'
        WHEN NEW.structure_nature = 'ressource' THEN 'resource'
        WHEN NEW.structure_nature = 'plante' THEN 'plant'
        ELSE 'building'
    END";

    public function up(Schema $schema): void
    {
        $this->replaceTriggers(self::DERIVATION);

        /* A road type is one the board lays as a road; `route` itself even
         * when none is placed yet. */
        $this->addSql(
            "UPDATE races
                SET structure_nature = 'route', type_kind = 'route', blocks_passage = 0, blocks_projectiles = 0
              WHERE kind = 'structure'
                AND (name = 'route'
                     OR CONVERT(name USING utf8mb4) IN (
                        SELECT CONVERT(race USING utf8mb4) FROM players WHERE player_type = 'route'
                     ))"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE races SET structure_nature = 'decor', type_kind = 'scenery' WHERE type_kind = 'route'"
        );

        $this->replaceTriggers(self::DERIVATION_BEFORE);
    }

    private function replaceTriggers(string $derivation): void
    {
        foreach (['bi' => 'BEFORE INSERT', 'bu' => 'BEFORE UPDATE'] as $suffix => $moment) {
            $this->addSql("DROP TRIGGER IF EXISTS races_type_kind_{$suffix}");
            $this->addSql(
                "CREATE TRIGGER races_type_kind_{$suffix} {$moment} ON races FOR EACH ROW
                 SET NEW.type_kind = IF(
                     NEW.type_kind IS NULL OR NEW.type_kind = '',
                     {$derivation},
                     NEW.type_kind
                 )"
            );
        }
    }
}
