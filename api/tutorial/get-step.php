<?php
/**
 * API Endpoint: Get Current Tutorial Step
 * GET /api/tutorial/get-step.php?session_id=xxx
 *
 * Gets the current step data for a tutorial session
 */

use App\Tutorial\TutorialManager;
use Classes\Player;

define('NO_LOGIN', true);
require_once(__DIR__ . '/../../config.php');

header('Content-Type: application/json; charset=utf-8');

// Allow both GET and POST
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Check authentication
if (!isset($_SESSION['playerId'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$playerId = $_SESSION['playerId'];

// Get session_id from GET or POST
$sessionId = $_GET['session_id'] ?? null;
if (!$sessionId && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $sessionId = $input['session_id'] ?? null;
}

if (!$sessionId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing session_id']);
    exit;
}

try {
    // Load player
    $player = new Player($playerId);
    $player->get_data();

    // Create tutorial manager
    $manager = new TutorialManager($player);

    // Resume session to get current state
    $resumeResult = $manager->resumeTutorial($sessionId);

    if (!$resumeResult['success']) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Tutorial session not found'
        ]);
        exit;
    }

    // Get current step data
    $currentStep = $resumeResult['current_step'];
    $version = $resumeResult['version'];
    $stepData = $manager->getCurrentStepForClient($currentStep, $version);

    if ($stepData) {
        // Calculate total steps from repository (not stored in DB)
        $stepRepository = new \App\Tutorial\TutorialStepRepository();
        $totalSteps = $stepRepository->getTotalSteps($version);

        echo json_encode([
            'success' => true,
            'session_id' => $sessionId,
            'current_step' => $currentStep,
            'total_steps' => $totalSteps,
            'xp_earned' => $resumeResult['xp_earned'],
            'step_data' => $stepData
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Step not found'
        ]);
    }

} catch (Exception $e) {
    error_log("Tutorial get-step error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'debug' => $e->getMessage()
    ]);
}
