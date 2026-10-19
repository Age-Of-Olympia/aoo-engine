<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drops the 13 obsolete_* columns of `players` (renamed by
 * Version20261020100000): accounts, turns and progression hold the values.
 * Idempotent; down() cannot bring them back — restore them from a backup.
 */
final class Version20261021100000_ObsoleteColumnsDropped extends AbstractMigration
{
    private const COLUMNS = [
        'psw', 'mail', 'plain_mail', 'email_bonus', 'lastLoginTime',
        'nextTurnTime', 'lastActionTime', 'nextTurnRescheduled', 'antiBerserkTime',
        'xp', 'rank', 'bonus_points', 'pi',
    ];

    public function getDescription(): string
    {
        return 'suppression des colonnes obsolete_* de players';
    }

    public function up(Schema $schema): void
    {
        foreach (self::COLUMNS as $column) {
            $this->addSql("ALTER TABLE players DROP COLUMN IF EXISTS `obsolete_{$column}`");
        }
    }

    public function down(Schema $schema): void
    {
        // The values live in accounts, turns and progression; the columns are not recreated
    }
}
