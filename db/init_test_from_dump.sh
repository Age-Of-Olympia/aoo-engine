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

SQL

# Copy tutorial settings only if source table exists
if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "SELECT 1 FROM $SOURCE_DB.tutorial_settings LIMIT 1" 2>/dev/null; then
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$TEST_DB" -e "INSERT IGNORE INTO tutorial_settings SELECT * FROM $SOURCE_DB.tutorial_settings;"
fi

# Schema fixes - ensure critical columns exist
echo "🔧 Applying schema fixes..."
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$TEST_DB" <<SCHEMA_FIXES
-- Add icon column to actions table if missing (required by Action entity)
ALTER TABLE actions
ADD COLUMN IF NOT EXISTS icon VARCHAR(50) NOT NULL DEFAULT '' AFTER name;

-- Add tooltip offset columns if missing (from migration Version20251127000000)
ALTER TABLE tutorial_step_ui
ADD COLUMN IF NOT EXISTS tooltip_offset_x INT DEFAULT 0 COMMENT 'X offset for tooltip' AFTER auto_close_card,
ADD COLUMN IF NOT EXISTS tooltip_offset_y INT DEFAULT 0 COMMENT 'Y offset for tooltip' AFTER tooltip_offset_x;
SCHEMA_FIXES

# Add test data
echo "👥 Creating test characters..."
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$TEST_DB" <<TESTDATA
-- Create test coordinates
INSERT IGNORE INTO coords (id, x, y, z, plan) VALUES
(1, 0, 0, 0, 'gaia'),
(2, 0, 1, 0, 'gaia'),
(3, 1, 0, 0, 'gaia'),
(4, 1, 1, 0, 'gaia'),
-- Tutorial plan coordinates (7x7 grid from -3 to 3)
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

-- Password hashes:
-- "test" = \$2y\$10\$ov5dVltdvc5avtBVC8GJFuUWv9le0kbPCiyjTNNnmd86VHjuXWvwu
-- "testpass" = \$2y\$10\$WZnuZBJr8H5rxFasPScpGe93yFmbbD4NJS9QATVxPs2JBS4Hcwjz6
-- Complex password for workflow test = \$2y\$10\$kw/kfYX8diry1vsFDjJvtelh7Fxl4rQ09q.FDNHBN/qcQtXCdGEqS

-- Create test characters for Cypress tests
INSERT IGNORE INTO players (id, name, race, psw, mail, plain_mail, nextTurnTime, coords_id, faction, avatar, portrait, player_type, display_id) VALUES
-- Workflow test player (ID 7, password: D0Oy7GF6ixBEo#>1RE{rG%9/5rk\\d*wk]**z\`\$pI)
(7, 'TestWorkflowPlayer', 'hs', '\$2y\$10\$kw/kfYX8diry1vsFDjJvtelh7Fxl4rQ09q.FDNHBN/qcQtXCdGEqS', 'workflow@test.com', 'workflow@test.com', UNIX_TIMESTAMP(), 1, 'hs', 'hs-avatar.png', 'hs-portrait.png', 'real', 1),
-- Admin account (password: test)
(100, 'TestAdmin', 'nf', '\$2y\$10\$ov5dVltdvc5avtBVC8GJFuUWv9le0kbPCiyjTNNnmd86VHjuXWvwu', 'admin@test.com', 'admin@test.com', UNIX_TIMESTAMP(), 1, 'nf', 'nf-avatar.png', 'nf-portrait.png', 'real', 2),
-- Fresh player for tutorial tests (password: testpass)
(101, 'TestFreshPlayer', 'em', '\$2y\$10\$WZnuZBJr8H5rxFasPScpGe93yFmbbD4NJS9QATVxPs2JBS4Hcwjz6', 'fresh@test.com', 'fresh@test.com', UNIX_TIMESTAMP(), 1, 'em', 'em-avatar.png', 'em-portrait.png', 'real', 3),
-- Tutorial started player (password: testpass)
(102, 'TestTutorialStarted', 'hs', '\$2y\$10\$WZnuZBJr8H5rxFasPScpGe93yFmbbD4NJS9QATVxPs2JBS4Hcwjz6', 'started@test.com', 'started@test.com', UNIX_TIMESTAMP(), 51602, 'hs', 'hs-avatar.png', 'hs-portrait.png', 'real', 4),
-- Tutorial completed player (password: testpass)
(103, 'TestTutorialCompleted', 'nf', '\$2y\$10\$WZnuZBJr8H5rxFasPScpGe93yFmbbD4NJS9QATVxPs2JBS4Hcwjz6', 'completed@test.com', 'completed@test.com', UNIX_TIMESTAMP(), 1, 'nf', 'nf-avatar.png', 'nf-portrait.png', 'real', 5),
-- Tutorial skipped player (password: testpass)
(104, 'TestTutorialSkipped', 'em', '\$2y\$10\$WZnuZBJr8H5rxFasPScpGe93yFmbbD4NJS9QATVxPs2JBS4Hcwjz6', 'skipped@test.com', 'skipped@test.com', UNIX_TIMESTAMP(), 1, 'em', 'em-avatar.png', 'em-portrait.png', 'real', 6);

-- Set admin option for TestAdmin
INSERT IGNORE INTO players_options (player_id, name) VALUES (100, 'isAdmin');

-- Mark TestTutorialCompleted as having completed the tutorial
INSERT IGNORE INTO tutorial_progress (player_id, tutorial_session_id, current_step, completed, tutorial_mode, tutorial_version, xp_earned)
VALUES (103, UUID(), '29.0', TRUE, 'first_time', '1.0.0', 500);

-- Clear firewall blocks
DELETE FROM players_ips WHERE failed > 0;

-- Populate tutorial template map (boundary walls, resources, NPCs)
-- Boundary walls (North, South, East, West borders of 9x9 grid: -4 to 4)
INSERT IGNORE INTO map_walls (name, coords_id, damages)
SELECT 'mur_pierre', c.id, 0
FROM coords c
WHERE c.plan = 'tutorial' AND c.z = 0
  AND ((c.x = -4 OR c.x = 4) OR (c.y = -4 OR c.y = 4));

-- Gatherable tree for resource tutorial at (0, 1)
INSERT IGNORE INTO map_walls (name, coords_id, damages)
SELECT 'arbre1', c.id, -1
FROM coords c
WHERE c.plan = 'tutorial' AND c.x = 0 AND c.y = 1 AND c.z = 0;

-- Add Gaïa NPC (tutorial guide) at (1, 0)
INSERT IGNORE INTO players (id, player_type, display_id, name, coords_id, race, xp, pi, energie, psw, mail, plain_mail, avatar, portrait, text)
SELECT -3, 'npc', 100, 'Gaïa', c.id, 'nf', 1000, 0, 100, '', '', '', 'img/avatars/nf/default.webp', 'img/portraits/nf/1.jpeg', 'Guide du tutoriel'
FROM coords c
WHERE c.plan = 'tutorial' AND c.x = 1 AND c.y = 0 AND c.z = 0;
TESTDATA

echo "✅ Test database structure initialized successfully!"
