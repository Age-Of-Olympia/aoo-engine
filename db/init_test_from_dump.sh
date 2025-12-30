#!/bin/bash
# Initialize test database by cloning structure from aoo4
# This ensures test database always matches main database schema

set -e

DB_HOST="mariadb-aoo4"
DB_USER="root"
DB_PASS="passwordRoot"
SOURCE_DB="aoo4"
TEST_DB="aoo4_test"

echo "🔄 Initializing test database from main database structure..."

# Wait for aoo4 to be ready (it's created by init_noupdates.sql first)
until mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "USE $SOURCE_DB" 2>/dev/null; do
    echo "⏳ Waiting for $SOURCE_DB to be ready..."
    sleep 2
done

# Create test database if it doesn't exist
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS $TEST_DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Clone structure from aoo4 (no data, no triggers)
echo "📋 Cloning table structures from $SOURCE_DB..."
mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" \
    --no-data \
    --skip-triggers \
    --skip-add-drop-table \
    "$SOURCE_DB" | \
    sed "s/CREATE TABLE/CREATE TABLE IF NOT EXISTS/g" | \
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$TEST_DB"

# Copy essential reference data
echo "📦 Copying essential data..."
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$TEST_DB" <<SQL
-- Copy reference data from source database
INSERT IGNORE INTO races SELECT * FROM $SOURCE_DB.races;
INSERT IGNORE INTO items SELECT * FROM $SOURCE_DB.items;
INSERT IGNORE INTO actions SELECT * FROM $SOURCE_DB.actions;
INSERT IGNORE INTO action_outcomes SELECT * FROM $SOURCE_DB.action_outcomes;
INSERT IGNORE INTO outcome_instructions SELECT * FROM $SOURCE_DB.outcome_instructions;
INSERT IGNORE INTO action_conditions SELECT * FROM $SOURCE_DB.action_conditions;
INSERT IGNORE INTO race_actions SELECT * FROM $SOURCE_DB.race_actions;

-- Copy tutorial configuration
INSERT IGNORE INTO tutorial_steps SELECT * FROM $SOURCE_DB.tutorial_steps;
INSERT IGNORE INTO tutorial_step_ui SELECT * FROM $SOURCE_DB.tutorial_step_ui;
INSERT IGNORE INTO tutorial_step_validation SELECT * FROM $SOURCE_DB.tutorial_step_validation;
INSERT IGNORE INTO tutorial_step_prerequisites SELECT * FROM $SOURCE_DB.tutorial_step_prerequisites;
INSERT IGNORE INTO tutorial_step_features SELECT * FROM $SOURCE_DB.tutorial_step_features;
INSERT IGNORE INTO tutorial_step_highlights SELECT * FROM $SOURCE_DB.tutorial_step_highlights;
INSERT IGNORE INTO tutorial_step_interactions SELECT * FROM $SOURCE_DB.tutorial_step_interactions;
INSERT IGNORE INTO tutorial_step_context_changes SELECT * FROM $SOURCE_DB.tutorial_step_context_changes;
INSERT IGNORE INTO tutorial_step_next_preparation SELECT * FROM $SOURCE_DB.tutorial_step_next_preparation;
INSERT IGNORE INTO tutorial_dialogs SELECT * FROM $SOURCE_DB.tutorial_dialogs;

-- Create and copy tutorial catalog if it exists
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
);

SQL

# Copy tutorial catalog only if source table exists
if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "SELECT 1 FROM $SOURCE_DB.tutorial_catalog LIMIT 1" 2>/dev/null; then
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$TEST_DB" -e "INSERT IGNORE INTO tutorial_catalog SELECT * FROM $SOURCE_DB.tutorial_catalog;"
fi

cat << 'SQL' | mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$TEST_DB"
-- Placeholder for additional SQL if needed
SELECT 1;
SQL

# Copy tutorial settings only if source table exists
if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "SELECT 1 FROM $SOURCE_DB.tutorial_settings LIMIT 1" 2>/dev/null; then
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$TEST_DB" -e "INSERT IGNORE INTO tutorial_settings SELECT * FROM $SOURCE_DB.tutorial_settings;"
fi

# Schema fixes - ensure critical columns exist
echo "🔧 Applying schema fixes..."
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$TEST_DB" --default-character-set=utf8mb4 <<SCHEMA_FIXES
-- Add icon column to actions table if missing (required by Action entity)
ALTER TABLE actions
ADD COLUMN IF NOT EXISTS icon VARCHAR(50) NOT NULL DEFAULT '' AFTER name;

-- Add tooltip offset columns if missing (from migration Version20251127000000)
ALTER TABLE tutorial_step_ui
ADD COLUMN IF NOT EXISTS tooltip_offset_x INT DEFAULT 0 COMMENT 'X offset for tooltip' AFTER auto_close_card,
ADD COLUMN IF NOT EXISTS tooltip_offset_y INT DEFAULT 0 COMMENT 'Y offset for tooltip' AFTER tooltip_offset_x;

-- Fix missing/invalid action icons (restore original values from init_noupdates.sql)
UPDATE actions SET icon = 'ra-crossed-swords' WHERE name = 'melee';
UPDATE actions SET icon = 'ra-arrow-cluster' WHERE name = 'distance';
UPDATE actions SET icon = 'ra-boot-stomp' WHERE name = 'courir';
UPDATE actions SET icon = 'ra-crowned-heart' WHERE name = 'prier';
UPDATE actions SET icon = 'ra-nuclear' WHERE name = 'vol_a_la_tire';
UPDATE actions SET icon = 'ra-bear-trap' WHERE name = 'esquive/cle_de_bras';
UPDATE actions SET icon = 'ra-archery-target' WHERE name = 'entrainement';

-- Fix tutorial step text to use {max_mvt} placeholder for race-adaptive movement count
UPDATE tutorial_steps SET
    text = '<strong>Attention !</strong> En jeu réel, vos mouvements sont <strong>limités</strong>. Vous avez {max_mvt} mouvements par tour. Chaque déplacement en consomme 1.'
WHERE step_id = 'movement_limit_warning';

UPDATE tutorial_steps SET
    text = 'Maintenant, <strong>déplacez-vous jusqu''à épuiser vos {max_mvt} mouvements</strong>. Regardez le compteur diminuer !'
WHERE step_id = 'deplete_movements';

-- Use -1 for mvt_required to indicate "use race max" instead of hardcoded value
UPDATE tutorial_step_prerequisites p
JOIN tutorial_steps s ON s.id = p.step_id
SET p.mvt_required = -1
WHERE s.step_id IN ('deplete_movements', 'actions_intro', 'walk_to_tree', 'walk_to_enemy')
AND p.mvt_required = 4;
SCHEMA_FIXES

# Add test data
echo "👥 Creating test characters..."
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$TEST_DB" --default-character-set=utf8mb4 <<TESTDATA
-- Create test coordinates
INSERT IGNORE INTO coords (id, x, y, z, plan) VALUES
(1, 0, 0, 0, 'gaia'),
(2, 0, 1, 0, 'gaia'),
(3, 1, 0, 0, 'gaia'),
(4, 1, 1, 0, 'gaia'),
-- Tutorial plan coordinates (11x11 grid from -5 to 5 for walls, -4 to 4 playable)
(51602, 0, 0, 0, 'tutorial'),
(51603, 1, 0, 0, 'tutorial'), (51604, -1, 0, 0, 'tutorial'),
(51605, 0, 1, 0, 'tutorial'), (51606, 0, -1, 0, 'tutorial'),
(51607, 1, 1, 0, 'tutorial'), (51608, -1, -1, 0, 'tutorial'),
(51609, 1, -1, 0, 'tutorial'), (51610, -1, 1, 0, 'tutorial'),
(51611, 2, 0, 0, 'tutorial'), (51612, -2, 0, 0, 'tutorial'),
(51613, 0, 2, 0, 'tutorial'), (51614, 0, -2, 0, 'tutorial'),
(51615, 2, 1, 0, 'tutorial'), (51616, 2, -1, 0, 'tutorial'),
(51617, -2, 1, 0, 'tutorial'), (51618, -2, -1, 0, 'tutorial'),
(51619, 1, 2, 0, 'tutorial'), (51620, -1, 2, 0, 'tutorial'),
(51621, 1, -2, 0, 'tutorial'), (51622, -1, -2, 0, 'tutorial'),
(51623, 2, 2, 0, 'tutorial'), (51624, -2, -2, 0, 'tutorial'),
(51625, 2, -2, 0, 'tutorial'), (51626, -2, 2, 0, 'tutorial'),
(51627, 3, 0, 0, 'tutorial'), (51628, -3, 0, 0, 'tutorial'),
(51629, 0, 3, 0, 'tutorial'), (51630, 0, -3, 0, 'tutorial'),
(51631, 3, 1, 0, 'tutorial'), (51632, -3, 1, 0, 'tutorial'),
(51633, 3, -1, 0, 'tutorial'), (51634, -3, -1, 0, 'tutorial'),
(51635, 1, 3, 0, 'tutorial'), (51636, -1, 3, 0, 'tutorial'),
(51637, 1, -3, 0, 'tutorial'), (51638, -1, -3, 0, 'tutorial'),
(51639, 3, 2, 0, 'tutorial'), (51640, -3, 2, 0, 'tutorial'),
(51641, 3, -2, 0, 'tutorial'), (51642, -3, -2, 0, 'tutorial'),
(51643, 2, 3, 0, 'tutorial'), (51644, -2, 3, 0, 'tutorial'),
(51645, 2, -3, 0, 'tutorial'), (51646, -2, -3, 0, 'tutorial'),
(51647, 3, 3, 0, 'tutorial'), (51648, -3, -3, 0, 'tutorial'),
(51649, 3, -3, 0, 'tutorial'), (51650, -3, 3, 0, 'tutorial'),
-- Add ±4 and ±5 coords for walls and extra space
(51651, 4, 0, 0, 'tutorial'), (51652, -4, 0, 0, 'tutorial'),
(51653, 0, 4, 0, 'tutorial'), (51654, 0, -4, 0, 'tutorial'),
(51655, 5, 0, 0, 'tutorial'), (51656, -5, 0, 0, 'tutorial'),
(51657, 0, 5, 0, 'tutorial'), (51658, 0, -5, 0, 'tutorial'),
(51659, 4, 1, 0, 'tutorial'), (51660, -4, 1, 0, 'tutorial'),
(51661, 4, -1, 0, 'tutorial'), (51662, -4, -1, 0, 'tutorial'),
(51663, 1, 4, 0, 'tutorial'), (51664, -1, 4, 0, 'tutorial'),
(51665, 1, -4, 0, 'tutorial'), (51666, -1, -4, 0, 'tutorial'),
(51667, 5, 1, 0, 'tutorial'), (51668, -5, 1, 0, 'tutorial'),
(51669, 5, -1, 0, 'tutorial'), (51670, -5, -1, 0, 'tutorial'),
(51671, 1, 5, 0, 'tutorial'), (51672, -1, 5, 0, 'tutorial'),
(51673, 1, -5, 0, 'tutorial'), (51674, -1, -5, 0, 'tutorial'),
(51675, 2, 4, 0, 'tutorial'), (51676, -2, 4, 0, 'tutorial'),
(51677, 2, -4, 0, 'tutorial'), (51678, -2, -4, 0, 'tutorial'),
(51679, 4, 2, 0, 'tutorial'), (51680, -4, 2, 0, 'tutorial'),
(51681, 4, -2, 0, 'tutorial'), (51682, -4, -2, 0, 'tutorial'),
(51683, 2, 5, 0, 'tutorial'), (51684, -2, 5, 0, 'tutorial'),
(51685, 2, -5, 0, 'tutorial'), (51686, -2, -5, 0, 'tutorial'),
(51687, 5, 2, 0, 'tutorial'), (51688, -5, 2, 0, 'tutorial'),
(51689, 5, -2, 0, 'tutorial'), (51690, -5, -2, 0, 'tutorial'),
(51691, 3, 4, 0, 'tutorial'), (51692, -3, 4, 0, 'tutorial'),
(51693, 3, -4, 0, 'tutorial'), (51694, -3, -4, 0, 'tutorial'),
(51695, 4, 3, 0, 'tutorial'), (51696, -4, 3, 0, 'tutorial'),
(51697, 4, -3, 0, 'tutorial'), (51698, -4, -3, 0, 'tutorial'),
(51699, 3, 5, 0, 'tutorial'), (51700, -3, 5, 0, 'tutorial'),
(51701, 3, -5, 0, 'tutorial'), (51702, -3, -5, 0, 'tutorial'),
(51703, 5, 3, 0, 'tutorial'), (51704, -5, 3, 0, 'tutorial'),
(51705, 5, -3, 0, 'tutorial'), (51706, -5, -3, 0, 'tutorial'),
(51707, 4, 4, 0, 'tutorial'), (51708, -4, -4, 0, 'tutorial'),
(51709, 4, -4, 0, 'tutorial'), (51710, -4, 4, 0, 'tutorial'),
(51711, 5, 4, 0, 'tutorial'), (51712, -5, 4, 0, 'tutorial'),
(51713, 5, -4, 0, 'tutorial'), (51714, -5, -4, 0, 'tutorial'),
(51715, 4, 5, 0, 'tutorial'), (51716, -4, 5, 0, 'tutorial'),
(51717, 4, -5, 0, 'tutorial'), (51718, -4, -5, 0, 'tutorial'),
(51719, 5, 5, 0, 'tutorial'), (51720, -5, -5, 0, 'tutorial'),
(51721, 5, -5, 0, 'tutorial'), (51722, -5, 5, 0, 'tutorial'),
-- Waiting room coordinates for new tutorial system
(60000, 0, 0, 0, 'waiting_room'),
(60001, 1, 0, 0, 'waiting_room'),
(60002, -1, 0, 0, 'waiting_room'),
(60003, 0, 1, 0, 'waiting_room'),
(60004, 0, -1, 0, 'waiting_room'),
(60005, 1, 1, 0, 'waiting_room'),
(60006, 1, -1, 0, 'waiting_room'),
(60007, -1, 1, 0, 'waiting_room'),
(60008, -1, -1, 0, 'waiting_room');

-- Password hashes (regenerate with: php -r "echo password_hash('test', PASSWORD_DEFAULT);")
-- "test" = password_hash('test', PASSWORD_DEFAULT) - used for TestAdmin
-- "testpass" = password_hash('testpass', PASSWORD_DEFAULT) - used for other test players
-- Complex password for workflow test (legacy)

-- Create test characters for Cypress tests
-- Valid races: nain, geant, olympien, hs, elfe, lutin, humain, dieu, ame
INSERT INTO players (id, name, race, psw, mail, plain_mail, nextTurnTime, coords_id, faction, avatar, portrait, player_type, display_id) VALUES
-- Workflow test player (ID 7, password: D0Oy7GF6ixBEo#>1RE{rG%9/5rk\\d*wk]**z\`\$pI)
(7, 'TestWorkflowPlayer', 'humain', '\$2y\$10\$kw/kfYX8diry1vsFDjJvtelh7Fxl4rQ09q.FDNHBN/qcQtXCdGEqS', 'workflow@test.com', 'workflow@test.com', UNIX_TIMESTAMP(), 1, 'zeus', 'img/avatars/humain/1.png', 'img/portraits/humain/1.jpeg', 'real', 1),
-- Admin account (password: test)
(100, 'TestAdmin', 'nain', '\$2y\$10\$N3yzGhEWxAilNdXIA42dKOwqk1CHgSlAhV/DGQJVaVv4RAo1xKfnO', 'admin@test.com', 'admin@test.com', UNIX_TIMESTAMP(), 1, 'hephaestos', 'img/avatars/nain/1.png', 'img/portraits/nain/1.jpeg', 'real', 2),
-- Fresh player for tutorial tests (password: testpass)
(101, 'TestFreshPlayer', 'elfe', '\$2y\$10\$LJiJdZasGC56wvjiHyIl7./pqnAaoPQcFqRM6PYXGJ745wQX33jN2', 'fresh@test.com', 'fresh@test.com', UNIX_TIMESTAMP(), 1, 'artemis', 'img/avatars/elfe/1.png', 'img/portraits/elfe/1.jpeg', 'real', 3),
-- Tutorial started player (password: testpass)
(102, 'TestTutorialStarted', 'humain', '\$2y\$10\$LJiJdZasGC56wvjiHyIl7./pqnAaoPQcFqRM6PYXGJ745wQX33jN2', 'started@test.com', 'started@test.com', UNIX_TIMESTAMP(), 51602, 'zeus', 'img/avatars/humain/1.png', 'img/portraits/humain/1.jpeg', 'real', 4),
-- Tutorial completed player (password: testpass)
(103, 'TestTutorialCompleted', 'nain', '\$2y\$10\$LJiJdZasGC56wvjiHyIl7./pqnAaoPQcFqRM6PYXGJ745wQX33jN2', 'completed@test.com', 'completed@test.com', UNIX_TIMESTAMP(), 1, 'hephaestos', 'img/avatars/nain/1.png', 'img/portraits/nain/1.jpeg', 'real', 5),
-- Tutorial skipped player (password: testpass)
(104, 'TestTutorialSkipped', 'elfe', '\$2y\$10\$LJiJdZasGC56wvjiHyIl7./pqnAaoPQcFqRM6PYXGJ745wQX33jN2', 'skipped@test.com', 'skipped@test.com', UNIX_TIMESTAMP(), 1, 'artemis', 'img/avatars/elfe/1.png', 'img/portraits/elfe/1.jpeg', 'real', 6)
ON DUPLICATE KEY UPDATE psw = VALUES(psw), race = VALUES(race);

-- Set admin option for TestAdmin
INSERT IGNORE INTO players_options (player_id, name) VALUES (100, 'isAdmin');

-- Mark TestTutorialCompleted as having completed the tutorial
INSERT IGNORE INTO tutorial_progress (player_id, tutorial_session_id, current_step, completed, tutorial_mode, tutorial_version, xp_earned)
VALUES (103, UUID(), '29.0', TRUE, 'first_time', '1.0.0', 500);

-- Clear firewall blocks
DELETE FROM players_ips WHERE failed > 0;

-- Populate tutorial template map (boundary walls, resources, NPCs)
-- Boundary walls (North, South, East, West borders of 11x11 grid: -5 to 5)
INSERT IGNORE INTO map_walls (name, coords_id, damages)
SELECT 'mur_pierre', c.id, 0
FROM coords c
WHERE c.plan = 'tutorial' AND c.z = 0
  AND ((c.x = -5 OR c.x = 5) OR (c.y = -5 OR c.y = 5));

-- Gatherable tree for resource tutorial at (0, 1)
INSERT IGNORE INTO map_walls (name, coords_id, damages)
SELECT 'arbre1', c.id, -1
FROM coords c
WHERE c.plan = 'tutorial' AND c.x = 0 AND c.y = 1 AND c.z = 0;

-- Add Gaïa NPC (tutorial guide) at (1, 0)
INSERT IGNORE INTO players (id, player_type, display_id, name, coords_id, race, xp, pi, energie, psw, mail, plain_mail, avatar, portrait, text)
SELECT -999999, 'npc', 999999, 'Gaïa', c.id, 'dieu', 0, 0, 100, '', '', '', 'img/avatars/dieu/1.png', 'img/portraits/dieu/1.jpeg', 'Gaïa, déesse de la Terre, guide les nouveaux joueurs dans leur apprentissage.'
FROM coords c
WHERE c.plan = 'tutorial' AND c.x = 1 AND c.y = 0 AND c.z = 0;
TESTDATA

echo "✅ Test database structure initialized successfully!"
