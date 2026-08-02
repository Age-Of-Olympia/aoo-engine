<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * What an entity earns moves to its own table: `progression`, keyed by
 * player_id — experience, rank, the season's banked overflow, and the PI that
 * experience mints.
 *
 * Only characters get a row. The `players` columns stay filled and are still
 * written to until the code that reads them is gone; dropping them is a
 * separate, post-deployment pass.
 */
final class Version20260803310000_ProgressionLeavesTheCharacter extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'progression table: xp, rank, bonus points and PI leave the players row';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS progression (
                player_id INT NOT NULL,
                xp INT NOT NULL DEFAULT 0,
                `rank` INT NOT NULL DEFAULT 1,
                bonus_points INT NOT NULL DEFAULT 0,
                pi INT NOT NULL DEFAULT 0,
                PRIMARY KEY (player_id),
                CONSTRAINT fk_progression_player FOREIGN KEY (player_id)
                    REFERENCES players (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'
        );

        /* Characters only, and idempotent: re-running must not overwrite a
         * progression the new code has already earned. */
        $this->addSql(
            "INSERT IGNORE INTO progression (player_id, xp, `rank`, bonus_points, pi)
             SELECT id,
                    COALESCE(xp, 0), COALESCE(`rank`, 1),
                    COALESCE(bonus_points, 0), COALESCE(pi, 0)
               FROM players
              WHERE player_type IN ('real', 'tutorial', 'npc') OR player_type IS NULL"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS progression');
    }
}
