<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251112171438 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tutorial_players table for temporary tutorial characters (one per tutorial session)';
    }

    public function isTransactional(): bool
    {
        return false; // Disable transactions for raw SQL
    }

    public function up(Schema $schema): void
    {
        // Create tutorial_players table - temporary characters for tutorial sessions
        $this->addSql("
            CREATE TABLE IF NOT EXISTS tutorial_players (
                id INT AUTO_INCREMENT PRIMARY KEY,
                real_player_id INT NOT NULL COMMENT 'Link to actual player account',
                tutorial_session_id VARCHAR(36) NOT NULL COMMENT 'Link to tutorial_progress session',
                name VARCHAR(255) NOT NULL COMMENT 'Character name (e.g., Héros en formation)',
                coords_id INT NOT NULL COMMENT 'Position on tutorial map',
                race VARCHAR(255) NOT NULL DEFAULT 'Humain' COMMENT 'Character race',
                xp INT NOT NULL DEFAULT 0 COMMENT 'Tutorial XP (separate from real player)',
                pi INT NOT NULL DEFAULT 0 COMMENT 'Investment points earned in tutorial',
                energie INT NOT NULL DEFAULT 100 COMMENT 'Energy/action points',
                level INT NOT NULL DEFAULT 1 COMMENT 'Character level in tutorial',
                is_active BOOLEAN DEFAULT TRUE COMMENT 'Is this tutorial character currently active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL COMMENT 'Soft delete when tutorial completes',
                INDEX idx_real_player (real_player_id),
                INDEX idx_session (tutorial_session_id),
                INDEX idx_coords (coords_id),
                INDEX idx_active (is_active),
                UNIQUE KEY unique_session_char (tutorial_session_id),
                FOREIGN KEY (real_player_id) REFERENCES players(id) ON DELETE CASCADE,
                FOREIGN KEY (coords_id) REFERENCES coords(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT = 'Temporary characters created for each tutorial instance - deleted when tutorial completes'
        ");
    }

    public function down(Schema $schema): void
    {
        // Rollback: drop tutorial_players table
        $this->addSql('DROP TABLE IF EXISTS tutorial_players');
    }
}
