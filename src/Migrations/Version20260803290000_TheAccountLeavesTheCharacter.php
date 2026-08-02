<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Credentials move to their own table: `accounts`, keyed by player_id.
 *
 * Only characters get a row — a forge has no password. The `players` columns
 * stay filled and are still written to until the code that reads them is gone;
 * dropping them is a separate, post-deployment pass.
 */
final class Version20260803290000_TheAccountLeavesTheCharacter extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'accounts table: credentials leave the players row';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS accounts (
                player_id INT NOT NULL,
                psw VARCHAR(255) NOT NULL DEFAULT \'\',
                mail VARCHAR(255) NOT NULL DEFAULT \'\',
                plain_mail VARCHAR(255) NOT NULL DEFAULT \'\',
                email_bonus TINYINT(1) DEFAULT 0,
                last_login_time INT NOT NULL DEFAULT 0,
                PRIMARY KEY (player_id),
                CONSTRAINT fk_accounts_player FOREIGN KEY (player_id)
                    REFERENCES players (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'
        );

        /* Characters only, and idempotent: re-running must not overwrite an
         * account the new code has already updated. */
        $this->addSql(
            "INSERT IGNORE INTO accounts (player_id, psw, mail, plain_mail, email_bonus, last_login_time)
             SELECT id,
                    COALESCE(psw, ''), COALESCE(mail, ''), COALESCE(plain_mail, ''),
                    email_bonus, COALESCE(lastLoginTime, 0)
               FROM players
              WHERE player_type IN ('real', 'tutorial', 'npc') OR player_type IS NULL"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS accounts');
    }
}
