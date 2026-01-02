-- Migration: Add tooltip offset columns to tutorial_step_ui
-- Date: 2025-12-07
-- Description: Adds tooltip_offset_x and tooltip_offset_y columns for tooltip positioning

USE aoo4;

-- Add tooltip offset columns if they don't exist
ALTER TABLE tutorial_step_ui
ADD COLUMN IF NOT EXISTS tooltip_offset_x INT DEFAULT 0 COMMENT 'X offset for tooltip' AFTER auto_close_card,
ADD COLUMN IF NOT EXISTS tooltip_offset_y INT DEFAULT 0 COMMENT 'Y offset for tooltip' AFTER tooltip_offset_x;
