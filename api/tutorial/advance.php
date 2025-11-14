<?php
/**
 * API Endpoint: Advance Tutorial Step
 * POST /api/tutorial/advance.php
 *
 * Advances to the next tutorial step after validating current step
 */

use App\Tutorial\TutorialManager;
use App\Tutorial\TutorialHelper;
use Classes\Player;

define('NO_LOGIN', true);
require_once(__DIR__ . '/../../config.php');

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$sessionId = $input['session_id'] ?? null;
$validationData = $input['validation_data'] ?? [];

// Validate required parameters
if (!$sessionId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing session_id']);
    exit;
}

try {
    // Get active player ID (tutorial player if in tutorial mode, otherwise main player)
    $activePlayerId = TutorialHelper::getActivePlayerId();

    // Load player
    $player = new Player($activePlayerId);
    $player->get_data();

    // Create tutorial manager
    $manager = new TutorialManager($player);

    // Resume session
    $resumeResult = $manager->resumeTutorial($sessionId);

    if (!$resumeResult['success']) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Tutorial session not found'
        ]);
        exit;
    }

    // Advance step
    $result = $manager->advanceStep($validationData);

    if ($result['success']) {
        // Check if tutorial is complete
        if (isset($result['completed']) && $result['completed']) {
            echo json_encode([
                'success' => true,
                'completed' => true,
                'message' => $result['message'] ?? 'Tutorial completed!',
                'xp_earned' => $result['xp_earned'] ?? 0,
                'final_level' => $result['final_level'] ?? 1
            ]);
        } else {
            // Return next step data
            echo json_encode([
                'success' => true,
                'current_step' => $result['current_step'],
                'total_steps' => $result['total_steps'],
                'xp_earned' => $result['xp_earned'],
                'level' => $result['level'],
                'pi' => $result['pi'],
                'step_data' => $result['next_step_data'] ?? null
            ]);
        }
    } else {
        // Validation failed or other error
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Failed to advance',
            'hint' => $result['hint'] ?? null
        ]);
    }

} catch (Exception $e) {
    error_log("Tutorial advance error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'debug' => $e->getMessage()
    ]);
}
