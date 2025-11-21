<?php
/**
 * API Endpoint: Cancel Tutorial
 * POST /api/tutorial/cancel.php
 *
 * Cancels the current tutorial session
 */

use App\Tutorial\TutorialHelper;
use App\Tutorial\TutorialSessionManager;
use App\Tutorial\TutorialMapInstance;
use App\Tutorial\TutorialEnemyCleanup;
use App\Entity\EntityManagerFactory;
use Classes\Db;
use Psr\Log\NullLogger;

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

    // Validate session ID format if provided
    if ($sessionId && !TutorialSessionManager::validateSessionIdFormat($sessionId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid session_id format']);
        exit;
    }

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

    // Phase 4: Use TutorialResourceManager for proper cleanup order
    $resourceManager = new \App\Tutorial\TutorialResourceManager();
    $sessionManager = new \App\Tutorial\TutorialSessionManager();

    if ($sessionId) {
        // Cancel specific session with transaction
        try {
            // Begin transaction for atomic cleanup
            $conn->beginTransaction();

            try {
                // Get tutorial player for this session
                $tutorialPlayer = $resourceManager->getTutorialPlayer($sessionId);

                if ($tutorialPlayer) {
                    // Delete all resources in correct order (enemies → players → coords)
                    $resourceManager->deleteTutorialPlayer($tutorialPlayer, $sessionId);
                }

                // Mark session as cancelled
                $sessionManager->cancelSession($sessionId);

                // Commit transaction
                $conn->commit();

                error_log("[Cancel] Cancelled tutorial session {$sessionId}");
            } catch (\Exception $e) {
                // Rollback on error
                $conn->rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            error_log("[Cancel] Error cancelling session {$sessionId}: " . $e->getMessage());
            // Don't throw - return success anyway to let user exit tutorial
        }
    } else {
        // If no session ID provided, cancel any active tutorial for this player
        $playerId = $_SESSION['playerId'];

        try {
            // Begin transaction for atomic cleanup
            $conn->beginTransaction();

            try {
                // Clean up all resources for this player
                $cleanedCount = $resourceManager->cleanupPrevious($playerId);

                // Mark all progress as completed
                $sql = 'UPDATE tutorial_progress SET completed = 1, completed_at = NOW()
                        WHERE player_id = ? AND completed = 0';
                $db->exe($sql, [$playerId]);

                // Commit transaction
                $conn->commit();

                error_log("[Cancel] Cancelled {$cleanedCount} active tutorial(s) for player {$playerId}");
            } catch (\Exception $e) {
                // Rollback on error
                $conn->rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            error_log("[Cancel] Error cancelling tutorials for player {$playerId}: " . $e->getMessage());
            // Don't throw - return success anyway to let user exit tutorial
        }
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
