<?php
/**
 * API Endpoint: Skip Tutorial
 * POST /api/tutorial/skip.php
 *
 * Allows a player to skip the tutorial without completing it
 * Removes invisibleMode so they can play normally
 */

use App\Factory\PlayerFactory;
use Classes\Db;
use App\Tutorial\TutorialSessionManager;
use App\Tutorial\TutorialHelper;

define('NO_LOGIN', true);
require_once(__DIR__ . '/../../config.php');

header('Content-Type: application/json; charset=utf-8');

// Check authentication
if (!isset($_SESSION['playerId'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    $playerId = $_SESSION['playerId'];
    $player = PlayerFactory::legacy($playerId);

    // Check if player has invisibleMode
    if (!$player->have_option('invisibleMode')) {
        echo json_encode([
            'success' => false,
            'error' => 'Player is not in invisible mode'
        ]);
        exit;
    }

    // Check if player is admin (admins shouldn't skip this way)
    if ($player->have_option('isAdmin')) {
        echo json_encode([
            'success' => false,
            'error' => 'Admins cannot skip tutorial this way'
        ]);
        exit;
    }

    // Grant rewards ONLY on first time (not a replay). Mirrors cancel.php so
    // re-entering via "Rejouer le tutoriel" cannot be exploited to farm XP.
    $sessionManager = new TutorialSessionManager(new Db());
    $hasCompletedBefore = $sessionManager->hasCompletedBefore($playerId);

    // Drop into the real game: invisibleMode off, teleport out of
    // waiting_room, grant race actions, and first-time reward + starter
    // pack. Shared with complete.php / cancel.php.
    TutorialHelper::finalizeExitToGame($player, TUTORIAL_SKIP_REWARD, $hasCompletedBefore);

    // If redirect parameter is set, redirect to index instead of returning JSON
    if (isset($_GET['redirect']) || isset($_POST['redirect'])) {
        header('Location: /index.php');
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Tutorial skipped, you can now play normally'
    ]);

} catch (Exception $e) {
    error_log("[Skip Tutorial] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to skip tutorial',
        'debug' => $e->getMessage()
    ]);
}
