<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * When an entity plays moves to its own table: `turns`, keyed by player_id.
 *
 * Only characters get a row — a forge takes turns the day it is playable, and
 * it takes them through this table rather than through a character column. The
 * `players` columns stay filled and are still written to until the code that
 * reads them is gone; dropping them is a separate, post-deployment pass.
 */
final class Version20260803300000_TheTurnLeavesTheCharacter extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'turns table: the turn clock leaves the players row';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS turns (
                player_id INT NOT NULL,
                next_turn_time INT NOT NULL DEFAULT 0,
                last_action_time INT NOT NULL DEFAULT 0,
                next_turn_rescheduled TINYINT(1) NOT NULL DEFAULT 0,
                anti_berserk_time INT NOT NULL DEFAULT 0,
                PRIMARY KEY (player_id),
                CONSTRAINT fk_turns_player FOREIGN KEY (player_id)
                    REFERENCES players (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'
        );

        /* Characters only, and idempotent: re-running must not overwrite a
         * turn the new code has already scheduled. */
        $this->addSql(
            "INSERT IGNORE INTO turns
                 (player_id, next_turn_time, last_action_time, next_turn_rescheduled, anti_berserk_time)
             SELECT id,
                    COALESCE(nextTurnTime, 0), COALESCE(lastActionTime, 0),
                    COALESCE(nextTurnRescheduled, 0), COALESCE(antiBerserkTime, 0)
               FROM players
              WHERE player_type IN ('real', 'tutorial', 'npc') OR player_type IS NULL"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS turns');
    }
}
