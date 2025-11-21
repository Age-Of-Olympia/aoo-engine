<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add tutorial_settings table for feature flags configuration
 *
 * This table stores configurable settings for the tutorial system:
 * - global_enabled: Enable tutorial for all players
 * - whitelisted_players: Comma-separated list of player IDs with access
 * - auto_show_new_players: Auto-prompt new players to start tutorial
 */
final class Version20251121100000_AddTutorialSettingsTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tutorial_settings table for feature flags configuration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE IF NOT EXISTS tutorial_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT,
                description VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_setting_key (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Insert default settings
        $this->addSql("
            INSERT IGNORE INTO tutorial_settings (setting_key, setting_value, description) VALUES
            ('global_enabled', '0', 'Enable tutorial globally for all players'),
            ('whitelisted_players', '1,2,3', 'Comma-separated list of player IDs who can access tutorial regardless of global setting'),
            ('auto_show_new_players', '1', 'Automatically show tutorial to new players')
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS tutorial_settings');
    }

    public function isTransactional(): bool
    {
        return true;
    }
}
