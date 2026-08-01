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
use App\Factory\EntityManagerFactory;
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

    $playerId = $_SESSION['playerId'];

    // Mark tutorial as completed (cancelled) in database
    $db = new Db();
    $em = EntityManagerFactory::getEntityManager();
    $conn = $em->getConnection();

    // Use TutorialResourceManager for proper cleanup order
    $resourceManager = new \App\Tutorial\TutorialResourceManager();
    $sessionManager = new \App\Tutorial\TutorialSessionManager();

    // IDOR guard: when a specific session_id is supplied, it is
    // caller-controlled. Reject cross-player attempts before doing any
    // cleanup. The $sessionId=null branch below is scoped to the
    // caller's own playerId via WHERE player_id = ?, which is safe.
    // See tests/Tutorial/TutorialSessionOwnershipTest.
    if ($sessionId && !$sessionManager->playerOwnsSession($sessionId, (int) $playerId)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Tutorial session not found']);
        exit;
    }

    // IMPORTANT: Check if player has completed tutorial BEFORE marking current session as completed
    // This determines if they should receive rewards (first time) or not (replay)
    $hasCompletedBefore = $sessionManager->hasCompletedBefore($playerId);

    // Clear tutorial session from PHP session (do this BEFORE creating Player object)
    TutorialHelper::exitTutorialMode();

    // Force session write to persist the cleared vars
    session_write_close();
    session_start();

    // Now create the main player object (after tutorial mode is cleared)
    $mainPlayer = new \Classes\Player($playerId);

    if ($sessionId) {
        // Cancel specific session with transaction
        try {
            // Begin transaction for atomic cleanup
            $conn->beginTransaction();

            try {
                // Entity-aware adapters. Matches the path
                // TutorialManager uses since !393; keeps cancel + complete
                // on the same abstraction.
                $tutorialPlayer = $resourceManager->getTutorialPlayerAsEntity($sessionId);

                if ($tutorialPlayer) {
                    // Delete all resources in correct order (enemies → players → coords)
                    $resourceManager->deleteTutorialPlayerAsEntity($tutorialPlayer, $sessionId);
                }

                // Mark session as cancelled
                $sessionManager->cancelSession($sessionId);

                // Commit transaction
                $conn->commit();

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

    // Drop into the real game: invisibleMode off, teleport out of
    // waiting_room, grant race actions, first-time reward + starter pack,
    // and refresh the data/view caches. Shared with complete.php / skip.php
    // ($hasCompletedBefore was captured above, before the session was
    // marked completed).
    TutorialHelper::finalizeExitToGame($mainPlayer, TUTORIAL_SKIP_REWARD, $hasCompletedBefore);

    // Clean output buffer (discard any PHP warnings/errors/output)
    if (ob_get_length()) {
        ob_get_clean();
        ob_start(); /* Restart buffer for clean JSON output */
    }

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
