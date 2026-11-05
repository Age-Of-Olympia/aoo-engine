<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * State of each module (App\Module\Module): on for everyone, plus testers
 * who get it while it is off.
 *
 * The tutorial becomes the first module, seeded from this server's
 * tutorial_settings (global_enabled, whitelisted_players). Those two rows
 * stay until a later migration: the code still running during the deploy
 * reads them.
 */
final class Version20261105100000_Modules extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'table modules ; le tutoriel devient le premier module, avec les réglages du serveur';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE IF NOT EXISTS modules (
                name VARCHAR(64) NOT NULL PRIMARY KEY,
                enabled TINYINT(1) NOT NULL DEFAULT 0,
                players JSON NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $settings = $this->connection->fetchAllKeyValue(
            "SELECT setting_key, setting_value FROM tutorial_settings
             WHERE setting_key IN ('global_enabled', 'whitelisted_players')"
        );

        $enabled = filter_var($settings['global_enabled'] ?? '0', FILTER_VALIDATE_BOOLEAN);
        $players = array_values(array_filter(
            array_map('intval', explode(',', (string) ($settings['whitelisted_players'] ?? ''))),
            static fn (int $id): bool => $id > 0
        ));

        $this->addSql(
            "INSERT IGNORE INTO modules (name, enabled, players) VALUES ('tutorial', ?, ?)",
            [(int) $enabled, json_encode($players)]
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS modules');
    }
}
