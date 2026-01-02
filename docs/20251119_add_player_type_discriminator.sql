-- Migration: Add player_type discriminator for Single Table Inheritance
-- Date: 2025-11-19
-- Purpose: Isolate tutorial players from real players using discriminator pattern

-- Step 1: Add discriminator columns to players table
ALTER TABLE players
ADD COLUMN player_type VARCHAR(20) NOT NULL DEFAULT 'real' AFTER id,
ADD COLUMN tutorial_session_id VARCHAR(36) NULL AFTER player_type,
ADD COLUMN real_player_id_ref INT(11) NULL AFTER tutorial_session_id,
ADD INDEX idx_player_type (player_type),
ADD INDEX idx_tutorial_session (tutorial_session_id);

-- Step 2: Mark existing players with correct player_type
-- NPCs (negative IDs)
UPDATE players SET player_type = 'npc' WHERE id < 0;

-- Tutorial players (those referenced in tutorial_players table)
UPDATE players p
INNER JOIN tutorial_players tp ON p.id = tp.player_id
SET p.player_type = 'tutorial',
    p.tutorial_session_id = tp.tutorial_session_id,
    p.real_player_id_ref = tp.real_player_id
WHERE tp.player_id IS NOT NULL;

-- All others remain 'real' (already set as default)

-- Verification query (run after migration):
-- SELECT player_type, COUNT(*) as count FROM players GROUP BY player_type;
-- Expected: real (actual players), npc (enemies), tutorial (test characters)
