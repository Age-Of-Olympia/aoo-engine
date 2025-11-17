<?php
/**
 * API Endpoint: Start Tutorial
 * POST /api/tutorial/start.php
 *
 * Starts a new tutorial session for the logged-in player
 */

use App\Tutorial\TutorialManager;
use App\Tutorial\TutorialFeatureFlag;
use App\Tutorial\TutorialHelper;
use App\Tutorial\TutorialMapInstance;
use App\Entity\EntityManagerFactory;
use Classes\Player;

// No login check - we'll handle it ourselves
define('NO_LOGIN', true);
require_once(__DIR__ . '/../../config.php');

header('Content-Type: application/json; charset=utf-8');

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
    // IMPORTANT: Cancel any existing active tutorials before starting a new one
    // This ensures we don't have orphaned tutorial sessions
    $db = new Classes\Db();

    // Get session IDs to clean up map instances
    $sessionsSql = 'SELECT tutorial_session_id FROM tutorial_progress WHERE player_id = ? AND completed = 0';
    $sessionsResult = $db->exe($sessionsSql, [$playerId]);

    $sessionIds = [];
    while ($row = $sessionsResult->fetch_assoc()) {
        $sessionIds[] = $row['tutorial_session_id'];
    }

    // Clean up map instances for cancelled tutorials
    if (!empty($sessionIds)) {
        $em = EntityManagerFactory::getEntityManager();
        $conn = $em->getConnection();
        $mapInstance = new TutorialMapInstance($conn);

        foreach ($sessionIds as $sid) {
            try {
                $mapInstance->deleteInstance($sid);
                error_log("[Start] Deleted tutorial map instance for cancelled session {$sid}");
            } catch (\Exception $e) {
                error_log("[Start] Error deleting map instance for session {$sid}: " . $e->getMessage());
            }
        }
    }

    $cancelSql = 'UPDATE tutorial_progress SET completed = 1, completed_at = NOW()
                  WHERE player_id = ? AND completed = 0';
    $db->exe($cancelSql, [$playerId]);

    $deactivateSql = 'UPDATE tutorial_players SET is_active = 0, deleted_at = NOW()
                      WHERE tutorial_session_id IN (
                          SELECT tutorial_session_id FROM tutorial_progress
                          WHERE player_id = ? AND completed = 1 AND completed_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
                      )';
    $db->exe($deactivateSql, [$playerId]);

    error_log("[Start] Cancelled any existing active tutorials for player {$playerId}");

    // Load player
    $player = new Player($playerId);
    $player->get_data();

    // Create tutorial manager
    $manager = new TutorialManager($player, $mode);

    // Start tutorial
    $result = $manager->startTutorial($version);

    if ($result['success']) {
        // Store tutorial session in PHP session
        TutorialHelper::startTutorialMode($result['session_id'], $result['tutorial_player_id']);

        // Force session write to ensure it persists
        session_write_close();
        session_start(); // Restart for any subsequent operations

        // Get first step data using step_id
        $firstStepData = $manager->getCurrentStepForClientById($result['current_step'], $version);

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
