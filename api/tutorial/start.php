<?php
/**
 * API Endpoint: Start Tutorial
 * POST /api/tutorial/start.php
 *
 * Starts a new tutorial session for the logged-in player
 */

use App\Tutorial\TutorialManager;
use App\Tutorial\TutorialFeatureFlag;
use Classes\Player;

// No login check - we'll handle it ourselves
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

// Check if tutorial is enabled for this player
if (!TutorialFeatureFlag::isEnabledForPlayer($playerId)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Tutorial not available',
        'debug' => [
            'player_id' => $playerId,
            'is_enabled_globally' => TutorialFeatureFlag::isEnabled(),
            'test_players' => [1, 2, 3]
        ]
    ]);
    exit;
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$mode = $input['mode'] ?? 'first_time'; // first_time, replay, practice
$version = $input['version'] ?? '1.0.0';

// Validate mode
if (!in_array($mode, ['first_time', 'replay', 'practice'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid mode']);
    exit;
}

try {
    // Load player
    $player = new Player($playerId);
    $player->get_data();

    // Create tutorial manager
    $manager = new TutorialManager($player, $mode);

    // Start tutorial
    $result = $manager->startTutorial($version);

    if ($result['success']) {
        // Store tutorial session in PHP session
        $_SESSION['tutorial_session_id'] = $result['session_id'];
        $_SESSION['tutorial_player_id'] = $result['tutorial_player_id'];
        $_SESSION['in_tutorial'] = true;

        // Force session write to ensure it persists
        session_write_close();
        session_start(); // Restart for any subsequent operations

        // Get first step data
        $firstStepData = $manager->getCurrentStepForClient(0, $version);

        echo json_encode([
            'success' => true,
            'session_id' => $result['session_id'],
            'tutorial_player_id' => $result['tutorial_player_id'],
            'total_steps' => $result['total_steps'],
            'current_step' => $result['current_step'],
            'mode' => $result['mode'],
            'version' => $result['version'],
            'step_data' => $firstStepData,
            'reload_required' => true // Tell frontend to reload page
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to start tutorial'
        ]);
    }

} catch (Exception $e) {
    error_log("Tutorial start error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'debug' => $e->getMessage()
    ]);
}
