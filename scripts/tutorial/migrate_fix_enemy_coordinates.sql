-- Migration: Fix tutorial enemy step coordinates
-- Date: 2025-11-20
-- Description: Corrects the coordinates for move_to_enemy and inspect_enemy steps
--              to point to the actual enemy spawn position (2, 1) instead of (0, -1)

-- Fix move_to_enemy step coordinates and UI selector
UPDATE tutorial_step_validation tsv
JOIN tutorial_steps ts ON tsv.step_id = ts.id
SET tsv.target_x = 2, tsv.target_y = 1
WHERE ts.step_id = 'move_to_enemy';

UPDATE tutorial_step_ui tsu
JOIN tutorial_steps ts ON tsu.step_id = ts.id
SET tsu.target_selector = '.case[data-coords="2,1"]'
WHERE ts.step_id = 'move_to_enemy';

-- Fix inspect_enemy step coordinates
UPDATE tutorial_step_validation tsv
JOIN tutorial_steps ts ON tsv.step_id = ts.id
SET tsv.target_x = 2, tsv.target_y = 1
WHERE ts.step_id = 'inspect_enemy';
