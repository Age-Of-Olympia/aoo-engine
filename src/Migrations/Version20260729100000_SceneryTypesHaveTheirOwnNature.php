<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Scenery types get their own `structure_nature`, so they have their own list.
 *
 * They were created as `edifice`, which meant a hundred-odd decor families sat
 * in the building types list — the population an animator scrolls when placing
 * a wall. `decor` separates them without a second table: the editor already
 * serves several faces of `races`, and this is the column it reads.
 *
 * A type is scenery when a `scenery` entity wears it. Derived rather than
 * remembered, so the migration re-runs without picking the wrong rows.
 */
final class Version20260729100000_SceneryTypesHaveTheirOwnNature extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Scenery types move to structure_nature = decor, giving them their own editor face';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE races r
                SET r.structure_nature = 'decor'
              WHERE r.kind = 'structure'
                AND r.structure_nature <> 'decor'
                AND EXISTS (
                    SELECT 1 FROM players p
                     WHERE p.race = r.name AND p.player_type = 'scenery'
                )"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE races SET structure_nature = 'edifice' WHERE structure_nature = 'decor'");
    }
}
