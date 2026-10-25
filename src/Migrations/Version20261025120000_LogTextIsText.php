<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * An event text is no longer cut at 255 characters (a death dropping dozens
 * of items wrote more). Same type as hiddenText, archive included.
 */
final class Version20261025120000_LogTextIsText extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'players_logs(.archives).text en TEXT';
    }

    public function up(Schema $schema): void
    {
        foreach (['players_logs', 'players_logs_archives'] as $table) {
            $this->addSql("ALTER TABLE $table MODIFY `text` TEXT NOT NULL DEFAULT ''");
        }
    }

    public function down(Schema $schema): void
    {
        foreach (['players_logs', 'players_logs_archives'] as $table) {
            $this->addSql("UPDATE $table SET `text` = LEFT(`text`, 255) WHERE CHAR_LENGTH(`text`) > 255");
            $this->addSql("ALTER TABLE $table MODIFY `text` VARCHAR(255) NOT NULL DEFAULT ''");
        }
    }
}
