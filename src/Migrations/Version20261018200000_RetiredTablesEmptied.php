<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Empties the tables the code stops touching in this release, ahead of their
 * drop in the next one:
 *
 * - map_resources, map_plants, map_routes: resources, plants and roads are
 *   entities; what is left are lines already converted and lines on past
 *   seasons' plans (Version20261018140000 recovered the live roads);
 * - unique_objects, tutorial_map_instances, players_items_instances: no
 *   writer any more.
 *
 * Emptied first because map_routes still holds foreign keys to coords: plan
 * deletion no longer clears it, and a leftover line would block it. The code
 * still deployed during the migration only ever deleted from these tables.
 * Idempotent; down() cannot bring the rows back.
 */
final class Version20261018200000_RetiredTablesEmptied extends AbstractMigration
{
    private const TABLES = [
        'map_resources', 'map_plants', 'map_routes',
        'unique_objects', 'tutorial_map_instances', 'players_items_instances',
    ];

    public function getDescription(): string
    {
        return 'vidage des tables retirées (couches devenues entités, liens sans écrivain) avant leur suppression';
    }

    public function up(Schema $schema): void
    {
        foreach (self::TABLES as $table) {
            if ($schema->hasTable($table)) {
                $this->addSql('DELETE FROM ' . $table);
            }
        }
    }

    public function down(Schema $schema): void
    {
        // The deleted rows are not restored
    }
}
