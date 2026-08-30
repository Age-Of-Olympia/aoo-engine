<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Road items say they are roads, so running on one works again.
 *
 * Laying a road stopped granting the +1 MVT that is the whole reason to lay
 * one. `courir` asks `map_routes`, which only the map editor writes; a player
 * crafts a `route`, places it, and the placement action installs an exemplar
 * on the cell instead — a road nothing recognised.
 *
 * `RoadService` now asks both, and recognises a road item by its workbench
 * subtype, the vocabulary that field already documents ("walls, routes…").
 * This marks the ones that exist; the several types to come will be created
 * with it.
 */
final class Version20260831100000_RoadItemsCarryTheirSubtype extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'les objets route portent le sous-type « routes » : courir retrouve son bonus sur une route posée par un joueur';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE items SET subtype = 'routes'
              WHERE type = 'constructible'
                AND subtype = ''
                AND name IN ('route')"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE items SET subtype = '' WHERE subtype = 'routes'");
    }
}
