-- Complete test database initialization
-- This runs AFTER init_noupdates.sql (alphabetically: 2-init-test-complete.sql)
-- Clones structure from aoo4 and adds minimal test data

-- Ensure test database exists
CREATE DATABASE IF NOT EXISTS aoo4_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Clone structure from aoo4 (without data)
-- This is done using mysqldump commands below, but we need to handle it differently in SQL
-- We'll use CREATE TABLE LIKE syntax for each table

USE aoo4_test;

-- Copy all table structures from aoo4
-- Get list of tables and recreate them
SET @tables = NULL;

-- Drop existing tables if any
DROP TABLE IF EXISTS players_actions;
DROP TABLE IF EXISTS players_items;
DROP TABLE IF EXISTS players_options;
DROP TABLE IF EXISTS players_logs;
DROP TABLE IF EXISTS players_ips;
DROP TABLE IF EXISTS tutorial_progress;
DROP TABLE IF EXISTS tutorial_players;
DROP TABLE IF EXISTS tutorial_enemies;
DROP TABLE IF EXISTS tutorial_step_highlights;
DROP TABLE IF EXISTS tutorial_step_interactions;
DROP TABLE IF EXISTS tutorial_step_context_changes;
DROP TABLE IF EXISTS tutorial_step_next_preparation;
DROP TABLE IF EXISTS tutorial_step_ui;
DROP TABLE IF EXISTS tutorial_step_validation;
DROP TABLE IF EXISTS tutorial_step_prerequisites;
DROP TABLE IF EXISTS tutorial_step_features;
DROP TABLE IF EXISTS tutorial_steps;
DROP TABLE IF EXISTS tutorial_dialogs;
DROP TABLE IF EXISTS players;
DROP TABLE IF EXISTS map_walls;
DROP TABLE IF EXISTS map_items;
DROP TABLE IF EXISTS map_foregrounds;
DROP TABLE IF EXISTS coords;
DROP TABLE IF EXISTS actions;
DROP TABLE IF EXISTS items;
DROP TABLE IF EXISTS races;

-- Create tables by copying structure from aoo4
CREATE TABLE coords LIKE aoo4.coords;
CREATE TABLE races LIKE aoo4.races;
CREATE TABLE items LIKE aoo4.items;
CREATE TABLE actions LIKE aoo4.actions;
CREATE TABLE players LIKE aoo4.players;
CREATE TABLE players_actions LIKE aoo4.players_actions;
CREATE TABLE players_items LIKE aoo4.players_items;
CREATE TABLE players_options LIKE aoo4.players_options;
CREATE TABLE players_logs LIKE aoo4.players_logs;
CREATE TABLE players_ips LIKE aoo4.players_ips;
CREATE TABLE map_walls LIKE aoo4.map_walls;
CREATE TABLE map_items LIKE aoo4.map_items;
CREATE TABLE map_foregrounds LIKE aoo4.map_foregrounds;
CREATE TABLE tutorial_steps LIKE aoo4.tutorial_steps;
CREATE TABLE tutorial_step_ui LIKE aoo4.tutorial_step_ui;
CREATE TABLE tutorial_step_validation LIKE aoo4.tutorial_step_validation;
CREATE TABLE tutorial_step_prerequisites LIKE aoo4.tutorial_step_prerequisites;
CREATE TABLE tutorial_step_features LIKE aoo4.tutorial_step_features;
CREATE TABLE tutorial_step_highlights LIKE aoo4.tutorial_step_highlights;
CREATE TABLE tutorial_step_interactions LIKE aoo4.tutorial_step_interactions;
CREATE TABLE tutorial_step_context_changes LIKE aoo4.tutorial_step_context_changes;
CREATE TABLE tutorial_step_next_preparation LIKE aoo4.tutorial_step_next_preparation;
CREATE TABLE tutorial_progress LIKE aoo4.tutorial_progress;
CREATE TABLE tutorial_players LIKE aoo4.tutorial_players;
CREATE TABLE tutorial_enemies LIKE aoo4.tutorial_enemies;
CREATE TABLE tutorial_dialogs LIKE aoo4.tutorial_dialogs;

-- Copy essential reference data from aoo4
INSERT INTO races SELECT * FROM aoo4.races;
INSERT INTO items SELECT * FROM aoo4.items;
INSERT INTO actions SELECT * FROM aoo4.actions;

-- Copy tutorial configuration
INSERT INTO tutorial_steps SELECT * FROM aoo4.tutorial_steps;
INSERT INTO tutorial_step_ui SELECT * FROM aoo4.tutorial_step_ui;
INSERT INTO tutorial_step_validation SELECT * FROM aoo4.tutorial_step_validation;
INSERT INTO tutorial_step_prerequisites SELECT * FROM aoo4.tutorial_step_prerequisites;
INSERT INTO tutorial_step_features SELECT * FROM aoo4.tutorial_step_features;
INSERT INTO tutorial_step_highlights SELECT * FROM aoo4.tutorial_step_highlights;
INSERT INTO tutorial_step_interactions SELECT * FROM aoo4.tutorial_step_interactions;
INSERT INTO tutorial_step_context_changes SELECT * FROM aoo4.tutorial_step_context_changes;
INSERT INTO tutorial_step_next_preparation SELECT * FROM aoo4.tutorial_step_next_preparation;
INSERT INTO tutorial_dialogs SELECT * FROM aoo4.tutorial_dialogs;

-- Create test coordinates
INSERT INTO coords (id, x, y, z, plan) VALUES
(1, 0, 0, 0, 'gaia'),
(2, 0, 1, 0, 'gaia'),
(3, 1, 0, 0, 'gaia'),
(4, 1, 1, 0, 'gaia'),
(51602, 0, 0, 0, 'tutorial');

-- Create test characters
-- Password for all: "test" (hashed with bcrypt)
INSERT INTO players (id, name, race, psw, mail, plain_mail, nextTurnTime, coords_id, faction, avatar, portrait) VALUES
(100, 'TestPlayerActive', 'nf', '$2y$10$ov5dVltdvc5avtBVC8GJFuUWv9le0kbPCiyjTNNnmd86VHjuXWvwu', 'active@test.com', 'active@test.com', UNIX_TIMESTAMP(), 1, 'nf', 'nf-avatar.png', 'nf-portrait.png'),
(101, 'TestPlayerInactive', 'em', '$2y$10$ov5dVltdvc5avtBVC8GJFuUWv9le0kbPCiyjTNNnmd86VHjuXWvwu', 'inactive@test.com', 'inactive@test.com', UNIX_TIMESTAMP(), 2, 'em', 'em-avatar.png', 'em-portrait.png'),
(102, 'TestTutorialStarted', 'hs', '$2y$10$ov5dVltdvc5avtBVC8GJFuUWv9le0kbPCiyjTNNnmd86VHjuXWvwu', 'started@test.com', 'started@test.com', UNIX_TIMESTAMP(), 51602, 'hs', 'hs-avatar.png', 'hs-portrait.png'),
(103, 'TestAdmin', 'nf', '$2y$10$ov5dVltdvc5avtBVC8GJFuUWv9le0kbPCiyjTNNnmd86VHjuXWvwu', 'admin@test.com', 'admin@test.com', UNIX_TIMESTAMP(), 1, 'nf', 'nf-avatar.png', 'nf-portrait.png');

-- Set admin option for TestAdmin
INSERT INTO players_options (player_id, name) VALUES
(103, 'isAdmin');

-- Add player_type and display_id columns (required for registration and player management)
ALTER TABLE players ADD COLUMN player_type VARCHAR(20) NOT NULL DEFAULT 'real' AFTER id;
ALTER TABLE players ADD COLUMN display_id INT NOT NULL DEFAULT 0 AFTER player_type;
ALTER TABLE players ADD INDEX idx_type_display (player_type, display_id);

-- Set player types for test characters
UPDATE players SET player_type = 'real' WHERE id IN (100, 101, 103);
UPDATE players SET player_type = 'tutorial' WHERE id = 102;

-- Assign display IDs
UPDATE players SET display_id = 1 WHERE id = 100;
UPDATE players SET display_id = 2 WHERE id = 101;
UPDATE players SET display_id = 1 WHERE id = 102;
UPDATE players SET display_id = 3 WHERE id = 103;

-- Grant all privileges
GRANT ALL PRIVILEGES ON aoo4_test.* TO 'run'@'%';
FLUSH PRIVILEGES;
