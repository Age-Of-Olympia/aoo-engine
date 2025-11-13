<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 0.1: Add tutorial system tables
 *
 * WARNING: This migration uses RAW SQL to avoid Doctrine schema diff issues.
 * Doctrine would try to drop all legacy tables it doesn't know about!
 *
 * These tables support:
 * - Tutorial progress tracking per player
 * - Multiple tutorial sessions (repeatable tutorial)
 * - Tutorial versioning for future game updates (e.g., when buildings are added)
 * - XP/PI progression tracking during tutorial
 * - All tutorial steps stored in database (NO JSON files to maintain)
 *
 * IMPORTANT:
 * - Non-breaking migration - only creates new tables
 * - Uses raw SQL to avoid touching legacy tables
 * - Can be run safely alongside existing game
 */
final class Version20251111120000_AddTutorialTables extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tutorial_progress and tutorial_configurations tables for standalone tutorial system (raw SQL, safe for legacy code)';
    }

    public function up(Schema $schema): void
    {
        // Tutorial progress tracking - supports multiple sessions per player
        $this->addSql("
            CREATE TABLE IF NOT EXISTS tutorial_progress (
                id INT AUTO_INCREMENT PRIMARY KEY,
                player_id INT NOT NULL,
                tutorial_session_id VARCHAR(36) NOT NULL COMMENT 'UUID for each tutorial attempt',
                current_step INT NOT NULL DEFAULT 0 COMMENT 'Current step number (0-based)',
                total_steps INT NOT NULL COMMENT 'Total steps in this tutorial version',
                completed BOOLEAN DEFAULT FALSE COMMENT 'Has player completed this session',
                started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                completed_at TIMESTAMP NULL,
                tutorial_mode ENUM('first_time', 'replay', 'practice') DEFAULT 'first_time' COMMENT 'Tutorial context',
                tutorial_version VARCHAR(20) NOT NULL DEFAULT '1.0.0' COMMENT 'Tutorial version (allows future updates)',
                xp_earned INT DEFAULT 0 COMMENT 'Total XP earned during this tutorial session',
                data JSON NULL COMMENT 'Extensible field for step-specific data, feature flags, etc.',
                INDEX idx_player_id (player_id),
                INDEX idx_session_id (tutorial_session_id),
                INDEX idx_completed (completed),
                FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Tutorial configuration - stores tutorial step definitions and versioning
        $this->addSql("
            CREATE TABLE IF NOT EXISTS tutorial_configurations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                version VARCHAR(20) NOT NULL COMMENT 'Tutorial version (e.g., 1.0.0, 1.1.0 when buildings added)',
                step_number INT NOT NULL COMMENT 'Step number in sequence',
                step_type VARCHAR(50) NOT NULL COMMENT 'Step type: movement, combat, dialog, pi_investment, building, etc.',
                title VARCHAR(255) NOT NULL COMMENT 'Step title shown to player',
                config JSON NOT NULL COMMENT 'Step configuration: dialogs, validations, XP rewards, etc.',
                xp_reward INT DEFAULT 0 COMMENT 'XP awarded for completing this step',
                is_active BOOLEAN DEFAULT TRUE COMMENT 'Feature flag for enabling/disabling steps',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_version (version),
                INDEX idx_step_number (step_number),
                INDEX idx_active (is_active),
                UNIQUE KEY unique_version_step (version, step_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Add comment about extensibility
        $this->addSql("
            ALTER TABLE tutorial_configurations
            COMMENT = 'Stores tutorial step definitions - easily extensible for new game features (buildings, crafting, etc.)'
        ");
    }

    public function down(Schema $schema): void
    {
        // Rollback: drop tutorial tables (safe - only affects new tables)
        $this->addSql('DROP TABLE IF EXISTS tutorial_progress');
        $this->addSql('DROP TABLE IF EXISTS tutorial_configurations');
    }

    public function isTransactional(): bool
    {
        // Run in transaction for safety
        return true;
    }

    /**
     * IMPORTANT NOTE FOR FUTURE MIGRATIONS:
     *
     * DO NOT use: doctrine:migrations:diff
     * This command compares current database with known entities and tries to drop unknown tables!
     *
     * Instead, ALWAYS create migrations manually like this one:
     * 1. Create new migration file: src/Migrations/VersionYYYYMMDDHHMMSS_Description.php
     * 2. Use raw SQL with CREATE TABLE IF NOT EXISTS
     * 3. Test with --dry-run first
     * 4. Execute migration
     *
     * For adding buildings feature later, create a new migration that adds tutorial steps:
     * INSERT INTO tutorial_configurations (version, step_number, step_type, title, config, xp_reward)
     * VALUES ('1.1.0', 48, 'building', 'Découvrir les bâtiments', '{"dialog": "master_builder", ...}', 15);
     */
}
