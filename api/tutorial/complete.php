<?php
/**
 * API Endpoint: Complete Tutorial (Manual Skip with Completion Credit)
 * POST /api/tutorial/complete.php
 *
 * Marks tutorial as completed even if not all steps finished
 * Used when player clicks "Compléter et jouer" button
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
    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);
    $sessionId = $input['session_id'] ?? null;

    if (!$sessionId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Session ID required']);
        exit;
    }

    $playerId = $_SESSION['playerId'];
    $db = new Db();
    $sessionManager = new TutorialSessionManager($db);

    // Get session details (loadSession is the public API; getSession was a rename that broke this endpoint)
    $session = $sessionManager->loadSession($sessionId);
    if (!$session || $session['player_id'] != $playerId) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Tutorial session not found']);
        exit;
    }

    // Capture first-time status BEFORE marking the session completed —
    // otherwise the current session would itself count as "completed before"
    // and the guard would always skip the reward.
    $hasCompletedBefore = $sessionManager->hasCompletedBefore($playerId);

    // Exit tutorial mode (switch back to main player)
    TutorialHelper::exitTutorialMode();

    // Get main player
    $mainPlayer = PlayerFactory::legacy($playerId);

    // Mark tutorial as completed (idempotent; advance.php already did this when the
    // final step advanced, but we re-assert to keep this endpoint self-contained).
    $finalXp = (int) ($session['xp_earned'] ?? 0);
    $sessionManager->completeSession($sessionId, $finalXp);

    // Award full completion rewards ONLY on first time (not a replay).
    // Also makes the endpoint idempotent: a second POST with the same
    // session_id finds hasCompletedBefore=true and grants nothing.
    $completionReward = TUTORIAL_COMPLETION_REWARD;
    $xpEarned = 0;
    $piEarned = 0;
    if (!$hasCompletedBefore) {
        $mainPlayer->put_xp($completionReward['xp']); /* This adds both XP and PI */
        $xpEarned = $completionReward['xp'];
        $piEarned = $completionReward['pi'];
    } else {
    }

    // Remove invisibleMode from main player
    if ($mainPlayer->have_option('invisibleMode')) {
        $mainPlayer->end_option('invisibleMode');
    }

    // Move player from waiting_room to faction's respawn plan if they're still there
    $mainPlayer->getCoords();
    if ($mainPlayer->coords->plan === 'waiting_room') {
        $mainPlayer->get_data();
        $factionJson = json()->decode('factions', $mainPlayer->data->faction);
        $respawnPlan = $factionJson->respawnPlan ?? "olympia";

        $goCoords = (object) array(
            'x' => 0,
            'y' => 0,
            'z' => 0,
            'plan' => $respawnPlan
        );

        $coordsId = \Classes\View::get_free_coords_id_arround($goCoords);

        // Update player's coordinates
        $sql = 'UPDATE players SET coords_id = ? WHERE id = ?';
        $db->exe($sql, array($coordsId, $playerId));

    }

    // Initialize player with race actions if not already added
    $mainPlayer->get_data();
    $raceJson = json()->decode('races', $mainPlayer->data->race);

    if ($raceJson && !empty($raceJson->actions)) {
        $addedCount = 0;
        foreach($raceJson->actions as $actionName) {
            try {
                if (!$mainPlayer->have_action($actionName)) {
                    $mainPlayer->add_action($actionName);
                    $addedCount++;
                }
            } catch (\Exception $e) {
                error_log("[Complete] Warning - could not check/add action '{$actionName}': " . $e->getMessage());
            }
        }
    }

    // Deactivate any tutorial players.
    // Phase 4.5: link is on players.real_player_id_ref; UPDATE via JOIN.
    $result = $db->exe(
        'UPDATE tutorial_players tp
         JOIN players p ON p.id = tp.player_id
         SET tp.is_active = 0
         WHERE p.real_player_id_ref = ?',
        $playerId
    );

    echo json_encode([
        'success' => true,
        'message' => 'Tutorial marked as completed, you can now play!',
        'xp_earned' => $xpEarned,
        'pi_earned' => $piEarned
    ]);

} catch (Exception $e) {
    error_log("[Complete] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to complete tutorial',
        'debug' => $e->getMessage()
    ]);
}
