<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drop `tutorial_players.real_player_id`; the canonical link is now
 * `players.real_player_id_ref`. Deploy after the reader/writer rewrites ship.
 *
 * Pre-migration audit (must return 0 on the target DB):
 *   SELECT COUNT(*) FROM tutorial_players tp
 *   JOIN players p ON p.id = tp.player_id
 *   WHERE p.player_type = 'tutorial'
 *     AND (p.real_player_id_ref IS NULL OR p.real_player_id_ref <> tp.real_player_id);
 * If it isn't 0, backfill `real_player_id_ref` from the old column first.
 */
final class Version20260419200000_DropTutorialPlayersRealPlayerIdColumn extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop tutorial_players.real_player_id (canonical link is players.real_player_id_ref)';
    }

    public function isTransactional(): bool
    {
        // MariaDB implicitly commits at every ALTER TABLE; wrapping DDL
        // in a transaction isn't meaningful and trips Doctrine's
        // all-or-nothing guard.
        return false;
    }

    public function up(Schema $schema): void
    {
        // FK first (blocks the index drop), then index, then column.
        $this->addSql('ALTER TABLE `tutorial_players` DROP FOREIGN KEY `tutorial_players_ibfk_1`');
        $this->addSql('ALTER TABLE `tutorial_players` DROP INDEX `idx_real_player`');
        $this->addSql('ALTER TABLE `tutorial_players` DROP COLUMN `real_player_id`');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE `tutorial_players` '
            . 'ADD COLUMN `real_player_id` INT(11) NOT NULL '
            . "COMMENT 'Link to actual player account' AFTER `id`"
        );
        // Rebuild values from the canonical column before re-enforcing NOT NULL + FK.
        $this->addSql('
            UPDATE `tutorial_players` tp
            JOIN `players` p ON p.id = tp.player_id
            SET tp.real_player_id = p.real_player_id_ref
            WHERE p.player_type = "tutorial" AND p.real_player_id_ref IS NOT NULL
        ');
        $this->addSql('ALTER TABLE `tutorial_players` ADD INDEX `idx_real_player` (`real_player_id`)');
        $this->addSql(
            'ALTER TABLE `tutorial_players` '
            . 'ADD CONSTRAINT `tutorial_players_ibfk_1` '
            . 'FOREIGN KEY (`real_player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE'
        );
    }
}
