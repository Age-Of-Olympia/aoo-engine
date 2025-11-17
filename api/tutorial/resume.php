<?php
/**
 * API Endpoint: Resume or Check Tutorial Status
 * GET /api/tutorial/resume.php
 *
 * Checks if player has an active tutorial session and returns it
 */

use App\Tutorial\TutorialManager;
use App\Tutorial\TutorialHelper;
use Classes\Player;
use Classes\Db;

define('NO_LOGIN', true);
require_once(__DIR__ . '/../../config.php');

header('Content-Type: application/json; charset=utf-8');

// Check authentication
if (!isset($_SESSION['playerId'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$playerId = $_SESSION['playerId'];

try {
    $db = new Db();

    // Check for active (non-completed) tutorial session
    $sql = 'SELECT tp.tutorial_session_id, tp.current_step, tp.total_steps, tp.tutorial_mode,
                   tp.tutorial_version, tp.xp_earned, tpl.player_id as tutorial_player_id
            FROM tutorial_progress tp
            LEFT JOIN tutorial_players tpl ON tpl.tutorial_session_id = tp.tutorial_session_id
            WHERE tp.player_id = ? AND tp.completed = 0 AND tpl.is_active = 1
            ORDER BY tp.started_at DESC
            LIMIT 1';

    $result = $db->exe($sql, [$playerId]);

    if ($result && $result->num_rows > 0) {
        $session = $result->fetch_assoc();

        // Load player
        $player = new Player($playerId);
        $player->get_data();

        // Create tutorial manager and get current step
        $manager = new TutorialManager($player);
        $resumeResult = $manager->resumeTutorial($session['tutorial_session_id']);

        if ($resumeResult['success']) {
            // CRITICAL: Set session variables so TutorialHelper works correctly
            // This ensures go.php, index.php, etc. all use the tutorial player
            // This is the ONLY place (besides start.php) that should set these vars
            if ($session['tutorial_player_id']) {
                TutorialHelper::startTutorialMode(
                    $session['tutorial_session_id'],
                    $session['tutorial_player_id']
                );

                error_log("[Resume] Set tutorial session vars: session_id={$session['tutorial_session_id']}, tutorial_player_id={$session['tutorial_player_id']}, main_player={$playerId}");

                // Force session write to persist across requests
                session_write_close();
                session_start(); // Restart for subsequent operations
            }

            // Get step data using tutorial player
            // DO NOT apply prerequisites on resume - only apply when ADVANCING to a step
            // Otherwise movements/resources get reset every time player moves (page reloads)
            $stepData = $manager->getCurrentStepForClientById(
                $session['current_step'],  // Now a step_id (string)
                $session['tutorial_version'],
                false  // applyPrerequisites = false (don't reset resources on resume)
            );

            // Get tutorial player's data for level/pi
            $tutorialPlayer = new Player($session['tutorial_player_id'] ?? $playerId);
            $tutorialPlayer->get_data();

            echo json_encode([
                'success' => true,
                'has_active_tutorial' => true,
                'session_id' => $session['tutorial_session_id'],
                'current_step' => $session['current_step'],  // step_id (string)
                'current_step_number' => $stepData['step_number'] ?? null,  // Step number (for ordering)
                'current_step_position' => $stepData['step_position'] ?? 1,  // Actual position (for display)
                'total_steps' => (int)$session['total_steps'],
                'mode' => $session['tutorial_mode'],
                'version' => $session['tutorial_version'],
                'xp_earned' => (int)$session['xp_earned'],
                'level' => $tutorialPlayer->data->level ?? 1,
                'pi' => $tutorialPlayer->data->pi ?? 0,
                'step_data' => $stepData
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'has_active_tutorial' => false
            ]);
        }
    } else {
        // No active tutorial
        // Check if player has completed tutorial before
        $completedCheck = $db->exe(
            'SELECT COUNT(*) as n FROM tutorial_progress WHERE player_id = ? AND completed = 1',
            [$playerId]
        );

        $hasCompleted = false;
        if ($completedCheck) {
            $row = $completedCheck->fetch_assoc();
            $hasCompleted = $row['n'] > 0;
        }

        echo json_encode([
            'success' => true,
            'has_active_tutorial' => false,
            'has_completed_before' => $hasCompleted
        ]);
    }

} catch (Exception $e) {
    error_log("Tutorial resume error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'debug' => $e->getMessage()
    ]);
}
