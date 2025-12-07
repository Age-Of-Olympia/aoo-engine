-- Add player_type and display_id columns to players table
-- This is required for the display ID system

-- Add player_type column if it doesn't exist
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = 'players'
    AND column_name = 'player_type');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE players ADD COLUMN player_type VARCHAR(20) NOT NULL DEFAULT ''real'' AFTER id',
    'SELECT ''player_type column already exists'' AS info');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add display_id column if it doesn't exist
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = 'players'
    AND column_name = 'display_id');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE players ADD COLUMN display_id INT NOT NULL DEFAULT 0 AFTER player_type',
    'SELECT ''display_id column already exists'' AS info');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index if it doesn't exist
SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE table_schema = DATABASE()
    AND table_name = 'players'
    AND index_name = 'idx_type_display');

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE players ADD INDEX idx_type_display (player_type, display_id)',
    'SELECT ''idx_type_display index already exists'' AS info');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update player_type for existing players
UPDATE players SET player_type = 'npc' WHERE id < 0 AND player_type = 'real';
UPDATE players SET player_type = 'real' WHERE id > 0 AND id < 10000000 AND player_type != 'tutorial';

-- Assign display_id to existing players
SET @real_seq = 0;
UPDATE players SET display_id = (@real_seq := @real_seq + 1)
WHERE player_type = 'real' ORDER BY id;

SET @npc_seq = 0;
UPDATE players SET display_id = (@npc_seq := @npc_seq + 1)
WHERE player_type = 'npc' ORDER BY id DESC;

SET @tutorial_seq = 0;
UPDATE players SET display_id = (@tutorial_seq := @tutorial_seq + 1)
WHERE player_type = 'tutorial' ORDER BY id;
