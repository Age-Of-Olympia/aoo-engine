<?php
/**
 * API Endpoint: Cancel Tutorial
 * POST /api/tutorial/cancel.php
 *
 * Cancels the current tutorial session
 */

use App\Tutorial\TutorialHelper;
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
    TutorialHelper::exitTutorialMode();

    error_log("[Cancel] Cleared tutorial session vars for player {$_SESSION['playerId']}");

    // Force session write to persist the cleared vars
    session_write_close();
    session_start();

    // Mark tutorial as completed (cancelled) in database
    $db = new Db();

    if ($sessionId) {
        // Mark progress as completed
        $sql = 'UPDATE tutorial_progress SET completed = 1, completed_at = NOW() WHERE tutorial_session_id = ?';
        $db->exe($sql, [$sessionId]);

        // Deactivate tutorial player
        $sql2 = 'UPDATE tutorial_players SET is_active = 0, deleted_at = NOW() WHERE tutorial_session_id = ?';
        $db->exe($sql2, [$sessionId]);

        error_log("[Cancel] Marked tutorial as completed and deactivated tutorial player for session {$sessionId}");
    } else {
        // If no session ID provided, cancel any active tutorial for this player
        $sql = 'UPDATE tutorial_progress SET completed = 1, completed_at = NOW() WHERE player_id = ? AND completed = 0';
        $db->exe($sql, [$_SESSION['playerId']]);

        // Deactivate all tutorial players for this main player
        $sql2 = 'UPDATE tutorial_players SET is_active = 0, deleted_at = NOW()
                 WHERE tutorial_session_id IN (
                     SELECT tutorial_session_id FROM tutorial_progress
                     WHERE player_id = ? AND completed = 1 AND completed_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
                 )';
        $db->exe($sql2, [$_SESSION['playerId']]);

        error_log("[Cancel] Marked all active tutorials as completed and deactivated tutorial players for player {$_SESSION['playerId']}");
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
