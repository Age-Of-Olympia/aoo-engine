<?php
/**
 * API Endpoint: Cancel Tutorial
 * POST /api/tutorial/cancel.php
 *
 * Cancels the current tutorial session
 */

use Classes\Db;

define('NO_LOGIN', true);
require_once(__DIR__ . '/../../config.php');

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['playerId'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    // Get input from JSON body
    $input = json_decode(file_get_contents('php://input'), true);
    $sessionId = $input['session_id'] ?? null;

    // Clear tutorial session from PHP session
    unset($_SESSION['tutorial_session_id']);
    unset($_SESSION['tutorial_player_id']);
    unset($_SESSION['in_tutorial']);

    // Mark tutorial as completed (cancelled) in database
    $db = new Db();

    if ($sessionId) {
        $sql = 'UPDATE tutorial_progress SET completed = 1, completed_at = NOW() WHERE tutorial_session_id = ?';
        $db->exe($sql, [$sessionId]);
    } else {
        // If no session ID provided, cancel any active tutorial for this player
        $sql = 'UPDATE tutorial_progress SET completed = 1, completed_at = NOW() WHERE player_id = ? AND completed = 0';
        $db->exe($sql, [$_SESSION['playerId']]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Tutorial cancelled'
    ]);

} catch (Exception $e) {
    error_log("Tutorial cancel error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to cancel tutorial'
    ]);
}
