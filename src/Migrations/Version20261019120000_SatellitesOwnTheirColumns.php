<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Last copy of the `players` mirror columns into `accounts`, `turns` and
 * `progression`, before the code stops reading and writing them (and the next
 * release drops them).
 *
 * It settles each value on what Player::get_row() answered until now: the
 * satellite when it holds something, the column when the satellite is missing
 * or empty — a write that reached the column alone. Rows that do not exist yet
 * are created from the columns, for the same entities as PlaysTurns (and every
 * character for `accounts`).
 *
 * Idempotent; down() has nothing to undo.
 */
final class Version20261019120000_SatellitesOwnTheirColumns extends AbstractMigration
{
    private const PLAYS = "(p.player_type IN ('real', 'tutorial', 'npc')
          OR p.player_type IS NULL
          OR r.playable = 1)";

    public function getDescription(): string
    {
        return 'dernière recopie des colonnes miroirs de players vers accounts, turns et progression';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "INSERT IGNORE INTO accounts (player_id, psw, mail, plain_mail, email_bonus, last_login_time)
             SELECT p.id, p.psw, p.mail, p.plain_mail, p.email_bonus, p.lastLoginTime
               FROM players p
              WHERE p.player_type IN ('real', 'tutorial', 'npc') OR p.player_type IS NULL"
        );
        $this->addSql(
            'INSERT IGNORE INTO turns (player_id, next_turn_time, last_action_time, next_turn_rescheduled, anti_berserk_time)
             SELECT p.id, p.nextTurnTime, p.lastActionTime, p.nextTurnRescheduled, p.antiBerserkTime
               FROM players p
               LEFT JOIN races r ON r.name = p.race
              WHERE ' . self::PLAYS
        );
        $this->addSql(
            'INSERT IGNORE INTO progression (player_id, xp, `rank`, bonus_points, pi)
             SELECT p.id, p.xp, p.`rank`, p.bonus_points, p.pi
               FROM players p
               LEFT JOIN races r ON r.name = p.race
              WHERE ' . self::PLAYS
        );

        $this->addSql(
            "UPDATE accounts a JOIN players p ON p.id = a.player_id
                SET a.psw = COALESCE(NULLIF(a.psw, ''), p.psw),
                    a.mail = COALESCE(NULLIF(a.mail, ''), p.mail),
                    a.plain_mail = COALESCE(NULLIF(a.plain_mail, ''), p.plain_mail),
                    a.email_bonus = COALESCE(a.email_bonus, p.email_bonus),
                    a.last_login_time = COALESCE(NULLIF(a.last_login_time, 0), p.lastLoginTime)"
        );
        $this->addSql(
            'UPDATE turns t JOIN players p ON p.id = t.player_id
                SET t.next_turn_time = COALESCE(NULLIF(t.next_turn_time, 0), p.nextTurnTime),
                    t.last_action_time = COALESCE(NULLIF(t.last_action_time, 0), p.lastActionTime),
                    t.next_turn_rescheduled = COALESCE(NULLIF(t.next_turn_rescheduled, 0), p.nextTurnRescheduled),
                    t.anti_berserk_time = COALESCE(NULLIF(t.anti_berserk_time, 0), p.antiBerserkTime)'
        );
        $this->addSql(
            'UPDATE progression g JOIN players p ON p.id = g.player_id
                SET g.xp = COALESCE(NULLIF(g.xp, 0), p.xp),
                    g.`rank` = COALESCE(NULLIF(g.`rank`, 0), p.`rank`),
                    g.bonus_points = COALESCE(NULLIF(g.bonus_points, 0), p.bonus_points),
                    g.pi = COALESCE(NULLIF(g.pi, 0), p.pi)'
        );
    }

    public function down(Schema $schema): void
    {
        // Nothing to undo: the columns are left as they were
    }
}
