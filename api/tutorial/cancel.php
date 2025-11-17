<?php
/**
 * API Endpoint: Cancel Tutorial
 * POST /api/tutorial/cancel.php
 *
 * Cancels the current tutorial session
 */

use App\Tutorial\TutorialHelper;
use App\Tutorial\TutorialMapInstance;
use App\Entity\EntityManagerFactory;
use Classes\Db;

define('NO_LOGIN', true);
require_once(__DIR__ . '/../../config.php');

// Start output buffering to catch any PHP errors/warnings
ob_start();

header('Content-Type: application/json; charset=utf-8');

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
    $em = EntityManagerFactory::getEntityManager();
    $conn = $em->getConnection();

    // Initialize map instance service for cleanup
    $mapInstance = new TutorialMapInstance($conn);

    if ($sessionId) {
        // Clean up tutorial map instance for this session
        try {
            $mapInstance->deleteInstance($sessionId);
            error_log("[Cancel] Deleted tutorial map instance for session {$sessionId}");
        } catch (\Exception $e) {
            error_log("[Cancel] Error deleting map instance: " . $e->getMessage());
        }

        // Clean up tutorial enemy for this session
        try {
            $stmt = $conn->prepare("
                SELECT enemy_player_id, enemy_coords_id
                FROM tutorial_enemies
                WHERE tutorial_session_id = ?
            ");
            $stmt->bindValue(1, $sessionId);
            $result = $stmt->executeQuery();

            $cleanedCount = 0;
            while ($row = $result->fetchAssociative()) {
                $enemyId = $row['enemy_player_id'];
                $coordsId = $row['enemy_coords_id'];

                if ($enemyId) {
                    // Delete related records first to avoid foreign key constraints
                    $conn->delete('players_logs', ['player_id' => $enemyId]);
                    $conn->delete('players_logs', ['target_id' => $enemyId]);
                    $conn->delete('players_actions', ['player_id' => $enemyId]);
                    $conn->delete('players_items', ['player_id' => $enemyId]);
                    $conn->delete('players_effects', ['player_id' => $enemyId]);
                    $conn->delete('players_kills', ['player_id' => $enemyId]);
                    $conn->delete('players_kills', ['target_id' => $enemyId]);

                    $conn->delete('players', ['id' => $enemyId]);
                }
                if ($coordsId) {
                    $conn->delete('coords', ['id' => $coordsId]);
                }
                $cleanedCount++;
            }

            $conn->delete('tutorial_enemies', ['tutorial_session_id' => $sessionId]);

            if ($cleanedCount > 0) {
                error_log("[Cancel] Removed {$cleanedCount} tutorial enemy/enemies for session {$sessionId}");
            }
        } catch (\Exception $e) {
            error_log("[Cancel] Error removing tutorial enemy: " . $e->getMessage());
        }

        // Mark progress as completed
        $sql = 'UPDATE tutorial_progress SET completed = 1, completed_at = NOW() WHERE tutorial_session_id = ?';
        $db->exe($sql, [$sessionId]);

        // Deactivate tutorial player
        $sql2 = 'UPDATE tutorial_players SET is_active = 0, deleted_at = NOW() WHERE tutorial_session_id = ?';
        $db->exe($sql2, [$sessionId]);

        error_log("[Cancel] Marked tutorial as completed and deactivated tutorial player for session {$sessionId}");
    } else {
        // If no session ID provided, cancel any active tutorial for this player
        // First, get session IDs to clean up enemies
        $sql = 'SELECT tutorial_session_id FROM tutorial_progress WHERE player_id = ? AND completed = 0';
        $result = $db->exe($sql, [$_SESSION['playerId']]);

        $sessionIds = [];
        while ($row = $result->fetch_assoc()) {
            $sessionIds[] = $row['tutorial_session_id'];
        }

        // Clean up map instances and enemies for all sessions
        foreach ($sessionIds as $sid) {
            // Delete map instance
            try {
                $mapInstance->deleteInstance($sid);
                error_log("[Cancel] Deleted tutorial map instance for session {$sid}");
            } catch (\Exception $e) {
                error_log("[Cancel] Error deleting map instance for session {$sid}: " . $e->getMessage());
            }

            // Delete enemy
            try {
                $stmt = $conn->prepare("
                    SELECT enemy_player_id, enemy_coords_id
                    FROM tutorial_enemies
                    WHERE tutorial_session_id = ?
                ");
                $stmt->bindValue(1, $sid);
                $result2 = $stmt->executeQuery();

                while ($row = $result2->fetchAssociative()) {
                    $enemyId = $row['enemy_player_id'];
                    $coordsId = $row['enemy_coords_id'];

                    if ($enemyId) {
                        // Delete related records first
                        $conn->delete('players_logs', ['player_id' => $enemyId]);
                        $conn->delete('players_logs', ['target_id' => $enemyId]);
                        $conn->delete('players_actions', ['player_id' => $enemyId]);
                        $conn->delete('players_items', ['player_id' => $enemyId]);
                        $conn->delete('players_effects', ['player_id' => $enemyId]);
                        $conn->delete('players_kills', ['player_id' => $enemyId]);
                        $conn->delete('players_kills', ['target_id' => $enemyId]);

                        $conn->delete('players', ['id' => $enemyId]);
                    }
                    if ($coordsId) {
                        $conn->delete('coords', ['id' => $coordsId]);
                    }
                }

                $conn->delete('tutorial_enemies', ['tutorial_session_id' => $sid]);
            } catch (\Exception $e) {
                error_log("[Cancel] Error removing tutorial enemy for session {$sid}: " . $e->getMessage());
            }
        }

        // Mark progress as completed
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

    // Clean output buffer (remove any PHP warnings/errors)
    ob_clean();

    echo json_encode([
        'success' => true,
        'message' => 'Tutorial cancelled'
    ]);

} catch (Exception $e) {
    error_log("Tutorial cancel error: " . $e->getMessage());
    http_response_code(500);

    // Clean output buffer (remove any PHP warnings/errors)
    ob_clean();

    echo json_encode([
        'success' => false,
        'error' => 'Failed to cancel tutorial'
    ]);
}
