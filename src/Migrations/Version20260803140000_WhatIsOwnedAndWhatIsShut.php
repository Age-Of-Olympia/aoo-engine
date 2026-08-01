<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Being owned and being shut move onto the entity.
 *
 * `buildings.owner_id` and `buildings.is_open` sat on the building satellite,
 * so only a building could be owned or closed. Nothing about either is
 * building-specific: a chest is shut by whoever owns it exactly as a forge is,
 * and `closureReason()` was already one rule — only its signature, taking a
 * `BuildingDetails`, kept it narrow.
 *
 * ON DELETE SET NULL on the owner: a character who disappears leaves their
 * belongings ownerless rather than undeletable. Losing a claim is recoverable;
 * a row that refuses to die is not.
 *
 * `buildings.faction` is deliberately NOT moved. `players.faction` already
 * exists and the two DISAGREE on every building that has one — the satellite
 * says a faction, the entity says none. Reconciling them is a decision about
 * which has been the truth, not a column move, and ownership-by-faction cannot
 * be written until it is answered.
 */
final class Version20260803140000_WhatIsOwnedAndWhatIsShut extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'players.owner_id/is_open: anything can be owned and shut, not just buildings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE players ADD COLUMN IF NOT EXISTS owner_id INT(11) NULL DEFAULT NULL');
        $this->addSql('ALTER TABLE players ADD COLUMN IF NOT EXISTS is_open TINYINT(1) NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE players ADD INDEX IF NOT EXISTS idx_players_owner (owner_id)');

        $this->addConstraintIfMissing(
            'players',
            'fk_players_owner',
            'ALTER TABLE players
             ADD CONSTRAINT fk_players_owner FOREIGN KEY (owner_id)
             REFERENCES players (id) ON DELETE SET NULL'
        );

        $this->addSql(
            'UPDATE players p
               JOIN buildings b ON b.player_id = p.id
                SET p.owner_id = b.owner_id, p.is_open = b.is_open'
        );

        $this->addSql('ALTER TABLE buildings DROP FOREIGN KEY IF EXISTS fk_buildings_owner');
        $this->addSql('ALTER TABLE buildings DROP COLUMN IF EXISTS owner_id');
        $this->addSql('ALTER TABLE buildings DROP COLUMN IF EXISTS is_open');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE buildings ADD COLUMN IF NOT EXISTS owner_id INT(11) NULL DEFAULT NULL');
        $this->addSql('ALTER TABLE buildings ADD COLUMN IF NOT EXISTS is_open TINYINT(1) NOT NULL DEFAULT 1');

        $this->addSql(
            'UPDATE buildings b
               JOIN players p ON p.id = b.player_id
                SET b.owner_id = p.owner_id, b.is_open = p.is_open'
        );

        $this->addSql('ALTER TABLE players DROP FOREIGN KEY IF EXISTS fk_players_owner');
        $this->addSql('ALTER TABLE players DROP INDEX IF EXISTS idx_players_owner');
        $this->addSql('ALTER TABLE players DROP COLUMN IF EXISTS is_open');
        $this->addSql('ALTER TABLE players DROP COLUMN IF EXISTS owner_id');
    }

    /** MariaDB has no `ADD CONSTRAINT IF NOT EXISTS`: look first, add if missing. */
    private function addConstraintIfMissing(string $table, string $name, string $sql): void
    {
        $exists = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
              WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$table, $name]
        );

        if ($exists === 0) {
            $this->addSql($sql);
        }
    }
}
