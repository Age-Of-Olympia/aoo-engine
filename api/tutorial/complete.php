<?php
/**
 * API Endpoint: Complete Tutorial (Manual Skip with Completion Credit)
 * POST /api/tutorial/complete.php
 *
 * Marks tutorial as completed even if not all steps finished
 * Used when player clicks "Compléter et jouer" button
 */

use Classes\Player;
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

    // Get session details
    $session = $sessionManager->getSession($sessionId);
    if (!$session || $session['player_id'] != $playerId) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Tutorial session not found']);
        exit;
    }

    // Exit tutorial mode (switch back to main player)
    TutorialHelper::exitTutorialMode();

    // Get main player
    $mainPlayer = new Player($playerId);

    // Mark tutorial as completed
    $sessionManager->markCompleted($sessionId);
    error_log("[Complete] Player {$playerId} manually completed tutorial via 'Compléter' button");

    // Award full completion rewards (they chose to complete, not skip)
    $completionReward = TUTORIAL_COMPLETION_REWARD;
    $mainPlayer->put_xp($completionReward['xp']); /* This adds both XP and PI */
    error_log("[Complete] Player {$playerId} received completion reward: {$completionReward['xp']} XP/PI");

    // Remove invisibleMode from main player
    if ($mainPlayer->have_option('invisibleMode')) {
        $mainPlayer->end_option('invisibleMode');
        error_log("[Complete] Removed invisibleMode from player {$playerId}");
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

        error_log("[Complete] Player {$playerId} moved from waiting_room to {$respawnPlan}");
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
        error_log("[Complete] Player {$playerId} initialized with {$addedCount} new actions for race {$mainPlayer->data->race}");
    }

    // Deactivate any tutorial players
    $result = $db->exe(
        'UPDATE tutorial_players SET is_active = 0 WHERE real_player_id = ?',
        $playerId
    );
    error_log("[Complete] Deactivated tutorial players for real_player_id={$playerId}");

    echo json_encode([
        'success' => true,
        'message' => 'Tutorial marked as completed, you can now play!',
        'xp_earned' => $completionReward['xp'],
        'pi_earned' => $completionReward['pi']
    ]);

} catch (Exception $e) {
    error_log("[Complete] Error: " . $e->getMessage());
    error_log("[Complete] Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to complete tutorial',
        'debug' => $e->getMessage()
    ]);
}
