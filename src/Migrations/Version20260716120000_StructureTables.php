<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Satellite tables for the Structure branch of the GameEntity STI
 * (docs/design-buildings-entities.md §4.5): `buildings` and
 * `unique_objects`, both 1:1 on players.id.
 *
 * No change to `players` itself — the new 'building' / 'unique'
 * discriminator values fit the existing player_type column, and their
 * id ranges are carved out in ENTITY_ID_RANGES (20M+ / 30M+).
 *
 * `faction` stores the faction CODE (factions.code) without a DB FK,
 * the same convention as players.faction; `build_state` is a free
 * string so future lifecycle states (usure/dégradation…) need no
 * schema change. Idempotent: IF NOT EXISTS throughout.
 */
final class Version20260716120000_StructureTables extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create buildings and unique_objects satellite tables for Structure entities';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "CREATE TABLE IF NOT EXISTS buildings (
                player_id INT NOT NULL PRIMARY KEY,
                archetype VARCHAR(64) NOT NULL,
                owner_id INT DEFAULT NULL,
                faction VARCHAR(100) NOT NULL DEFAULT '',
                build_state VARCHAR(20) NOT NULL DEFAULT 'built',
                INDEX idx_buildings_owner (owner_id),
                CONSTRAINT fk_buildings_player FOREIGN KEY (player_id) REFERENCES players (id),
                CONSTRAINT fk_buildings_owner FOREIGN KEY (owner_id) REFERENCES players (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );

        $this->addSql(
            "CREATE TABLE IF NOT EXISTS unique_objects (
                player_id INT NOT NULL PRIMARY KEY,
                archetype VARCHAR(64) NOT NULL,
                interaction TEXT DEFAULT NULL,
                CONSTRAINT fk_unique_objects_player FOREIGN KEY (player_id) REFERENCES players (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS unique_objects');
        $this->addSql('DROP TABLE IF EXISTS buildings');
    }
}
