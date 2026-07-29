<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Harvestable types get their own nature, and leave the buildings list.
 *
 * Entering the catalogue as `obstacle` put 39 trees, stones and peat bogs in
 * the building types — the list an animator scrolls to place a wall. Exactly
 * what happened to scenery, and it was fixed the same way: a nature of their
 * own, no second table.
 *
 * A type is harvestable when `resource_types` says pv = -1, the flag the game
 * already reads. Derived rather than listed, so a re-run picks the right rows.
 *
 * The two altar entries are dropped from `resource_types` at the same time:
 * the altar is an entity, no `map_resources` row wears the name any more, and
 * an entry claiming 25 destructible points describes nothing.
 */
final class Version20260730180000_HarvestableTypesLeaveTheBuildings extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Harvestable types move to structure_nature = ressource; dead altar rows leave resource_types';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE races r
                SET r.structure_nature = 'ressource'
              WHERE r.kind = 'structure'
                AND r.structure_nature <> 'ressource'
                AND EXISTS (
                    SELECT 1 FROM resource_types t
                     WHERE t.name COLLATE utf8mb4_general_ci = r.name COLLATE utf8mb4_general_ci
                       AND t.pv = -1
                )"
        );

        /* Only if nothing wears the name on the board: an altar left as a
         * resource row somewhere would lose its destructibility silently. */
        $this->addSql(
            "DELETE FROM resource_types
              WHERE name IN ('altar', 'altar_broken')
                AND NOT EXISTS (
                    SELECT 1 FROM map_resources m
                     WHERE m.name COLLATE utf8mb4_general_ci = resource_types.name COLLATE utf8mb4_general_ci
                )"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE races
                SET structure_nature = 'obstacle'
              WHERE kind = 'structure' AND structure_nature = 'ressource'"
        );

        $this->addSql(
            "INSERT IGNORE INTO resource_types (name, pv) VALUES ('altar', 25), ('altar_broken', 25)"
        );
    }
}
