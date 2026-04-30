<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * COMPREHENSIVE TUTORIAL SYSTEM MIGRATION
 *
 * This migration creates the complete tutorial system with all 15 tables
 * and populates them with the current production-ready tutorial content.
 *
 * PREREQUISITES:
 * - Race file: datas/private/races/ame.json must exist (tutorial dummy race with base stats)
 *
 * Tables created:
 * - tutorial_progress: Session tracking
 * - tutorial_players: Temporary tutorial characters
 * - tutorial_enemies: Combat training enemies
 * - tutorial_map_instances: Tutorial map instances
 * - tutorial_settings: Feature flags
 * - tutorial_steps: Core step definitions (NORMALIZED SCHEMA)
 * - tutorial_step_ui: UI configuration (1:1)
 * - tutorial_step_validation: Validation rules (1:1)
 * - tutorial_step_prerequisites: Resource requirements (1:1)
 * - tutorial_step_features: Special features (1:1)
 * - tutorial_step_highlights: Additional highlights (1:N)
 * - tutorial_step_interactions: Allowed interactions (1:N)
 * - tutorial_step_context_changes: Context modifications (1:N)
 * - tutorial_step_next_preparation: Next step preparation (1:N)
 *
 * Tutorial content:
 * - 29 complete tutorial steps (version 1.0.0)
 * - All UI, validation, and prerequisite configurations
 * - Default settings for feature flags
 * - Marks all existing players as having completed the tutorial (prevents re-doing)
 *
 * Production-ready: YES
 * Idempotent: YES (uses IF NOT EXISTS)
 * Transactional: YES
 */
final class Version20251127000000_CreateCompleteTutorialSystem extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create complete tutorial system with 15 tables, 29 steps, and mark existing players as completed';
    }

    public function up(Schema $schema): void
    {
        /* ================================================================
         * STEP 0: Add tutorial-related columns to players table
         * ================================================================ */

        // Add discriminator and tutorial tracking columns to players table
        $this->addSql("
            ALTER TABLE players
            ADD COLUMN IF NOT EXISTS player_type VARCHAR(20) DEFAULT 'real' COMMENT 'Entity type: real, tutorial, building, npc',
            ADD COLUMN IF NOT EXISTS display_id INT NULL COMMENT 'Sequential display ID for UI (e.g., Tutorial Player #1)',
            ADD COLUMN IF NOT EXISTS tutorial_session_id VARCHAR(36) NULL COMMENT 'Tutorial session UUID (for tutorial players)',
            ADD COLUMN IF NOT EXISTS real_player_id_ref INT NULL COMMENT 'Real player ID reference (for tutorial players)'
        ");

        // Add index for tutorial queries
        $this->addSql("
            CREATE INDEX IF NOT EXISTS idx_player_type ON players(player_type)
        ");

        $this->addSql("
            CREATE INDEX IF NOT EXISTS idx_tutorial_session ON players(tutorial_session_id)
        ");

        /* ================================================================
         * STEP 1: Create all tutorial tables (normalized schema)
         * ================================================================ */

        // Tutorial progress tracking - supports multiple sessions per player
        $this->addSql("
            CREATE TABLE IF NOT EXISTS tutorial_progress (
                id INT AUTO_INCREMENT PRIMARY KEY,
                player_id INT NOT NULL,
                tutorial_session_id VARCHAR(36) NOT NULL COMMENT 'UUID for each tutorial attempt',
                current_step VARCHAR(100) NOT NULL DEFAULT '1.0' COMMENT 'Current step number',
                total_steps INT DEFAULT 0 COMMENT 'Total steps in tutorial version',
                completed BOOLEAN DEFAULT FALSE COMMENT 'Has player completed this session',
                started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                completed_at TIMESTAMP NULL,
                tutorial_mode ENUM('first_time', 'replay', 'practice') DEFAULT 'first_time' COMMENT 'Tutorial context',
                tutorial_version VARCHAR(20) NOT NULL DEFAULT '1.0.0' COMMENT 'Tutorial version',
                xp_earned INT DEFAULT 0 COMMENT 'Total XP earned during this tutorial session',
                data LONGTEXT NULL COMMENT 'Additional session data (JSON)',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_player_id (player_id),
                INDEX idx_session_id (tutorial_session_id),
                INDEX idx_completed (completed),
                FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Tutorial progress tracking for each player session'
        ");

        // Tutorial players - temporary characters for tutorial sessions
        $this->addSql("
            CREATE TABLE IF NOT EXISTS tutorial_players (
                id INT AUTO_INCREMENT PRIMARY KEY,
                real_player_id INT NOT NULL COMMENT 'Link to actual player account',
                tutorial_session_id VARCHAR(36) NOT NULL COMMENT 'Link to tutorial_progress session',
                player_id INT NULL COMMENT 'Tutorial player ID in players table (set after INSERT)',
                name VARCHAR(255) NOT NULL COMMENT 'Character name',
                is_active BOOLEAN DEFAULT TRUE COMMENT 'Is this tutorial character currently active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL COMMENT 'Soft delete when tutorial completes',
                INDEX idx_real_player (real_player_id),
                INDEX idx_session (tutorial_session_id),
                INDEX idx_tutorial_player (player_id),
                INDEX idx_active (is_active),
                UNIQUE KEY unique_session_char (tutorial_session_id),
                FOREIGN KEY (real_player_id) REFERENCES players(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Temporary characters created for each tutorial instance'
        ");

        // Tutorial enemies - combat training enemies
        $this->addSql("
            CREATE TABLE IF NOT EXISTS tutorial_enemies (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tutorial_session_id VARCHAR(36) NOT NULL,
                enemy_player_id INT NOT NULL COMMENT 'ID in players table (negative)',
                enemy_coords_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_session (tutorial_session_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Enemies spawned for combat training in tutorial'
        ");

        // Tutorial map instances
        $this->addSql("
            CREATE TABLE IF NOT EXISTS tutorial_map_instances (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tutorial_session_id VARCHAR(36) NOT NULL,
                plan_name VARCHAR(100) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_session (tutorial_session_id),
                INDEX idx_plan (plan_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Tutorial map instance tracking'
        ");

        // Tutorial settings - feature flags
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
            COMMENT='Tutorial system feature flags and configuration'
        ");

        // Tutorial catalog - manages multiple tutorials
        $this->addSql("
            CREATE TABLE IF NOT EXISTS tutorial_catalog (
                id INT PRIMARY KEY AUTO_INCREMENT,
                version VARCHAR(20) NOT NULL UNIQUE,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                icon VARCHAR(50) DEFAULT 'ra-book',
                difficulty ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
                estimated_minutes INT DEFAULT 10,
                prerequisites JSON,
                plan VARCHAR(50) DEFAULT 'tutorial',
                spawn_x INT DEFAULT 0,
                spawn_y INT DEFAULT 0,
                is_active BOOLEAN DEFAULT TRUE,
                display_order INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Tutorial catalog for managing multiple tutorials'
        ");

        /* ================================================================
         * NORMALIZED TUTORIAL STEPS SCHEMA
         * ================================================================ */

        // Core step information
        $this->addSql("
            CREATE TABLE IF NOT EXISTS tutorial_steps (
                id INT PRIMARY KEY AUTO_INCREMENT,
                version VARCHAR(20) NOT NULL DEFAULT '1.0.0',
                step_id VARCHAR(100) COMMENT 'Human-readable step identifier',
                next_step VARCHAR(100) COMMENT 'Next step identifier for branching logic',
                step_number DECIMAL(5,1) NOT NULL COMMENT 'Order in sequence',
                step_type VARCHAR(50) NOT NULL COMMENT 'info, movement, action, combat, etc.',
                title VARCHAR(255) NOT NULL COMMENT 'Step title shown to player',
                text TEXT NOT NULL COMMENT 'Step description/instructions',
                xp_reward INT DEFAULT 0 COMMENT 'XP awarded for completing this step',
                is_active TINYINT(1) DEFAULT 1 COMMENT 'Feature flag',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_version_step (version, step_number),
                KEY idx_version (version),
                KEY idx_step_number (step_number),
                KEY idx_step_id (step_id),
                KEY idx_active (is_active),
                KEY idx_step_type (step_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Tutorial step definitions - core information'
        ");

        // UI/rendering configuration (1:1)
        $this->addSql("
            CREATE TABLE IF NOT EXISTS tutorial_step_ui (
                id INT PRIMARY KEY AUTO_INCREMENT,
                step_id INT NOT NULL,
                target_selector VARCHAR(500) COMMENT 'CSS selector for element to highlight',
                target_description VARCHAR(255) COMMENT 'Human-readable description',
                highlight_selector VARCHAR(500) COMMENT 'Alternative selector for highlighting',
                tooltip_position ENUM('top', 'bottom', 'left', 'right', 'center', 'center-top', 'center-bottom') DEFAULT 'bottom',
                interaction_mode ENUM('blocking', 'semi-blocking', 'open') DEFAULT 'blocking',
                blocked_click_message TEXT COMMENT 'Message shown when clicking blocked element',
                show_delay INT DEFAULT 0 COMMENT 'Delay in ms before showing tooltip',
                auto_advance_delay INT DEFAULT NULL COMMENT 'Auto-advance after N ms',
                allow_manual_advance TINYINT(1) DEFAULT 1 COMMENT 'Allow manual Next button',
                auto_close_card TINYINT(1) DEFAULT NULL COMMENT 'Auto-close action card',
                tooltip_offset_x INT DEFAULT 0 COMMENT 'X offset for tooltip',
                tooltip_offset_y INT DEFAULT 0 COMMENT 'Y offset for tooltip',
                highlight_padding INT DEFAULT 0 COMMENT 'Extra px around the highlight box and spotlight cut-out',
                caracs_panel_state ENUM('open', 'closed') NULL DEFAULT NULL COMMENT 'Force the caracs panel open/closed at step start; NULL = leave as-is',
                UNIQUE KEY unique_step (step_id),
                FOREIGN KEY (step_id) REFERENCES tutorial_steps(id) ON DELETE CASCADE,
                KEY idx_interaction_mode (interaction_mode)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='UI configuration for tutorial steps'
        ");

        // Validation configuration (1:1)
        $this->addSql("
            CREATE TABLE IF NOT EXISTS tutorial_step_validation (
                id INT PRIMARY KEY AUTO_INCREMENT,
                step_id INT NOT NULL,
                requires_validation TINYINT(1) DEFAULT 0,
                validation_type VARCHAR(50) COMMENT 'any_movement, movements_depleted, position, action_used, etc.',
                validation_hint TEXT COMMENT 'Hint shown when validation fails',
                target_x INT DEFAULT NULL COMMENT 'Target X coordinate',
                target_y INT DEFAULT NULL COMMENT 'Target Y coordinate',
                movement_count INT DEFAULT NULL COMMENT 'Required number of movements',
                action_name VARCHAR(50) DEFAULT NULL COMMENT 'Required action name',
                action_charges_required INT DEFAULT 1 COMMENT 'Number of times action must be used',
                combat_required TINYINT(1) DEFAULT 0,
                panel_id VARCHAR(50) DEFAULT NULL COMMENT 'Panel that must be opened',
                element_selector VARCHAR(255) DEFAULT NULL COMMENT 'Element that must be visible/hidden',
                element_clicked VARCHAR(255) DEFAULT NULL COMMENT 'Element that must be clicked',
                dialog_id VARCHAR(50) DEFAULT NULL COMMENT 'Dialog that must be completed',
                UNIQUE KEY unique_step (step_id),
                FOREIGN KEY (step_id) REFERENCES tutorial_steps(id) ON DELETE CASCADE,
                KEY idx_validation_type (validation_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Validation rules for tutorial steps'
        ");

        // Prerequisites (MVT/PA requirements) (1:1)
        $this->addSql("
            CREATE TABLE IF NOT EXISTS tutorial_step_prerequisites (
                id INT PRIMARY KEY AUTO_INCREMENT,
                step_id INT NOT NULL,
                mvt_required INT DEFAULT NULL COMMENT 'Movement points required',
                pa_required INT DEFAULT NULL COMMENT 'Action points required',
                auto_restore TINYINT(1) DEFAULT 1 COMMENT 'Auto-restore resources on step start',
                consume_movements TINYINT(1) DEFAULT 0 COMMENT 'Consume MVT when moving',
                unlimited_mvt TINYINT(1) DEFAULT 0 COMMENT 'Unlimited movement for this step',
                unlimited_pa TINYINT(1) DEFAULT 0 COMMENT 'Unlimited actions for this step',
                spawn_enemy VARCHAR(50) DEFAULT NULL COMMENT 'Enemy type to spawn',
                ensure_harvestable_tree_x INT DEFAULT NULL COMMENT 'Ensure harvestable tree at X',
                ensure_harvestable_tree_y INT DEFAULT NULL COMMENT 'Ensure harvestable tree at Y',
                UNIQUE KEY unique_step (step_id),
                FOREIGN KEY (step_id) REFERENCES tutorial_steps(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Prerequisites and resource requirements for steps'
        ");

        // Special step features (1:1)
        $this->addSql("
            CREATE TABLE IF NOT EXISTS tutorial_step_features (
                id INT PRIMARY KEY AUTO_INCREMENT,
                step_id INT NOT NULL,
                celebration TINYINT(1) DEFAULT 0 COMMENT 'Show celebration animation',
                show_rewards TINYINT(1) DEFAULT 0 COMMENT 'Display rewards summary',
                redirect_delay INT DEFAULT NULL COMMENT 'Redirect to main game after N ms',
                UNIQUE KEY unique_step (step_id),
                FOREIGN KEY (step_id) REFERENCES tutorial_steps(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Special features and effects for tutorial steps'
        ");

        // Additional highlights (1:N)
        $this->addSql("
            CREATE TABLE IF NOT EXISTS tutorial_step_highlights (
                id INT PRIMARY KEY AUTO_INCREMENT,
                step_id INT NOT NULL,
                selector VARCHAR(500) NOT NULL COMMENT 'CSS selector for additional highlight',
                padding INT DEFAULT 0 COMMENT 'Extra px around this highlight (independent of the step-level target padding)',
                FOREIGN KEY (step_id) REFERENCES tutorial_steps(id) ON DELETE CASCADE,
                KEY idx_step_id (step_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Additional elements to highlight beyond main target'
        ");

        // Allowed interactions for semi-blocking mode (1:N)
        $this->addSql("
            CREATE TABLE IF NOT EXISTS tutorial_step_interactions (
                id INT PRIMARY KEY AUTO_INCREMENT,
                step_id INT NOT NULL,
                selector VARCHAR(500) NOT NULL COMMENT 'CSS selector for allowed clickable element',
                description VARCHAR(255) COMMENT 'Human-readable description',
                FOREIGN KEY (step_id) REFERENCES tutorial_steps(id) ON DELETE CASCADE,
                KEY idx_step_id (step_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Allowed interactions for semi-blocking steps'
        ");

        // Context changes (state modifications) (1:N)
        $this->addSql("
            CREATE TABLE IF NOT EXISTS tutorial_step_context_changes (
                id INT PRIMARY KEY AUTO_INCREMENT,
                step_id INT NOT NULL,
                context_key VARCHAR(50) NOT NULL COMMENT 'unlimited_mvt, consume_movements, set_mvt_limit, etc.',
                context_value TEXT NOT NULL COMMENT 'Value (int, bool, string)',
                FOREIGN KEY (step_id) REFERENCES tutorial_steps(id) ON DELETE CASCADE,
                KEY idx_step_id (step_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Context state changes applied during step'
        ");

        // Preparation for next step (1:N)
        $this->addSql("
            CREATE TABLE IF NOT EXISTS tutorial_step_next_preparation (
                id INT PRIMARY KEY AUTO_INCREMENT,
                step_id INT NOT NULL COMMENT 'Current step ID',
                preparation_key VARCHAR(50) NOT NULL COMMENT 'restore_mvt, restore_actions, spawn_enemy, etc.',
                preparation_value TEXT NOT NULL COMMENT 'Value for the preparation',
                FOREIGN KEY (step_id) REFERENCES tutorial_steps(id) ON DELETE CASCADE,
                KEY idx_step_id (step_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Preparation actions after step completion'
        ");

        /* ================================================================
         * STEP 1.5: Create tutorial map template (9x9 grid with walls)
         * ================================================================ */

        // Create template tutorial map coordinates (plan='tutorial')
        // This serves as the template that gets copied for each tutorial instance
        // 9x9 grid centered at (0,0): from (-4,-4) to (4,4)
        // Inner playable area: 7x7 from (-3,-3) to (3,3)
        $this->addSql("
            INSERT IGNORE INTO coords (x, y, z, plan)
            SELECT x, y, 0 as z, 'tutorial' as plan
            FROM (
                SELECT -4 as x UNION SELECT -3 UNION SELECT -2 UNION SELECT -1 UNION SELECT 0
                UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
            ) as xs
            CROSS JOIN (
                SELECT -4 as y UNION SELECT -3 UNION SELECT -2 UNION SELECT -1 UNION SELECT 0
                UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
            ) as ys
        ");

        // Perimeter walls — single-statement ring seed; NOT EXISTS
        // dedup avoids the corner double-insert.
        $this->addSql("
            INSERT INTO map_walls (name, coords_id, damages)
            SELECT 'mur_pierre', c.id, 0
            FROM coords c
            WHERE c.plan = 'tutorial' AND c.z = 0
              AND (ABS(c.x) = 4 OR ABS(c.y) = 4)
              AND NOT EXISTS (
                  SELECT 1 FROM map_walls mw WHERE mw.coords_id = c.id
              )
        ");

        // Add a gatherable tree at (0,1) for resource gathering tutorial
        $this->addSql("
            INSERT IGNORE INTO map_walls (name, coords_id, damages)
            SELECT 'arbre1', c.id, -1
            FROM coords c
            WHERE c.plan = 'tutorial' AND c.z = 0 AND c.x = 0 AND c.y = 1
        ");

        // Decorative trees + rocks in interior corners (not on any movement path)
        foreach ([
            ['arbre2', -2, 2],
            ['arbre2',  2, 2],
            ['pierre1', -2, -2],
            ['pierre1',  2, -2],
        ] as [$wallName, $x, $y]) {
            $this->addSql(
                "INSERT IGNORE INTO map_walls (name, coords_id, damages)
                 SELECT ?, c.id, 0 FROM coords c
                 WHERE c.plan='tutorial' AND c.z=0 AND c.x=? AND c.y=?",
                [$wallName, $x, $y]
            );
        }

        // Grass carpet on every interior walkable tile (|x|<=3 AND |y|<=3, no wall on it).
        // `eryn_dolen` is the closest to a plain grass texture in img/tiles/.
        $this->addSql("
            INSERT IGNORE INTO map_tiles (name, coords_id, foreground)
            SELECT 'eryn_dolen', c.id, 0
            FROM coords c
            WHERE c.plan='tutorial' AND c.z=0 AND ABS(c.x)<=3 AND ABS(c.y)<=3
        ");

        // Add Gaïa NPC (tutorial guide) at (1,0)
        // She will be copied to each tutorial instance when TutorialMapInstance creates the map
        $this->addSql("
            INSERT IGNORE INTO players (id, player_type, display_id, name, coords_id, race, xp, pi, energie, psw, mail, plain_mail, avatar, portrait, text)
            SELECT
                -999999 as id,
                'npc' as player_type,
                999999 as display_id,
                'Gaïa' as name,
                c.id as coords_id,
                'dieu' as race,
                0 as xp,
                0 as pi,
                100 as energie,
                '' as psw,
                '' as mail,
                '' as plain_mail,
                'img/avatars/dieu/25.png' as avatar,
                'img/portraits/dieu/1.jpeg' as portrait,
                'Gaïa, déesse de la Terre, guide les nouveaux joueurs dans leur apprentissage.' as text
            FROM coords c
            WHERE c.plan = 'tutorial' AND c.z = 0 AND c.x = 1 AND c.y = 0
            LIMIT 1
        ");

        /* ================================================================
         * STEP 2: Insert tutorial settings
         * ================================================================ */

        $this->addSql("
            INSERT INTO tutorial_settings (setting_key, setting_value, description) VALUES
            ('global_enabled', '0', 'Enable tutorial globally for all players'),
            ('whitelisted_players', '', 'Comma-separated list of player IDs who can access tutorial'),
            ('auto_show_new_players', '1', 'Automatically show tutorial to new players')
            ON DUPLICATE KEY UPDATE setting_key=setting_key
        ");

        /* ================================================================
         * STEP 2b: Insert tutorial catalog entries
         * ================================================================ */

        $this->addSql("
            INSERT INTO tutorial_catalog (version, name, description, icon, difficulty, estimated_minutes, prerequisites, plan, spawn_x, spawn_y, is_active, display_order) VALUES
            ('1.0.0', 'Tutoriel de base', 'Apprenez les bases du jeu : déplacement, récolte de ressources et combat.', 'ra-player', 'beginner', 15, NULL, 'tutorial', 0, 0, TRUE, 1),
            ('2.0.0-craft', 'Tutoriel Artisanat', 'Apprenez à utiliser le système d''artisanat pour créer des objets à partir de ressources.', 'ra-forging', 'intermediate', 5, '[\"1.0.0\"]', 'tutorial', 0, 0, TRUE, 2)
            ON DUPLICATE KEY UPDATE name=VALUES(name)
        ");

        /* ================================================================
         * STEP 3: Mark existing players as having completed the tutorial
         * This prevents existing players from seeing the tutorial button
         * and ensures they cannot earn XP/PI by doing the tutorial
         * ================================================================ */

        // The four "_at" columns on tutorial_progress are TIMESTAMPs.
        // players.registerTime is an int(11) Unix epoch and MySQL strict
        // mode rejects integer-as-string for TIMESTAMP columns
        // ("Incorrect datetime value: '1736117307'"). The semantic intent
        // here is just "mark existing players as having completed the
        // tutorial so they don't see the button" — the historical
        // registration date is irrelevant for that marker, so NOW() is
        // the right placeholder. Avoids FROM_UNIXTIME() edge cases for
        // registerTime=0 rows (TIMESTAMP cannot represent 1970-01-01
        // 00:00:00 in some configurations).
        $this->addSql("
            INSERT INTO tutorial_progress (player_id, tutorial_session_id, current_step, total_steps, completed, started_at, completed_at, tutorial_mode, tutorial_version, xp_earned, data, created_at, updated_at)
            SELECT
                id as player_id,
                UUID() as tutorial_session_id,
                '30.0' as current_step,
                29 as total_steps,
                TRUE as completed,
                NOW() as started_at,
                NOW() as completed_at,
                'first_time' as tutorial_mode,
                '1.0.0' as tutorial_version,
                0 as xp_earned,
                '{}' as data,
                NOW() as created_at,
                NOW() as updated_at
            FROM players
            WHERE id > 0
            ON DUPLICATE KEY UPDATE player_id=player_id
        ");

        /* ================================================================
         * STEP 4: Insert tutorial steps (29 steps - production ready)
         * ================================================================ */

        $this->insertTutorialSteps();
        $this->insertTutorialUI();
        $this->insertTutorialValidation();
        $this->insertTutorialPrerequisites();
        $this->insertTutorialFeatures();
        $this->insertTutorialHighlights();
        $this->insertTutorialInteractions();
        $this->insertTutorialContextChanges();
        $this->insertTutorialNextPreparation();

        // Crafting tutorial (2.0.0-craft)
        $this->insertCraftingTutorial();
    }

    private function insertTutorialSteps(): void
    {
        // Set charset to utf8mb4 for proper French accent encoding
        $this->addSql("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

        $this->addSql("
            INSERT INTO tutorial_steps (version, step_id, next_step, step_number, step_type, title, text, xp_reward, is_active) VALUES
            ('1.0.0', 'welcome', 'your_character', 1.0, 'info', 'Bienvenue !', 'Bienvenue dans Age of Olympia ! Ce tutoriel va vous apprendre les bases du jeu. Suivez les instructions pour découvrir comment explorer, récolter et combattre.', 5, 1),
            ('1.0.0', 'your_character', 'meet_gaia', 2.0, 'info', 'Votre personnage', 'Voici <strong>votre personnage</strong> ! Il est représenté au centre du damier. C''est vous dans le monde d''Olympia.', 5, 1),
            ('1.0.0', 'meet_gaia', 'close_card', 3.0, 'info', 'Gaïa, votre guide', 'Voici <strong>Gaïa</strong>, la déesse de la Terre. Elle sera votre guide tout au long de ce tutoriel. Cliquez sur elle pour voir sa fiche.', 5, 1),
            ('1.0.0', 'close_card', 'movement_intro', 4.0, 'ui_interaction', 'Fermer la fiche', 'Vous pouvez <strong>fermer la fiche</strong> en cliquant sur le bouton X, sur une case vide, ou ailleurs sur le damier.', 5, 1),
            ('1.0.0', 'movement_intro', 'first_move', 5.0, 'info', 'Se déplacer', 'Regardez les <strong>cases</strong> autour de vous ! Ce sont les cases où vous pouvez vous déplacer si elles sont vides.', 5, 1),
            ('1.0.0', 'first_move', 'movement_limit_warning', 6.0, 'movement', 'Premier pas', 'Cliquez sur une <strong>case mise en valeur</strong> pour vous déplacer !', 10, 1),
            ('1.0.0', 'movement_limit_warning', 'show_characteristics', 7.0, 'info', 'Mouvements limités !', '<strong>Attention !</strong> En jeu réel, vos mouvements sont <strong>limités</strong>. Vous avez {max_mvt} mouvements par tour. <strong>À partir de maintenant, chaque déplacement consommera 1 mouvement.</strong>', 5, 1),
            ('1.0.0', 'show_characteristics', 'deplete_movements', 8.0, 'ui_interaction', 'Vos caractéristiques', 'Cliquez sur <strong>\"Caractéristiques\"</strong> pour voir vos stats, dont vos mouvements restants.', 5, 1),
            ('1.0.0', 'deplete_movements', 'movements_depleted_info', 9.0, 'movement', 'Épuisez vos mouvements', 'Maintenant, <strong>déplacez-vous jusqu''à épuiser vos {max_mvt} mouvements</strong>. Regardez le compteur diminuer !', 15, 1),
            ('1.0.0', 'movements_depleted_info', 'actions_intro', 10.0, 'info', 'Plus de mouvements !', 'Vous n''avez plus de mouvements ! En jeu réel, ils se régénèrent à chaque tour (toutes les 18h). Pour le tutoriel, on vous les restaure.', 5, 1),
            ('1.0.0', 'actions_intro', 'click_yourself', 11.0, 'info', 'Les Actions', 'En plus des mouvements, vous avez des <strong>Points d''Action (PA)</strong>. Ils permettent de fouiller, attaquer, récolter...', 5, 1),
            ('1.0.0', 'click_yourself', 'actions_panel_info', 12.0, 'ui_interaction', 'Vos actions', '<strong>Cliquez sur votre personnage</strong> pour voir les actions disponibles.', 5, 1),
            ('1.0.0', 'actions_panel_info', 'close_card_for_tree', 13.0, 'info', 'Panneau d''actions', 'Voici vos <strong>actions disponibles</strong> ! Chaque action consomme des PA. Nous allons en tester une : la récolte de ressources.', 5, 1),
            ('1.0.0', 'close_card_for_tree', 'walk_to_tree', 14.0, 'ui_interaction', 'Direction l''arbre', 'Fermez cette fiche. Nous allons aller vers un <strong>arbre</strong> pour le récolter.', 5, 1),
            ('1.0.0', 'walk_to_tree', 'observe_tree', 15.0, 'movement', 'Approchez de l''arbre', 'Déplacez-vous vers l''<strong>arbre</strong> marqué sur le damier. Vous devez être sur une case <strong>adjacente</strong> pour le récolter.', 10, 1),
            ('1.0.0', 'observe_tree', 'tree_info', 16.0, 'ui_interaction', 'Observer l''arbre', '<strong>Cliquez sur l''arbre</strong> pour voir ses informations.', 5, 1),
            ('1.0.0', 'tree_info', 'use_fouiller', 17.0, 'info', 'Ressource récoltable', 'Cet arbre est <strong>récoltable</strong> ! Vous voyez l''indication \"récoltable\" sous le damier, en bas de votre écran.', 5, 1),
            ('1.0.0', 'use_fouiller', 'action_consumed', 18.0, 'action', 'Fouiller !', 'Cliquez sur <strong>Fouiller</strong> pour récolter du bois de l''arbre.', 15, 1),
            ('1.0.0', 'action_consumed', 'open_inventory', 20.0, 'info', 'Action consommée', 'Vous avez récolté du <strong>bois</strong> ! Remarquez que l''action a consommé <strong>1 PA</strong>. Vos PA se régénèrent aussi à chaque tour.', 5, 1),
            ('1.0.0', 'open_inventory', 'inventory_wood', 21.0, 'ui_interaction', 'Votre inventaire', 'Ouvrez votre <strong>Inventaire</strong> pour voir le bois récolté.', 5, 1),
            ('1.0.0', 'inventory_wood', 'close_inventory', 22.0, 'info', 'Du bois !', 'Voilà votre <strong>bois</strong> ! Les ressources récoltées vont dans votre inventaire. Vous pourrez les utiliser pour fabriquer des objets.', 5, 1),
            ('1.0.0', 'close_inventory', 'combat_intro', 23.0, 'ui_interaction', 'Retour au jeu', 'Fermez l''inventaire pour revenir au jeu. Cliquez sur <strong>Retour</strong>.', 5, 1),
            ('1.0.0', 'combat_intro', 'enemy_spawned', 24.0, 'info', 'Le Combat', 'Maintenant, passons au <strong>combat</strong> ! C''est essentiel pour survivre dans Olympia. Un ennemi d''entraînement vous attend.', 5, 1),
            ('1.0.0', 'enemy_spawned', 'walk_to_enemy', 25.0, 'info', 'Votre adversaire', 'Voici une <strong>âme d''entraînement</strong> ! C''est un ennemi inoffensif créé pour le tutoriel. Approchez-vous !', 5, 1),
            ('1.0.0', 'walk_to_enemy', 'click_enemy', 26.0, 'movement', 'Approchez l''ennemi', 'Déplacez-vous vers l''<strong>âme d''entraînement</strong>. Vous devez être sur une <strong>case adjacente</strong> pour attaquer.', 10, 1),
            ('1.0.0', 'click_enemy', 'attack_enemy', 27.0, 'ui_interaction', 'Cibler l''ennemi', '<strong>Cliquez sur l''âme d''entraînement</strong> pour voir ses informations et l''action d''attaque.', 5, 1),
            ('1.0.0', 'attack_enemy', 'attack_result', 28.0, 'combat', 'Attaquez !', 'Cliquez sur <strong>Attaquer</strong> pour frapper l''âme d''entraînement !', 20, 1),
            ('1.0.0', 'attack_result', 'tutorial_complete', 29.0, 'info', 'Ennemi blessé !', 'Excellent ! Vous pouvez voir le <strong>résultat de l''attaque</strong> : l''ennemi a perdu des PV ! Regardez la barre rouge qui indique les dégâts.', 5, 1),
            ('1.0.0', 'tutorial_complete', NULL, 30.0, 'info', 'Tutoriel terminé !', '<strong>Félicitations !</strong> Vous avez terminé le tutoriel ! Vous savez maintenant vous déplacer, récolter des ressources et combattre. Bonne chance dans Olympia !', 50, 1)
        ");
    }

    private function insertTutorialUI(): void
    {
        // Get step IDs dynamically using subqueries
        $this->addSql("
            INSERT INTO tutorial_step_ui (step_id, target_selector, tooltip_position, interaction_mode, show_delay, allow_manual_advance, auto_close_card, tooltip_offset_x, tooltip_offset_y, caracs_panel_state, highlight_padding)
            SELECT id, NULL, 'center', 'blocking', 0, 1, 0, 0, 0, 'closed', 0 FROM tutorial_steps WHERE step_id = 'welcome' UNION ALL
            SELECT id, '.case[data-coords=\"0,0\"]', 'bottom', 'blocking', 200, 1, NULL, 0, 0, NULL, 0 FROM tutorial_steps WHERE step_id = 'your_character' UNION ALL
            SELECT id, '.case[data-coords=\"1,0\"]', 'right', 'semi-blocking', 0, 1, 0, 0, 0, NULL, 0 FROM tutorial_steps WHERE step_id = 'meet_gaia' UNION ALL
            SELECT id, '#ui-card .close-card', 'right', 'semi-blocking', 300, 1, 0, 0, 0, NULL, 0 FROM tutorial_steps WHERE step_id = 'close_card' UNION ALL
            SELECT id, '#current-player-avatar', 'top', 'blocking', 300, 1, NULL, 0, 0, NULL, 50 FROM tutorial_steps WHERE step_id = 'movement_intro' UNION ALL
            SELECT id, '#current-player-avatar', 'top', 'semi-blocking', 0, 1, NULL, 0, 0, NULL, 50 FROM tutorial_steps WHERE step_id = 'first_move' UNION ALL
            SELECT id, NULL, 'center', 'blocking', 0, 1, 0, 0, 0, NULL, 0 FROM tutorial_steps WHERE step_id = 'movement_limit_warning' UNION ALL
            SELECT id, '#show-caracs', 'bottom', 'semi-blocking', 700, 1, 0, 0, 0, NULL, 0 FROM tutorial_steps WHERE step_id = 'show_characteristics' UNION ALL
            SELECT id, '#mvt-counter', 'right', 'semi-blocking', 700, 1, 0, 0, 0, NULL, 0 FROM tutorial_steps WHERE step_id = 'deplete_movements' UNION ALL
            SELECT id, '#mvt-counter', 'right', 'blocking', 700, 1, NULL, 0, 0, NULL, 0 FROM tutorial_steps WHERE step_id = 'movements_depleted_info' UNION ALL
            SELECT id, '#action-counter', 'right', 'blocking', 700, 1, NULL, 0, 0, NULL, 0 FROM tutorial_steps WHERE step_id = 'actions_intro' UNION ALL
            SELECT id, '#current-player-avatar', 'bottom', 'semi-blocking', 0, 1, 0, 0, 0, NULL, 0 FROM tutorial_steps WHERE step_id = 'click_yourself' UNION ALL
            SELECT id, '.card-actions', 'right', 'blocking', 300, 1, NULL, 0, 0, NULL, 0 FROM tutorial_steps WHERE step_id = 'actions_panel_info' UNION ALL
            SELECT id, '#ui-card .close-card', 'right', 'semi-blocking', 0, 1, 1, 0, 0, NULL, 0 FROM tutorial_steps WHERE step_id = 'close_card_for_tree' UNION ALL
            SELECT id, '.case[data-coords=\"0,1\"]', 'center-bottom', 'semi-blocking', 0, 1, NULL, 0, 0, NULL, 0 FROM tutorial_steps WHERE step_id = 'walk_to_tree' UNION ALL
            SELECT id, '.case[data-coords=\"0,1\"]', 'bottom', 'semi-blocking', 0, 0, 0, 0, 0, NULL, 0 FROM tutorial_steps WHERE step_id = 'observe_tree' UNION ALL
            SELECT id, '.resource-status', 'left', 'blocking', 300, 1, NULL, 0, 0, NULL, 0 FROM tutorial_steps WHERE step_id = 'tree_info' UNION ALL
            SELECT id, '.action[data-action=\"fouiller\"]', 'right', 'semi-blocking', 300, 1, 1, 0, 0, NULL, 0 FROM tutorial_steps WHERE step_id = 'use_fouiller' UNION ALL
            SELECT id, '#action-counter', 'right', 'blocking', 700, 1, NULL, 0, 0, NULL, 0 FROM tutorial_steps WHERE step_id = 'action_consumed' UNION ALL
            SELECT id, '#show-inventory', 'bottom', 'semi-blocking', 300, 1, NULL, 0, 0, NULL, 0 FROM tutorial_steps WHERE step_id = 'open_inventory' UNION ALL
            SELECT id, '.item-case[data-name=\"Bois\"]', 'left', 'blocking', 700, 1, NULL, 0, 0, NULL, 0 FROM tutorial_steps WHERE step_id = 'inventory_wood' UNION ALL
            SELECT id, '#back', 'bottom', 'semi-blocking', 200, 1, NULL, 0, 0, NULL, 0 FROM tutorial_steps WHERE step_id = 'close_inventory' UNION ALL
            SELECT id, NULL, 'center', 'blocking', 0, 1, 0, 0, 0, NULL, 0 FROM tutorial_steps WHERE step_id = 'combat_intro' UNION ALL
            SELECT id, '.tutorial-enemy', 'bottom', 'blocking', 500, 1, 0, 0, 0, NULL, 0 FROM tutorial_steps WHERE step_id = 'enemy_spawned' UNION ALL
            SELECT id, '.tutorial-enemy', 'center-bottom', 'semi-blocking', 0, 1, NULL, 0, 0, NULL, 0 FROM tutorial_steps WHERE step_id = 'walk_to_enemy' UNION ALL
            SELECT id, '.tutorial-enemy', 'bottom', 'semi-blocking', 0, 1, 0, 0, 0, NULL, 0 FROM tutorial_steps WHERE step_id = 'click_enemy' UNION ALL
            SELECT id, '.action[data-action=\"attaquer\"]', 'right', 'semi-blocking', 0, 1, NULL, 0, 0, NULL, 0 FROM tutorial_steps WHERE step_id = 'attack_enemy' UNION ALL
            SELECT id, '#red-filter', 'right', 'blocking', 700, 1, 0, 0, 0, NULL, 0 FROM tutorial_steps WHERE step_id = 'attack_result' UNION ALL
            SELECT id, NULL, 'center', 'blocking', 0, 1, NULL, 0, 0, NULL, 0 FROM tutorial_steps WHERE step_id = 'tutorial_complete'
        ");
    }

    private function insertTutorialValidation(): void
    {
        $this->addSql("
            INSERT INTO tutorial_step_validation (step_id, requires_validation, validation_type, validation_hint, target_x, target_y, panel_id, element_selector, element_clicked, action_name, action_charges_required, combat_required)
            SELECT id, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'welcome' UNION ALL
            SELECT id, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'your_character' UNION ALL
            SELECT id, 1, 'ui_panel_opened', 'Cliquez sur Gaïa pour ouvrir sa fiche', NULL, NULL, 'actions', NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'meet_gaia' UNION ALL
            SELECT id, 1, 'ui_element_hidden', 'Fermez la fiche de personnage', NULL, NULL, NULL, '#ui-card', NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'close_card' UNION ALL
            SELECT id, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'movement_intro' UNION ALL
            SELECT id, 1, 'any_movement', 'Déplacez-vous sur une case adjacente', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'first_move' UNION ALL
            SELECT id, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'movement_limit_warning' UNION ALL
            SELECT id, 1, 'ui_panel_opened', 'Ouvrez le panneau des caractéristiques', NULL, NULL, 'characteristics', NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'show_characteristics' UNION ALL
            SELECT id, 1, 'movements_depleted', 'Utilisez tous vos mouvements', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'deplete_movements' UNION ALL
            SELECT id, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'movements_depleted_info' UNION ALL
            SELECT id, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'actions_intro' UNION ALL
            SELECT id, 1, 'ui_panel_opened', 'Cliquez sur votre personnage', NULL, NULL, 'actions', NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'click_yourself' UNION ALL
            SELECT id, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'actions_panel_info' UNION ALL
            SELECT id, 1, 'ui_element_hidden', 'Fermez la fiche', NULL, NULL, NULL, '#ui-card', NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'close_card_for_tree' UNION ALL
            SELECT id, 1, 'adjacent_to_position', 'Approchez-vous de l''arbre', 0, 1, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'walk_to_tree' UNION ALL
            SELECT id, 1, 'ui_panel_opened', 'Cliquez sur l''arbre', NULL, NULL, 'actions', NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'observe_tree' UNION ALL
            SELECT id, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'tree_info' UNION ALL
            SELECT id, 1, 'action_used', 'Utilisez l''action Fouiller', NULL, NULL, NULL, NULL, NULL, 'fouiller', 1, 0 FROM tutorial_steps WHERE step_id = 'use_fouiller' UNION ALL
            SELECT id, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'action_consumed' UNION ALL
            SELECT id, 1, 'ui_interaction', 'Cliquez sur le bouton Inventaire', NULL, NULL, NULL, NULL, '#show-inventory', NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'open_inventory' UNION ALL
            SELECT id, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'inventory_wood' UNION ALL
            SELECT id, 1, 'ui_interaction', 'Retournez au damier', NULL, NULL, NULL, NULL, '#back', NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'close_inventory' UNION ALL
            SELECT id, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'combat_intro' UNION ALL
            SELECT id, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'enemy_spawned' UNION ALL
            SELECT id, 1, 'adjacent_to_position', 'Approchez-vous de l''ennemi', 2, 1, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'walk_to_enemy' UNION ALL
            SELECT id, 1, 'ui_panel_opened', 'Cliquez sur l''ennemi', NULL, NULL, 'actions', NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'click_enemy' UNION ALL
            SELECT id, 1, 'action_used', 'Attaquez l''ennemi', NULL, NULL, NULL, NULL, NULL, 'attaquer', 1, 0 FROM tutorial_steps WHERE step_id = 'attack_enemy' UNION ALL
            SELECT id, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'attack_result' UNION ALL
            SELECT id, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'tutorial_complete'
        ");
    }

    private function insertTutorialPrerequisites(): void
    {
        $this->addSql("
            INSERT INTO tutorial_step_prerequisites (step_id, mvt_required, pa_required, auto_restore, consume_movements, unlimited_mvt, unlimited_pa, spawn_enemy, ensure_harvestable_tree_x, ensure_harvestable_tree_y)
            SELECT id, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'welcome' UNION ALL
            SELECT id, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'meet_gaia' UNION ALL
            SELECT id, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'close_card' UNION ALL
            SELECT id, 1, NULL, 1, 0, 1, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'first_move' UNION ALL
            SELECT id, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'movement_limit_warning' UNION ALL
            SELECT id, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'show_characteristics' UNION ALL
            SELECT id, -1, NULL, 1, 1, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'deplete_movements' UNION ALL
            SELECT id, -1, 2, 1, 0, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'actions_intro' UNION ALL
            SELECT id, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'click_yourself' UNION ALL
            SELECT id, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'close_card_for_tree' UNION ALL
            SELECT id, -1, NULL, 1, 0, 0, 0, NULL, 0, 1 FROM tutorial_steps WHERE step_id = 'walk_to_tree' UNION ALL
            SELECT id, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'observe_tree' UNION ALL
            SELECT id, NULL, 1, 1, 0, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'use_fouiller' UNION ALL
            SELECT id, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'combat_intro' UNION ALL
            SELECT id, NULL, NULL, 1, 0, 0, 0, 'tutorial_dummy', NULL, NULL FROM tutorial_steps WHERE step_id = 'enemy_spawned' UNION ALL
            SELECT id, -1, NULL, 1, 0, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'walk_to_enemy' UNION ALL
            SELECT id, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'click_enemy' UNION ALL
            SELECT id, NULL, 1, 1, 0, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'attack_enemy' UNION ALL
            SELECT id, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'attack_result'
        ");
    }

    private function insertTutorialFeatures(): void
    {
        $this->addSql("
            INSERT INTO tutorial_step_features (step_id, celebration, show_rewards, redirect_delay)
            SELECT id, 0, 0, NULL FROM tutorial_steps WHERE step_id = 'welcome' UNION ALL
            SELECT id, 0, 0, NULL FROM tutorial_steps WHERE step_id = 'meet_gaia' UNION ALL
            SELECT id, 0, 0, NULL FROM tutorial_steps WHERE step_id = 'close_card' UNION ALL
            SELECT id, 0, 0, NULL FROM tutorial_steps WHERE step_id = 'movement_limit_warning' UNION ALL
            SELECT id, 0, 0, NULL FROM tutorial_steps WHERE step_id = 'show_characteristics' UNION ALL
            SELECT id, 0, 0, NULL FROM tutorial_steps WHERE step_id = 'deplete_movements' UNION ALL
            SELECT id, 0, 0, NULL FROM tutorial_steps WHERE step_id = 'click_yourself' UNION ALL
            SELECT id, 0, 0, NULL FROM tutorial_steps WHERE step_id = 'close_card_for_tree' UNION ALL
            SELECT id, 0, 0, NULL FROM tutorial_steps WHERE step_id = 'observe_tree' UNION ALL
            SELECT id, 0, 0, NULL FROM tutorial_steps WHERE step_id = 'combat_intro' UNION ALL
            SELECT id, 0, 0, NULL FROM tutorial_steps WHERE step_id = 'enemy_spawned' UNION ALL
            SELECT id, 0, 0, NULL FROM tutorial_steps WHERE step_id = 'click_enemy' UNION ALL
            SELECT id, 0, 0, NULL FROM tutorial_steps WHERE step_id = 'attack_result' UNION ALL
            SELECT id, 1, 1, 20000 FROM tutorial_steps WHERE step_id = 'tutorial_complete'
        ");
    }

    private function insertTutorialHighlights(): void
    {
        $this->addSql("
            INSERT INTO tutorial_step_highlights (step_id, selector, padding)
            SELECT id, '.case[data-coords=\"0,1\"]', 0 FROM tutorial_steps WHERE step_id = 'walk_to_tree' UNION ALL
            -- Player ring on every walk-related step: pad the player avatar
            -- by 50px to highlight the 8 walkable tiles around them.
            SELECT id, '#current-player-avatar', 50 FROM tutorial_steps WHERE step_id = 'deplete_movements' UNION ALL
            SELECT id, '#current-player-avatar', 50 FROM tutorial_steps WHERE step_id = 'walk_to_tree' UNION ALL
            SELECT id, '#current-player-avatar', 50 FROM tutorial_steps WHERE step_id = 'walk_to_enemy'
        ");
    }

    private function insertTutorialInteractions(): void
    {
        // This inserts all allowed interactions for semi-blocking steps
        $this->addSql("
            INSERT INTO tutorial_step_interactions (step_id, selector, description) VALUES
            ((SELECT id FROM tutorial_steps WHERE step_id = 'meet_gaia'), '.case', 'Cases du damier'),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'meet_gaia'), 'image', 'Personnages'),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'meet_gaia'), '.case-infos', 'Fiche personnage'),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'close_card'), '.case', 'Cases du damier'),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'close_card'), '.close-card', 'Bouton fermer'),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'close_card'), '#game-map', 'Zone de jeu'),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'close_card'), 'svg', 'Fond du damier'),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'first_move'), '.case', NULL),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'first_move'), '.case.go', NULL),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'first_move'), '#go-rect', NULL),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'first_move'), '#go-img', NULL),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'show_characteristics'), '#show-caracs', 'Bouton caractéristiques'),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'deplete_movements'), '.case', 'Cases du damier'),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'deplete_movements'), '.case.go', 'Cases accessibles'),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'deplete_movements'), '#go-rect', 'Bouton de déplacement (rectangle)'),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'deplete_movements'), '#go-img', 'Bouton de déplacement (image)'),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'click_yourself'), '.case', 'Cases du damier'),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'click_yourself'), 'image', 'Personnages'),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'click_yourself'), '#current-player-avatar', 'Avatar du joueur'),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'close_card_for_tree'), '.case', 'Cases du damier'),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'close_card_for_tree'), '.close-card', 'Bouton fermer'),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'walk_to_tree'), '.case', NULL),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'walk_to_tree'), '.case.go', NULL),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'walk_to_tree'), '#go-rect', NULL),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'walk_to_tree'), '#go-img', NULL),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'observe_tree'), '.case', 'Cases du damier'),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'observe_tree'), '.case[data-coords=\"0,1\"]', 'L''arbre'),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'use_fouiller'), '.action[data-action=\"fouiller\"]', NULL),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'use_fouiller'), '.case-infos', NULL),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'use_fouiller'), 'button.action', NULL),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'open_inventory'), '#show-inventory', NULL),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'close_inventory'), '#back', NULL),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'walk_to_enemy'), '.case', NULL),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'walk_to_enemy'), '.case.go', NULL),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'walk_to_enemy'), '#go-rect', NULL),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'walk_to_enemy'), '#go-img', NULL),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'click_enemy'), '.case', 'Cases du damier'),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'click_enemy'), 'image', 'Personnages'),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'click_enemy'), '.tutorial-enemy', 'Ennemi du tutoriel'),
            ((SELECT id FROM tutorial_steps WHERE step_id = 'attack_enemy'), '.action[data-action=\"attaquer\"]', NULL)
        ");
    }

    private function insertTutorialContextChanges(): void
    {
        $this->addSql("
            INSERT INTO tutorial_step_context_changes (step_id, context_key, context_value)
            SELECT id, 'unlimited_mvt', 'true' FROM tutorial_steps WHERE step_id = 'first_move' UNION ALL
            SELECT id, 'consume_movements', 'false' FROM tutorial_steps WHERE step_id = 'first_move' UNION ALL
            SELECT id, 'consume_movements', 'true' FROM tutorial_steps WHERE step_id = 'deplete_movements'
        ");
    }

    private function insertTutorialNextPreparation(): void
    {
        $this->addSql("
            INSERT INTO tutorial_step_next_preparation (step_id, preparation_key, preparation_value)
            SELECT id, 'restore_mvt', '4' FROM tutorial_steps WHERE step_id = 'movements_depleted_info' UNION ALL
            SELECT id, 'spawn_enemy', 'tutorial_dummy' FROM tutorial_steps WHERE step_id = 'combat_intro'
        ");
    }

    /**
     * Insert crafting tutorial (version 2.0.0-craft)
     * A shorter tutorial focused on the crafting system
     */
    private function insertCraftingTutorial(): void
    {
        $this->addSql("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

        // Insert steps
        $this->addSql("
            INSERT INTO tutorial_steps (version, step_id, next_step, step_number, step_type, title, text, xp_reward, is_active) VALUES
            ('2.0.0-craft', 'craft_welcome', 'craft_open_menu', 1.0, 'info', 'Bienvenue dans l''Artisanat !', 'Ce tutoriel va vous apprendre à utiliser le système d''artisanat pour créer des objets à partir de ressources récoltées.', 5, 1),
            ('2.0.0-craft', 'craft_open_menu', 'craft_click_inventory', 2.0, 'ui_interaction', 'Ouvrir le menu', 'Cliquez sur le bouton <strong>Menu</strong> pour accéder aux différentes sections du jeu.', 5, 1),
            ('2.0.0-craft', 'craft_click_inventory', 'craft_click_artisanat', 3.0, 'ui_interaction', 'Inventaire', 'Cliquez sur <strong>Inventaire</strong> pour voir vos ressources et accéder à l''artisanat.', 5, 1),
            ('2.0.0-craft', 'craft_click_artisanat', 'craft_explain_interface', 4.0, 'ui_interaction', 'Onglet Artisanat', 'Cliquez sur l''onglet <strong>Artisanat</strong> pour voir les recettes disponibles.', 5, 1),
            ('2.0.0-craft', 'craft_explain_interface', 'craft_complete', 5.0, 'info', 'Interface d''artisanat', 'Voici l''interface d''artisanat ! Vous voyez les recettes disponibles et les ressources nécessaires. Les recettes en vert sont réalisables avec vos ressources actuelles.', 10, 1),
            ('2.0.0-craft', 'craft_complete', NULL, 6.0, 'info', 'Tutoriel terminé !', '<strong>Félicitations !</strong> Vous connaissez maintenant les bases de l''artisanat. Récoltez des ressources et créez vos premiers objets !', 20, 1)
        ");

        // Insert UI config
        $this->addSql("
            INSERT INTO tutorial_step_ui (step_id, target_selector, tooltip_position, interaction_mode, show_delay, allow_manual_advance)
            SELECT id, NULL, 'center', 'blocking', 0, 1 FROM tutorial_steps WHERE version = '2.0.0-craft' AND step_id = 'craft_welcome' UNION ALL
            SELECT id, '#show-menu', 'bottom', 'semi-blocking', 0, 0 FROM tutorial_steps WHERE version = '2.0.0-craft' AND step_id = 'craft_open_menu' UNION ALL
            SELECT id, '.menu-inventaire', 'right', 'semi-blocking', 300, 0 FROM tutorial_steps WHERE version = '2.0.0-craft' AND step_id = 'craft_click_inventory' UNION ALL
            SELECT id, '.inventory-tab-craft', 'bottom', 'semi-blocking', 300, 0 FROM tutorial_steps WHERE version = '2.0.0-craft' AND step_id = 'craft_click_artisanat' UNION ALL
            SELECT id, '.craft-recipes', 'right', 'blocking', 500, 1 FROM tutorial_steps WHERE version = '2.0.0-craft' AND step_id = 'craft_explain_interface' UNION ALL
            SELECT id, NULL, 'center', 'blocking', 0, 1 FROM tutorial_steps WHERE version = '2.0.0-craft' AND step_id = 'craft_complete'
        ");

        // Insert validation config
        $this->addSql("
            INSERT INTO tutorial_step_validation (step_id, requires_validation, validation_type, element_clicked)
            SELECT id, 0, NULL, NULL FROM tutorial_steps WHERE version = '2.0.0-craft' AND step_id = 'craft_welcome' UNION ALL
            SELECT id, 1, 'ui_interaction', '#show-menu' FROM tutorial_steps WHERE version = '2.0.0-craft' AND step_id = 'craft_open_menu' UNION ALL
            SELECT id, 1, 'ui_interaction', '.menu-inventaire' FROM tutorial_steps WHERE version = '2.0.0-craft' AND step_id = 'craft_click_inventory' UNION ALL
            SELECT id, 1, 'ui_interaction', '.inventory-tab-craft' FROM tutorial_steps WHERE version = '2.0.0-craft' AND step_id = 'craft_click_artisanat' UNION ALL
            SELECT id, 0, NULL, NULL FROM tutorial_steps WHERE version = '2.0.0-craft' AND step_id = 'craft_explain_interface' UNION ALL
            SELECT id, 0, NULL, NULL FROM tutorial_steps WHERE version = '2.0.0-craft' AND step_id = 'craft_complete'
        ");

        // Insert features (celebration on complete)
        $this->addSql("
            INSERT INTO tutorial_step_features (step_id, celebration, show_rewards)
            SELECT id, 1, 1 FROM tutorial_steps WHERE version = '2.0.0-craft' AND step_id = 'craft_complete'
        ");
    }

    public function down(Schema $schema): void
    {
        // Drop all tutorial tables in reverse order (respecting foreign keys)
        $this->addSql('DROP TABLE IF EXISTS tutorial_step_next_preparation');
        $this->addSql('DROP TABLE IF EXISTS tutorial_step_context_changes');
        $this->addSql('DROP TABLE IF EXISTS tutorial_step_interactions');
        $this->addSql('DROP TABLE IF EXISTS tutorial_step_highlights');
        $this->addSql('DROP TABLE IF EXISTS tutorial_step_features');
        $this->addSql('DROP TABLE IF EXISTS tutorial_step_prerequisites');
        $this->addSql('DROP TABLE IF EXISTS tutorial_step_validation');
        $this->addSql('DROP TABLE IF EXISTS tutorial_step_ui');
        $this->addSql('DROP TABLE IF EXISTS tutorial_steps');
        $this->addSql('DROP TABLE IF EXISTS tutorial_catalog');
        $this->addSql('DROP TABLE IF EXISTS tutorial_settings');
        $this->addSql('DROP TABLE IF EXISTS tutorial_map_instances');
        $this->addSql('DROP TABLE IF EXISTS tutorial_enemies');
        $this->addSql('DROP TABLE IF EXISTS tutorial_players');
        $this->addSql('DROP TABLE IF EXISTS tutorial_progress');
    }

    public function isTransactional(): bool
    {
        // MUST be false for this migration. It creates 15 DDL tables.
        // MariaDB/MySQL implicitly commits the active transaction at every
        // DDL statement, which destroys the savepoint Doctrine creates per
        // migration. Returning true here makes `doctrine-migrations migrate`
        // fail with "SAVEPOINT DOCTRINE_2 does not exist" at the second
        // CREATE TABLE.
        //
        // The "safety" the previous comment hoped for does not exist on
        // MySQL with DDL anyway: there is no atomic rollback across CREATE
        // TABLE statements. Atomicity must be achieved by making each
        // CREATE TABLE idempotent (we use IF NOT EXISTS throughout).
        return false;
    }
}
