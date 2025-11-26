<?php
/**
 * Refresh player characteristics cache
 *
 * Regenerates the cached player stats (caracs and turn data)
 * Used after tutorial completion to ensure panel shows updated XP/PI
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

    // Load player and regenerate cache
    $player = new Player($playerId);
    $player->get_data();

    // Force regeneration of caracs and turn cache
    $player->get_caracs();

    error_log("[refresh_caracs] Regenerated cache for player {$playerId}");

    echo json_encode([
        'success' => true,
        'player_id' => $playerId,
        'message' => 'Character cache refreshed'
    ]);

} catch (Exception $e) {
    error_log("[refresh_caracs] Error: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to refresh cache: ' . $e->getMessage()
    ]);
}
