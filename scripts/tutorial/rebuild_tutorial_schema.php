<?php
/**
 * Rebuild Tutorial Schema
 *
 * Recreates all tutorial tables for the normalized schema
 * Run from CLI: php scripts/tutorial/rebuild_tutorial_schema.php
 */

// CLI only - bypass authentication
if (php_sapi_name() !== 'cli') {
    die('This script must be run from CLI');
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/db_constants.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/bootstrap.php';

use Classes\Db;

$db = new Db();

echo "=== Rebuilding Tutorial Schema ===\n\n";

$tables = [
    // Core step definitions
    'tutorial_steps' => "
        CREATE TABLE IF NOT EXISTS tutorial_steps (
            id INT AUTO_INCREMENT PRIMARY KEY,
            version VARCHAR(20) NOT NULL DEFAULT '1.0.0',
            step_id VARCHAR(100) NULL COMMENT 'Human-readable identifier',
            next_step VARCHAR(100) NULL COMMENT 'Next step identifier',
            step_number DECIMAL(5,1) NOT NULL COMMENT 'Order in sequence',
            step_type VARCHAR(50) NOT NULL COMMENT 'info, movement, action, combat, etc.',
            title VARCHAR(255) NOT NULL,
            text TEXT NOT NULL,
            xp_reward INT DEFAULT 0,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_version (version),
            INDEX idx_step_number (step_number),
            INDEX idx_step_id (step_id),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    // UI configuration (1:1 with steps)
    'tutorial_step_ui' => "
        CREATE TABLE IF NOT EXISTS tutorial_step_ui (
            id INT AUTO_INCREMENT PRIMARY KEY,
            step_id INT NOT NULL,
            target_selector VARCHAR(500) NULL,
            target_description VARCHAR(255) NULL,
            highlight_selector VARCHAR(500) NULL,
            tooltip_position ENUM('top', 'bottom', 'left', 'right', 'center') DEFAULT 'bottom',
            interaction_mode ENUM('blocking', 'semi-blocking', 'open') DEFAULT 'blocking',
            blocked_click_message TEXT NULL,
            show_delay INT DEFAULT 0,
            auto_advance_delay INT NULL,
            allow_manual_advance BOOLEAN DEFAULT TRUE,
            auto_close_card BOOLEAN DEFAULT FALSE,
            FOREIGN KEY (step_id) REFERENCES tutorial_steps(id) ON DELETE CASCADE,
            UNIQUE KEY unique_step (step_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    // Validation rules (1:1 with steps)
    'tutorial_step_validation' => "
        CREATE TABLE IF NOT EXISTS tutorial_step_validation (
            id INT AUTO_INCREMENT PRIMARY KEY,
            step_id INT NOT NULL,
            requires_validation BOOLEAN DEFAULT FALSE,
            validation_type VARCHAR(50) NULL COMMENT 'any_movement, position, action_used, ui_interaction, etc.',
            validation_hint TEXT NULL,
            target_x INT NULL,
            target_y INT NULL,
            movement_count INT NULL,
            action_name VARCHAR(100) NULL,
            action_charges_required INT DEFAULT 1,
            combat_required BOOLEAN DEFAULT FALSE,
            panel_id VARCHAR(100) NULL,
            element_selector VARCHAR(500) NULL,
            element_clicked VARCHAR(500) NULL,
            dialog_id VARCHAR(100) NULL,
            FOREIGN KEY (step_id) REFERENCES tutorial_steps(id) ON DELETE CASCADE,
            UNIQUE KEY unique_step (step_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    // Prerequisites (1:1 with steps)
    'tutorial_step_prerequisites' => "
        CREATE TABLE IF NOT EXISTS tutorial_step_prerequisites (
            id INT AUTO_INCREMENT PRIMARY KEY,
            step_id INT NOT NULL,
            mvt_required INT NULL,
            pa_required INT NULL,
            auto_restore BOOLEAN DEFAULT TRUE,
            consume_movements BOOLEAN DEFAULT FALSE,
            unlimited_mvt BOOLEAN DEFAULT FALSE,
            unlimited_pa BOOLEAN DEFAULT FALSE,
            spawn_enemy VARCHAR(100) NULL,
            ensure_harvestable_tree_x INT NULL,
            ensure_harvestable_tree_y INT NULL,
            FOREIGN KEY (step_id) REFERENCES tutorial_steps(id) ON DELETE CASCADE,
            UNIQUE KEY unique_step (step_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    // Features (1:1 with steps)
    'tutorial_step_features' => "
        CREATE TABLE IF NOT EXISTS tutorial_step_features (
            id INT AUTO_INCREMENT PRIMARY KEY,
            step_id INT NOT NULL,
            celebration BOOLEAN DEFAULT FALSE,
            show_rewards BOOLEAN DEFAULT FALSE,
            redirect_delay INT NULL,
            FOREIGN KEY (step_id) REFERENCES tutorial_steps(id) ON DELETE CASCADE,
            UNIQUE KEY unique_step (step_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    // Allowed interactions for semi-blocking mode (1:N with steps)
    'tutorial_step_interactions' => "
        CREATE TABLE IF NOT EXISTS tutorial_step_interactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            step_id INT NOT NULL,
            selector VARCHAR(500) NOT NULL,
            description VARCHAR(255) NULL,
            FOREIGN KEY (step_id) REFERENCES tutorial_steps(id) ON DELETE CASCADE,
            INDEX idx_step (step_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    // Additional highlights (1:N with steps)
    'tutorial_step_highlights' => "
        CREATE TABLE IF NOT EXISTS tutorial_step_highlights (
            id INT AUTO_INCREMENT PRIMARY KEY,
            step_id INT NOT NULL,
            selector VARCHAR(500) NOT NULL,
            FOREIGN KEY (step_id) REFERENCES tutorial_steps(id) ON DELETE CASCADE,
            INDEX idx_step (step_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    // Context changes on step completion (1:N with steps)
    'tutorial_step_context_changes' => "
        CREATE TABLE IF NOT EXISTS tutorial_step_context_changes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            step_id INT NOT NULL,
            context_key VARCHAR(100) NOT NULL,
            context_value TEXT NOT NULL,
            FOREIGN KEY (step_id) REFERENCES tutorial_steps(id) ON DELETE CASCADE,
            INDEX idx_step (step_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    // Preparation for next step (1:N with steps)
    'tutorial_step_next_preparation' => "
        CREATE TABLE IF NOT EXISTS tutorial_step_next_preparation (
            id INT AUTO_INCREMENT PRIMARY KEY,
            step_id INT NOT NULL,
            preparation_key VARCHAR(100) NOT NULL,
            preparation_value TEXT NOT NULL,
            FOREIGN KEY (step_id) REFERENCES tutorial_steps(id) ON DELETE CASCADE,
            INDEX idx_step (step_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    // Progress tracking
    'tutorial_progress' => "
        CREATE TABLE IF NOT EXISTS tutorial_progress (
            id INT AUTO_INCREMENT PRIMARY KEY,
            player_id INT NOT NULL,
            tutorial_session_id VARCHAR(36) NOT NULL,
            current_step VARCHAR(100) NULL COMMENT 'Current step_id',
            total_steps INT DEFAULT 0,
            completed BOOLEAN DEFAULT FALSE,
            tutorial_mode ENUM('first_time', 'replay', 'practice') DEFAULT 'first_time',
            tutorial_version VARCHAR(20) NOT NULL DEFAULT '1.0.0',
            xp_earned INT DEFAULT 0,
            data JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL,
            INDEX idx_player_id (player_id),
            INDEX idx_session_id (tutorial_session_id),
            INDEX idx_completed (completed),
            FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    // Tutorial players (temporary characters)
    'tutorial_players' => "
        CREATE TABLE IF NOT EXISTS tutorial_players (
            id INT AUTO_INCREMENT PRIMARY KEY,
            real_player_id INT NOT NULL,
            tutorial_session_id VARCHAR(36) NOT NULL,
            player_id INT NOT NULL COMMENT 'ID in players table',
            name VARCHAR(255) NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL,
            INDEX idx_real_player (real_player_id),
            INDEX idx_session (tutorial_session_id),
            INDEX idx_player (player_id),
            INDEX idx_active (is_active),
            FOREIGN KEY (real_player_id) REFERENCES players(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    // Tutorial enemies
    'tutorial_enemies' => "
        CREATE TABLE IF NOT EXISTS tutorial_enemies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tutorial_session_id VARCHAR(36) NOT NULL,
            enemy_player_id INT NOT NULL COMMENT 'ID in players table (negative)',
            enemy_coords_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_session (tutorial_session_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    // Dialogs
    'tutorial_dialogs' => "
        CREATE TABLE IF NOT EXISTS tutorial_dialogs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            dialog_id VARCHAR(100) NOT NULL,
            npc_name VARCHAR(100) NOT NULL,
            version VARCHAR(20) NOT NULL DEFAULT '1.0.0',
            dialog_data JSON NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_dialog_id (dialog_id),
            INDEX idx_version (version),
            UNIQUE KEY unique_dialog_version (dialog_id, version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    "
];

foreach ($tables as $name => $sql) {
    echo "Creating table: $name ... ";
    try {
        $db->exe($sql);
        echo "OK\n";
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Schema rebuild complete ===\n";
echo "\nNOTE: Tables are empty. You'll need to recreate your tutorial steps.\n";
echo "Use the admin panel at /admin/tutorial.php to add new steps.\n";
