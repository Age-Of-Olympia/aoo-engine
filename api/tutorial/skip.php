<?php
/**
 * API Endpoint: Skip Tutorial
 * POST /api/tutorial/skip.php
 *
 * Allows a player to skip the tutorial without completing it
 * Removes invisibleMode so they can play normally
 */

use Classes\Player;

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
    $player = new Player($playerId);

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

    // Initialize player with race actions
    $player->get_data();
    $raceJson = json()->decode('races', $player->data->race);

    // Add all race-specific actions (keep tuto/attaquer for legacy compatibility)
    // Use have_action() to check before adding to avoid duplicates
    // Wrap in try-catch to handle Doctrine errors gracefully
    if ($raceJson && !empty($raceJson->actions)) {
        $addedCount = 0;
        foreach($raceJson->actions as $actionName) {
            try {
                // Only add if player doesn't already have this action
                if (!$player->have_action($actionName)) {
                    $player->add_action($actionName);
                    $addedCount++;
                }
            } catch (\Exception $e) {
                // Log Doctrine errors but continue processing other actions
                error_log("[Skip Tutorial] Warning - could not check/add action '{$actionName}': " . $e->getMessage());
            }
        }
        error_log("[Skip Tutorial] Player {$playerId} initialized with {$addedCount} new actions for race {$player->data->race}");
    }

    // Remove invisibleMode
    $player->end_option('invisibleMode');

    error_log("[Skip Tutorial] Player {$playerId} skipped tutorial, invisibleMode removed");

    // Move player from waiting_room to faction's respawn plan
    $player->getCoords();
    if ($player->coords->plan === 'waiting_room') {
        $factionJson = json()->decode('factions', $player->data->faction);
        $respawnPlan = $factionJson->respawnPlan ?? "olympia";

        $goCoords = (object) array(
            'x' => 0,
            'y' => 0,
            'z' => 0,
            'plan' => $respawnPlan
        );

        $coordsId = \Classes\View::get_free_coords_id_arround($goCoords);

        // Update player's coordinates
        $db = new \Classes\Db();
        $sql = 'UPDATE players SET coords_id = ? WHERE id = ?';
        $db->exe($sql, array($coordsId, $playerId));

        error_log("[Skip Tutorial] Player {$playerId} moved from waiting_room to {$respawnPlan}");
    }

    // Grant skip rewards as consolation for not completing tutorial
    $skipReward = TUTORIAL_SKIP_REWARD;
    $player->put_xp($skipReward['xp']); /* This adds both XP and PI */
    error_log("[Skip Tutorial] Player {$playerId} received skip reward: {$skipReward['xp']} XP/PI");

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
    error_log("[Skip Tutorial] Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to skip tutorial',
        'debug' => $e->getMessage()
    ]);
}
