-- Reset tutorial progress to beginning (step 0)
-- Run with: mysql -h mariadb-aoo4 -u root -ppasswordRoot aoo_prod_20250821 < scripts/tutorial/reset_to_beginning.sql

USE aoo_prod_20250821;

-- Show current progress
SELECT '=== CURRENT PROGRESS ===' as status;
SELECT player_id, tutorial_session_id, current_step, total_steps, xp_earned
FROM tutorial_progress
WHERE player_id = 7 AND completed = 0;

-- Reset to first step (your_position)
UPDATE tutorial_progress
SET current_step = 'your_position',
    xp_earned = 0
WHERE player_id = 7 AND completed = 0;

-- Show updated progress
SELECT '=== UPDATED PROGRESS ===' as status;
SELECT player_id, tutorial_session_id, current_step, total_steps, xp_earned
FROM tutorial_progress
WHERE player_id = 7 AND completed = 0;

SELECT '=== NEXT: Reload the game page to see step 0 with all fixes ===' as instruction;
