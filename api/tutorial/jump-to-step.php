<?php
/**
 * Jump to Step API - For debugging and testing
 * Allows jumping to any step in the tutorial
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config.php';

use App\Tutorial\TutorialManager;
use App\Tutorial\TutorialHelper;
use Classes\Player;

try {
    // Check authentication
    if (!isset($_SESSION['playerId'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }

    // Get active player (might be tutorial player)
    $activePlayerId = TutorialHelper::getActivePlayerId();
    $player = new Player($activePlayerId);

    // Get request data
    $data = json_decode(file_get_contents('php://input'), true);
    $targetStepId = $data['step_id'] ?? null;
    $targetStepNumber = $data['step_number'] ?? null;

    // Allow either step_id or step_number
    if ($targetStepId === null && $targetStepNumber === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'step_id or step_number is required']);
        exit;
    }

    // If step_id is provided, look up the step_number
    if ($targetStepId !== null) {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT step_number FROM tutorial_steps WHERE step_id = ? AND is_active = 1");
        $stmt->execute([$targetStepId]);
        $result = $stmt->fetch();

        if (!$result) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid step_id: ' . $targetStepId]);
            exit;
        }

        $targetStepNumber = $result['step_number'];
    }

    // Get current tutorial session
    $session = TutorialHelper::getActiveTutorialSession($player->id);

    if (!$session) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No active tutorial session']);
        exit;
    }

    // Create manager and jump to step
    $manager = new TutorialManager($player);
    $success = $manager->jumpToStep($session['tutorial_session_id'], (int)$targetStepNumber);

    if ($success) {
        // Get the new step data
        $resumeResult = $manager->resumeTutorial($session['tutorial_session_id']);

        echo json_encode([
            'success' => true,
            'current_step' => $resumeResult['current_step'] ?? null,
            'step_data' => $resumeResult['step_data'] ?? null
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to jump to step']);
    }

} catch (Exception $e) {
    error_log("Jump to step error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'details' => $e->getMessage()
    ]);
}
