<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The logs are read by period (the feed's last three days, the archive
 * cron, digging into the archives) and by a player's last move: indexes
 * for both, on the live table and on its archive. Built online.
 */
final class Version20261023100000_LogsIndexedByTime extends AbstractMigration
{
    private const TABLES = ['players_logs', 'players_logs_archives'];

    public function getDescription(): string
    {
        return 'index par date et par (joueur, type, date) sur players_logs et players_logs_archives';
    }

    public function up(Schema $schema): void
    {
        foreach (self::TABLES as $table) {
            $this->addSql("ALTER TABLE $table ADD INDEX IF NOT EXISTS idx_{$table}_time (time), ALGORITHM=INPLACE, LOCK=NONE");
            $this->addSql("ALTER TABLE $table ADD INDEX IF NOT EXISTS idx_{$table}_player_type_time (player_id, type, time), ALGORITHM=INPLACE, LOCK=NONE");
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::TABLES as $table) {
            $this->addSql("ALTER TABLE $table DROP INDEX IF EXISTS idx_{$table}_time");
            $this->addSql("ALTER TABLE $table DROP INDEX IF EXISTS idx_{$table}_player_type_time");
        }
    }
}
