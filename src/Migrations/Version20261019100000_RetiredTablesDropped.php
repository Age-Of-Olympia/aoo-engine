<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drops what the previous release stopped reading and emptied
 * (Version20261018200000_RetiredTablesEmptied):
 *
 * - map_resources, map_plants, map_routes: resources, plants and roads are entities;
 * - unique_objects, tutorial_map_instances, players_items_instances: no writer;
 * - plans.biomes: yields live on the types and in race_harvest.
 *
 * Idempotent. down() restores plans.biomes empty; the tables are not recreated.
 */
final class Version20261019100000_RetiredTablesDropped extends AbstractMigration
{
    private const TABLES = [
        'map_resources', 'map_plants', 'map_routes',
        'unique_objects', 'tutorial_map_instances', 'players_items_instances',
    ];

    public function getDescription(): string
    {
        return 'suppression des tables retirées et de plans.biomes';
    }

    public function up(Schema $schema): void
    {
        foreach (self::TABLES as $table) {
            $this->addSql('DROP TABLE IF EXISTS ' . $table);
        }
        $this->addSql('ALTER TABLE plans DROP COLUMN IF EXISTS biomes');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plans ADD COLUMN IF NOT EXISTS biomes TEXT DEFAULT NULL');
    }
}
