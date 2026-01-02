-- Migration: Add display_id system for entity type sequencing
-- Date: 2025-11-25
-- Purpose: Allow sequential display IDs within each entity type while using range-based internal IDs

-- Step 1: Add display_id column
ALTER TABLE players
ADD COLUMN display_id INT NOT NULL DEFAULT 0 AFTER player_type,
ADD INDEX idx_type_display (player_type, display_id);

-- Step 2: Fix player_type values for existing data
-- NPCs should be marked as 'npc'
UPDATE players SET player_type = 'npc' WHERE id < 0;

-- Real players should be marked as 'real' (not 'regular')
UPDATE players SET player_type = 'real' WHERE id > 0 AND id < 10000000;

-- Tutorial players already marked correctly

-- Step 3: Assign display_id to existing players
-- Real players: sequential by id
SET @real_seq = 0;
UPDATE players SET display_id = (@real_seq := @real_seq + 1)
WHERE player_type = 'real' ORDER BY id;

-- NPCs: sequential by id (descending, since they're negative)
SET @npc_seq = 0;
UPDATE players SET display_id = (@npc_seq := @npc_seq + 1)
WHERE player_type = 'npc' ORDER BY id DESC;

-- Tutorial players: sequential by id
SET @tutorial_seq = 0;
UPDATE players SET display_id = (@tutorial_seq := @tutorial_seq + 1)
WHERE player_type = 'tutorial' ORDER BY id;

-- Step 4: Clean up tutorial players (keep tutorial steps intact)
-- This removes tutorial test players but preserves tutorial_steps tables
DELETE FROM players WHERE player_type = 'tutorial';
DELETE FROM tutorial_players;
DELETE FROM tutorial_enemies;
DELETE FROM tutorial_progress;

-- Verification queries:
-- SELECT player_type, COUNT(*) as count, MIN(display_id) as min_display, MAX(display_id) as max_display FROM players GROUP BY player_type;
-- SELECT player_type, display_id, id, name FROM players ORDER BY player_type, display_id LIMIT 20;
