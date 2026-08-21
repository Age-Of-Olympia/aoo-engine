<?php
/**
 * Reload the active player's caracs from the database.
 *
 * Used after tutorial completion so the panel shows up-to-date XP/PI.
 * Player data is no longer cached in files: this simply reloads the
 * object from the database.
 */

header('Content-Type: application/json; charset=utf-8');

require_once(__DIR__ . '/../../config.php');

use Classes\Player;
use App\Tutorial\TutorialHelper;

try {
    // Get active player ID (tutorial player if in tutorial mode, otherwise main player)
    $playerId = TutorialHelper::getActivePlayerId();

    if (!$playerId) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Not authenticated'
        ]);
        exit;
    }

    $player = new Player($playerId);
    $player->get_data();
    $player->get_caracs();

    echo json_encode([
        'success' => true,
        'player_id' => $playerId,
        'message' => 'Character data reloaded from database'
    ]);

} catch (Exception $e) {
    error_log("[refresh_caracs] Error: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to refresh: ' . $e->getMessage()
    ]);
}
