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

    // Remove invisibleMode
    $player->end_option('invisibleMode');

    error_log("[Skip Tutorial] Player {$playerId} skipped tutorial, invisibleMode removed");

    echo json_encode([
        'success' => true,
        'message' => 'Tutorial skipped, you can now play normally'
    ]);

} catch (Exception $e) {
    error_log("[Skip Tutorial] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to skip tutorial'
    ]);
}
