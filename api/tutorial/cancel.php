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

    $playerId = $_SESSION['playerId'];

    // Mark tutorial as completed (cancelled) in database
    $db = new Db();
    $em = EntityManagerFactory::getEntityManager();
    $conn = $em->getConnection();

    // Phase 4: Use TutorialResourceManager for proper cleanup order
    $resourceManager = new \App\Tutorial\TutorialResourceManager();
    $sessionManager = new \App\Tutorial\TutorialSessionManager();

    // IMPORTANT: Check if player has completed tutorial BEFORE marking current session as completed
    // This determines if they should receive rewards (first time) or not (replay)
    $hasCompletedBefore = $sessionManager->hasCompletedBefore($playerId);
    error_log("[Cancel] Player {$playerId} hasCompletedBefore check (BEFORE marking current session): " . ($hasCompletedBefore ? 'YES (replay)' : 'NO (first time)'));

    // Clear tutorial session from PHP session (do this BEFORE creating Player object)
    TutorialHelper::exitTutorialMode();
    error_log("[Cancel] Cleared tutorial session vars for player {$playerId}");

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

    // Remove invisibleMode from main player and add race actions (player already loaded above)
    $mainPlayer->get_data();

    // Remove invisibleMode so player can interact normally
    if ($mainPlayer->have_option('invisibleMode')) {
        $mainPlayer->end_option('invisibleMode');
        error_log("[Cancel] Removed invisibleMode from player {$playerId}");
    }

    // Move player from waiting_room to faction's respawn plan if they're still there
    $mainPlayer->getCoords();
    if ($mainPlayer->coords->plan === 'waiting_room') {
        $factionJson = json()->decode('factions', $mainPlayer->data->faction);
        $respawnPlan = $factionJson->respawnPlan ?? "olympia";

        $goCoords = (object) array(
            'x' => 0,
            'y' => 0,
            'z' => 0,
            'plan' => $respawnPlan
        );

        // Try to get or create coords for the respawn location
        $coordsId = \Classes\View::get_free_coords_id_arround($goCoords);

        // If no coords found, create the coords entry
        if ($coordsId === null) {
            $coordsDb = new \Classes\Db();
            $sql = 'INSERT INTO coords (x, y, z, plan) VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)';
            $coordsDb->exe($sql, array(0, 0, 0, $respawnPlan));
            /* Use MySQL's LAST_INSERT_ID() to get the ID (works for both INSERT and UPDATE) */
            $result = $coordsDb->exe('SELECT LAST_INSERT_ID() as id');
            $row = $result->fetch_assoc();
            $coordsId = $row['id'];
            error_log("[Cancel] Created/found coords entry {$coordsId} for {$respawnPlan}");
        }

        // Update player's coordinates
        $db = new \Classes\Db();
        $sql = 'UPDATE players SET coords_id = ? WHERE id = ?';
        $db->exe($sql, array($coordsId, $playerId));

        error_log("[Cancel] Player {$playerId} moved from waiting_room to {$respawnPlan} (coords_id: {$coordsId})");
    }

    // Add race actions if not already present
    $raceJson = json()->decode('races', $mainPlayer->data->race);
    if ($raceJson && !empty($raceJson->actions)) {
        $addedCount = 0;
        foreach($raceJson->actions as $actionName) {
            try {
                /* Only add if player doesn't already have this action */
                if (!$mainPlayer->have_action($actionName)) {
                    $mainPlayer->add_action($actionName);
                    $addedCount++;
                }
            } catch (\Exception $e) {
                /* Log Doctrine errors but continue processing other actions */
                error_log("[Cancel] Warning - could not check/add action '{$actionName}': " . $e->getMessage());
            }
        }
        error_log("[Cancel] Player {$playerId} initialized with {$addedCount} new actions for race {$mainPlayer->data->race}");
    }

    // Grant skip rewards ONLY if this is their first time (not a replay)
    // Already checked at the beginning before marking session as completed
    if (!$hasCompletedBefore) {
        $skipReward = TUTORIAL_SKIP_REWARD;
        $mainPlayer->put_xp($skipReward['xp']); /* This adds both XP and PI */
        error_log("[Cancel] Player {$playerId} received skip reward (first time): {$skipReward['xp']} XP/PI");
    } else {
        error_log("[Cancel] Player {$playerId} is replaying tutorial - no rewards granted");
    }

    // Refresh player data and view cache (so new coords/stats are shown after reload)
    $mainPlayer->refresh_data(); /* Clear JSON cache */
    $mainPlayer->refresh_view(); /* Clear view HTML cache */
    $mainPlayer->getCoords(); /* Reload coordinates */
    error_log("[Cancel] Player data and view refreshed - player at ({$mainPlayer->coords->x},{$mainPlayer->coords->y}) on plan {$mainPlayer->coords->plan}");

    // Clean output buffer (discard any PHP warnings/errors/output)
    if (ob_get_length()) {
        $buffered = ob_get_clean();
        if (!empty($buffered)) {
            error_log("[Cancel] WARNING: Discarded buffered output: " . substr($buffered, 0, 200));
        }
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
