<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926200000_EveryPlacedRoadHasItsType extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'roads laid without a type (carreaux, sentier…) get their RouteType: listed in Routes → Types, and walkable';
    }

    public function up(Schema $schema): void
    {
        // Same row as RouteTypeService::ensure(); the images are not in the checkout, the placed roads are
        $this->addSql(
            "INSERT INTO races (code, name, label, description, playable, hidden, kind, type_kind,
                                structure_nature, bleeds, wound_color, blocks_passage, blocks_projectiles,
                                pv, spd, bgColor, color, repairable, faction, plan)
             SELECT UPPER(t.race), t.race, CONCAT(UPPER(LEFT(t.race, 1)), SUBSTRING(REPLACE(t.race, '_', ' '), 2)),
                    '', 0, 1, 'structure', 'route', 'route', '', '#8b4513', 0, 0,
                    60, 16, '#8b4513', 'black', 1, '', ''
               FROM (SELECT DISTINCT race FROM players WHERE player_type = 'route') t
              WHERE NOT EXISTS (
                    SELECT 1 FROM races r WHERE CONVERT(r.name USING utf8mb4) = CONVERT(t.race USING utf8mb4)
                )"
        );
    }

    public function down(Schema $schema): void
    {
        // Types that roads on the map rely on: not removed
    }
}
