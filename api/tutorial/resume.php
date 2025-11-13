<?php
/**
 * API Endpoint: Resume or Check Tutorial Status
 * GET /api/tutorial/resume.php
 *
 * Checks if player has an active tutorial session and returns it
 */

use App\Tutorial\TutorialManager;
use Classes\Player;
use Classes\Db;

define('NO_LOGIN', true);
require_once(__DIR__ . '/../../config.php');

header('Content-Type: application/json');

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
    $sql = 'SELECT tutorial_session_id, current_step, total_steps, tutorial_mode, tutorial_version, xp_earned
            FROM tutorial_progress
            WHERE player_id = ? AND completed = 0
            ORDER BY started_at DESC
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
            $stepData = $manager->getCurrentStepForClient(
                (int)$session['current_step'],
                $session['tutorial_version']
            );

            echo json_encode([
                'success' => true,
                'has_active_tutorial' => true,
                'session_id' => $session['tutorial_session_id'],
                'current_step' => (int)$session['current_step'],
                'total_steps' => (int)$session['total_steps'],
                'mode' => $session['tutorial_mode'],
                'version' => $session['tutorial_version'],
                'xp_earned' => (int)$session['xp_earned'],
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
