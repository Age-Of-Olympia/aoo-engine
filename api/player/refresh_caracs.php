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

    // Delete cached JSON files to force regeneration from database
    $playerJsonPath = $_SERVER['DOCUMENT_ROOT'] . '/datas/private/players/' . $playerId . '.json';
    $caracsJsonPath = $_SERVER['DOCUMENT_ROOT'] . '/datas/private/players/' . $playerId . '.caracs.json';
    $turnJsonPath = $_SERVER['DOCUMENT_ROOT'] . '/datas/private/players/' . $playerId . '.turn.json';

    $deletedFiles = [];
    if (file_exists($playerJsonPath)) {
        unlink($playerJsonPath);
        $deletedFiles[] = 'player.json';
    }
    if (file_exists($caracsJsonPath)) {
        unlink($caracsJsonPath);
        $deletedFiles[] = 'caracs.json';
    }
    if (file_exists($turnJsonPath)) {
        unlink($turnJsonPath);
        $deletedFiles[] = 'turn.json';
    }

    error_log("[refresh_caracs] Deleted cache files for player {$playerId}: " . implode(', ', $deletedFiles));

    // Load player and regenerate cache from fresh database data
    $player = new Player($playerId);
    $player->get_data();  // Will reload from DB since cache was deleted

    // Force regeneration of caracs and turn cache
    $player->get_caracs();

    error_log("[refresh_caracs] Regenerated cache for player {$playerId}");

    echo json_encode([
        'success' => true,
        'player_id' => $playerId,
        'deleted_cache' => $deletedFiles,
        'message' => 'Character cache refreshed from database'
    ]);

} catch (Exception $e) {
    error_log("[refresh_caracs] Error: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to refresh cache: ' . $e->getMessage()
    ]);
}
