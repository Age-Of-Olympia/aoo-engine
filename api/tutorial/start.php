<?php
/**
 * API Endpoint: Start Tutorial
 * POST /api/tutorial/start.php
 *
 * Starts a new tutorial session for the logged-in player
 */

use App\Factory\PlayerFactory;
use App\Tutorial\TutorialCatalogService;
use App\Tutorial\TutorialManager;
use App\Tutorial\TutorialFeatureFlag;
use App\Tutorial\TutorialHelper;
use App\Tutorial\TutorialMapInstance;
use App\Entity\EntityManagerFactory;

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
        'error' => 'Tutorial not available'
    ]);
    exit;
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$mode = $input['mode'] ?? 'first_time'; // first_time, replay, practice
$version = $input['version'] ?? '1.0.0';
// Optional race override — null means "use the real player's race" (default).
$raceOverride = isset($input['race']) && $input['race'] !== '' ? (string) $input['race'] : null;

// Validate mode
if (!in_array($mode, ['first_time', 'replay', 'practice'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid mode']);
    exit;
}

// Validate race override up front so we fail with 400 rather than leaking
// TutorialPlayerFactory's InvalidArgumentException as a 500.
if ($raceOverride !== null && !in_array(strtolower($raceOverride), RACES_EXT, true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => "Invalid race '{$raceOverride}'",
        'valid'   => array_values(RACES_EXT),
    ]);
    exit;
}

// Validate catalog entry (existence + active). Player-facing start never
// launches an unknown or disabled scenario — only admin/tutorial-launcher.php
// can bypass this.
$catalog = (new TutorialCatalogService())->getByVersion($version);
if (!$catalog) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => "Unknown tutorial version '{$version}'"]);
    exit;
}
if (!(int) ($catalog['is_active'] ?? 0)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Tutorial not available']);
    exit;
}

try {
    // Phase 4: Cleanup is now handled by TutorialManager.startTutorial()
    // via TutorialResourceManager.cleanupPrevious() in correct order
    // No need for manual cleanup here

    // Load player
    $player = PlayerFactory::legacy($playerId);
    $player->get_data();

    // Create tutorial manager
    $manager = new TutorialManager($player, $mode);

    // Start tutorial (race override is null by default → uses real player's race)
    $result = $manager->startTutorial($version, $raceOverride);

    if ($result['success']) {
        // Store tutorial session in PHP session
        TutorialHelper::startTutorialMode($result['session_id'], $result['tutorial_player_id']);

        // Clear auto-start flag now that tutorial has started
        unset($_SESSION['auto_start_tutorial']);

        // Force session write to ensure it persists
        session_write_close();
        session_start(); // Restart for any subsequent operations

        // Get first step data using step_id
        $firstStepData = $manager->getCurrentStepForClientById($result['current_step'], $version);

        // Calculate total steps from repository (not stored in DB)
        $stepRepository = new \App\Tutorial\TutorialStepRepository();
        $totalSteps = $stepRepository->getTotalSteps($version);

        echo json_encode([
            'success' => true,
            'session_id' => $result['session_id'],
            'tutorial_player_id' => $result['tutorial_player_id'],
            'total_steps' => $totalSteps,
            'current_step' => $result['current_step'],  // step_id (string)
            'current_step_position' => $firstStepData['step_position'] ?? 1,  // display position (int)
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
    $chain = [];
    for ($cur = $e; $cur !== null; $cur = $cur->getPrevious()) {
        $chain[] = sprintf(
            '%s: %s @ %s:%d',
            get_class($cur),
            $cur->getMessage(),
            $cur->getFile(),
            $cur->getLine()
        );
    }
    error_log("Tutorial start error: " . implode(' | <- ', $chain));
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'debug' => $e->getMessage(),
        'chain' => $chain,
    ]);
}
