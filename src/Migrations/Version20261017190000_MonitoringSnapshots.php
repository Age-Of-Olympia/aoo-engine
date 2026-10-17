<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * One row per day of game measures (gold, XP, items, admins, table sizes),
 * written by the daily cron: the monitoring dashboard compares against it
 * to show trends and unusual gains.
 */
final class Version20261017190000_MonitoringSnapshots extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'crée monitoring_snapshots : un relevé quotidien pour le monitoring';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS monitoring_snapshots (
                day DATE NOT NULL,
                taken_at DATETIME NOT NULL,
                metrics LONGTEXT NOT NULL,
                PRIMARY KEY (day)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS monitoring_snapshots');
    }
}
