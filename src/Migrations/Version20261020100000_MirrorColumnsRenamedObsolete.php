<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Renames the 13 `players` columns that accounts, turns and progression
 * replaced (no code reads them since Version20261019120000) to obsolete_*,
 * one release before dropping them: a rollback only has to rename them back.
 *
 * down() renames them back and refills them from the tables, so code that
 * still reads the columns gets current values. Idempotent both ways.
 */
final class Version20261020100000_MirrorColumnsRenamedObsolete extends AbstractMigration
{
    /** Column => definition, as created by the earlier migrations. */
    private const COLUMNS = [
        'psw' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'mail' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'plain_mail' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'email_bonus' => 'TINYINT(1) DEFAULT 0',
        'lastLoginTime' => 'INT(11) NOT NULL DEFAULT 0',
        'nextTurnTime' => 'INT(11) NOT NULL DEFAULT 0',
        'lastActionTime' => 'INT(11) NOT NULL DEFAULT 0',
        'nextTurnRescheduled' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'antiBerserkTime' => 'INT(11) NOT NULL DEFAULT 0',
        'xp' => 'INT(11) NOT NULL DEFAULT 0',
        'rank' => 'INT(11) NOT NULL DEFAULT 1',
        'bonus_points' => 'INT(11) NOT NULL DEFAULT 0',
        'pi' => 'INT(11) NOT NULL DEFAULT 0',
    ];

    public function getDescription(): string
    {
        return 'renommage en obsolete_* des colonnes de players remplacées par accounts, turns et progression';
    }

    public function up(Schema $schema): void
    {
        $players = $schema->getTable('players');
        foreach (self::COLUMNS as $column => $definition) {
            if ($players->hasColumn($column)) {
                $this->addSql("ALTER TABLE players CHANGE `{$column}` `obsolete_{$column}` {$definition}");
            }
        }
    }

    public function down(Schema $schema): void
    {
        $players = $schema->getTable('players');
        foreach (self::COLUMNS as $column => $definition) {
            if ($players->hasColumn('obsolete_' . $column)) {
                $this->addSql("ALTER TABLE players CHANGE `obsolete_{$column}` `{$column}` {$definition}");
            }
        }

        $this->addSql(
            'UPDATE players p JOIN accounts a ON a.player_id = p.id
                SET p.psw = a.psw, p.mail = a.mail, p.plain_mail = a.plain_mail,
                    p.email_bonus = a.email_bonus, p.lastLoginTime = a.last_login_time'
        );
        $this->addSql(
            'UPDATE players p JOIN turns t ON t.player_id = p.id
                SET p.nextTurnTime = t.next_turn_time, p.lastActionTime = t.last_action_time,
                    p.nextTurnRescheduled = t.next_turn_rescheduled, p.antiBerserkTime = t.anti_berserk_time'
        );
        $this->addSql(
            'UPDATE players p JOIN progression g ON g.player_id = p.id
                SET p.xp = g.xp, p.`rank` = g.`rank`, p.bonus_points = g.bonus_points, p.pi = g.pi'
        );
    }
}
