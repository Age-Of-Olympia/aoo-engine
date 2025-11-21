<?php

namespace App\Tutorial;

use App\Entity\EntityManagerFactory;
use App\Tutorial\Exceptions\TutorialException;
use Psr\Log\NullLogger;

/**
 * Tutorial Resource Manager
 *
 * Handles tutorial resource lifecycle:
 * - Tutorial player creation/deletion
 * - Tutorial enemy spawning/removal
 * - Map instance coordination
 * - Orphaned resource cleanup
 *
 * This service manages physical resources (players, enemies, map instances)
 * but does NOT handle session state or step progression.
 */
class TutorialResourceManager
{
    private $conn; // Doctrine DBAL connection

    public function __construct()
    {
        $em = EntityManagerFactory::getEntityManager();
        $this->conn = $em->getConnection();
    }

    /**
     * Create tutorial player with isolated map instance
     *
     * Creates a complete tutorial environment:
     * 1. Isolated map instance (copy of template)
     * 2. Tutorial player character
     * 3. Tutorial enemy NPC
     *
     * @param int $realPlayerId Real player's ID
     * @param string $sessionId Tutorial session UUID
     * @param string|null $race Character race (defaults to real player's race)
     * @return TutorialPlayer Created tutorial player
     * @throws TutorialException If creation fails
     */
    public function createTutorialPlayer(
        int $realPlayerId,
        string $sessionId,
        ?string $race = null
    ): TutorialPlayer {
        try {
            // Create tutorial player (which creates map instance internally)
            $tutorialPlayer = TutorialPlayer::create(
                $this->conn,
                $realPlayerId,
                $sessionId,
                null, // startingCoordsId auto-generated from instance
                $race
            );

            // Spawn tutorial enemy
            $this->spawnTutorialEnemy($sessionId);

            error_log("[TutorialResourceManager] Created tutorial player {$tutorialPlayer->playerId} for session {$sessionId}");

            return $tutorialPlayer;

        } catch (\Exception $e) {
            // Cleanup partial creation if it fails
            try {
                $this->cleanupPrevious($realPlayerId);
            } catch (\Exception $cleanupError) {
                error_log("[TutorialResourceManager] Cleanup after failed creation also failed: " . $cleanupError->getMessage());
            }

            throw new TutorialException(
                "Failed to create tutorial player for player {$realPlayerId}",
                ['real_player_id' => $realPlayerId, 'session_id' => $sessionId],
                0,
                $e
            );
        }
    }

    /**
     * Delete tutorial player and all associated resources
     *
     * @param TutorialPlayer $tutorialPlayer Tutorial player to delete
     * @param string $sessionId Session ID for cleanup
     * @throws TutorialException If deletion fails
     */
    public function deleteTutorialPlayer(TutorialPlayer $tutorialPlayer, string $sessionId): void
    {
        try {
            // Step 1: Delete tutorial enemy (no foreign key dependencies)
            $this->removeTutorialEnemy($sessionId);

            // Step 2: Delete tutorial player (references coords via foreign key)
            $tutorialPlayer->delete();
            error_log("[TutorialResourceManager] Deleted tutorial player for session {$sessionId}");

            // Step 3: Delete map instance (deletes coords - must be AFTER player deletion)
            $mapInstance = new TutorialMapInstance($this->conn);
            $mapInstance->deleteInstance($sessionId);
            error_log("[TutorialResourceManager] Deleted map instance for session {$sessionId}");

        } catch (\Exception $e) {
            throw new TutorialException(
                "Failed to delete tutorial player for session {$sessionId}",
                ['session_id' => $sessionId],
                0,
                $e
            );
        }
    }

    /**
     * Spawn tutorial enemy for combat training
     *
     * Creates a weak enemy NPC near the tutorial starting position.
     *
     * @param string $sessionId Tutorial session UUID
     * @throws TutorialException If spawn fails
     */
    private function spawnTutorialEnemy(string $sessionId): void
    {
        try {
            // Get tutorial player's position
            $stmt = $this->conn->prepare("
                SELECT p.coords_id, c.x, c.y, c.plan
                FROM tutorial_players tp
                JOIN players p ON tp.player_id = p.id
                JOIN coords c ON p.coords_id = c.id
                WHERE tp.tutorial_session_id = ?
            ");
            $stmt->bindValue(1, $sessionId);
            $result = $stmt->executeQuery();
            $playerData = $result->fetchAssociative();

            if (!$playerData) {
                throw new \RuntimeException("Tutorial player not found for session {$sessionId}");
            }

            $playerX = (int) $playerData['x'];
            $playerY = (int) $playerData['y'];
            $plan = $playerData['plan'];

            // Find nearby position for enemy (offset to avoid Gaïa at 1,0)
            // Gaïa (NPC guide) is at (1,0), so spawn enemy with offset
            $enemyX = $playerX + TutorialConstants::ENEMY_SPAWN_OFFSET_X;
            $enemyY = $playerY + TutorialConstants::ENEMY_SPAWN_OFFSET_Y;

            // Get or create coordinates for enemy
            $coordsStmt = $this->conn->prepare("
                SELECT id FROM coords WHERE x = ? AND y = ? AND plan = ?
            ");
            $coordsStmt->bindValue(1, $enemyX);
            $coordsStmt->bindValue(2, $enemyY);
            $coordsStmt->bindValue(3, $plan);
            $coordsResult = $coordsStmt->executeQuery();
            $coords = $coordsResult->fetchAssociative();

            if ($coords) {
                $enemyCoordsId = $coords['id'];
            } else {
                // Create new coordinates
                $this->conn->insert('coords', [
                    'x' => $enemyX,
                    'y' => $enemyY,
                    'z' => 0,
                    'plan' => $plan
                ]);
                $enemyCoordsId = (int) $this->conn->lastInsertId();
            }

            // Generate unique enemy ID (negative for NPCs)
            $enemyId = TutorialConstants::generateEnemyId();

            // Create enemy NPC
            $this->conn->insert('players', [
                'id' => $enemyId,
                'name' => 'Mannequin d\'entraînement',
                'coords_id' => $enemyCoordsId,
                'race' => 'Ame',
                'xp' => 0,
                'pi' => 0,
                'energie' => 50, // Weak enemy
                'psw' => '',
                'mail' => '',
                'plain_mail' => '',
                'avatar' => 'img/avatars/ame/default.webp',
                'portrait' => 'img/portraits/ame/default.webp',
                'text' => 'Mannequin d\'entrainement pour le tutoriel'
            ]);

            // Initialize enemy caracs (characteristics)
            // Use Classes\Player to set up proper stats
            require_once dirname(__FILE__) . '/../../Classes/Player.php';
            $enemyPlayer = new \Classes\Player($enemyId);
            $enemyPlayer->get_caracs(); // This will generate initial caracs with proper PV

            // Set enemy PV to 50 so it survives tutorial attacks
            $this->conn->executeStatement(
                "INSERT INTO players_bonus (player_id, name, n) VALUES (?, 'pv', 50)
                 ON DUPLICATE KEY UPDATE n = 50",
                [$enemyId]
            );
            // Refresh caracs with the new PV bonus
            $enemyPlayer->get_caracs();

            // Track enemy in tutorial_enemies table
            $this->conn->insert('tutorial_enemies', [
                'tutorial_session_id' => $sessionId,
                'enemy_player_id' => $enemyId,
                'enemy_coords_id' => $enemyCoordsId
            ]);

            error_log("[TutorialResourceManager] Spawned tutorial enemy {$enemyId} at ({$enemyX}, {$enemyY}) for session {$sessionId}");

            // Invalidate cached SVG for tutorial player
            $this->invalidateTutorialPlayerCache($sessionId);

        } catch (\Exception $e) {
            // Don't fail tutorial start if enemy spawn fails
            error_log("[TutorialResourceManager] Error spawning tutorial enemy: " . $e->getMessage());
        }
    }

    /**
     * Invalidate cached files for tutorial player
     *
     * Deletes cached SVG and other generated files to force regeneration
     * when map state changes (e.g., enemy spawned, resources gathered)
     *
     * @param string $sessionId Tutorial session UUID
     */
    private function invalidateTutorialPlayerCache(string $sessionId): void
    {
        try {
            // Get tutorial player ID
            $stmt = $this->conn->prepare("
                SELECT player_id
                FROM tutorial_players
                WHERE tutorial_session_id = ? AND is_active = 1
                LIMIT 1
            ");
            $stmt->bindValue(1, $sessionId);
            $result = $stmt->executeQuery();
            $row = $result->fetchAssociative();

            if (!$row) {
                return;
            }

            $tutorialPlayerId = $row['player_id'];

            // Delete cached SVG
            $svgPath = dirname(__FILE__) . '/../../datas/private/players/' . $tutorialPlayerId . '.svg';
            if (file_exists($svgPath)) {
                unlink($svgPath);
                error_log("[TutorialResourceManager] Invalidated SVG cache for tutorial player {$tutorialPlayerId}");
            }

        } catch (\Exception $e) {
            error_log("[TutorialResourceManager] Error invalidating cache: " . $e->getMessage());
        }
    }

    /**
     * Remove tutorial enemy for a session
     *
     * @param string $sessionId Tutorial session UUID
     */
    private function removeTutorialEnemy(string $sessionId): void
    {
        $cleanup = new TutorialEnemyCleanup($this->conn, new NullLogger());

        try {
            $cleanup->removeBySessionId($sessionId);
        } catch (TutorialEnemyCleanupException $e) {
            error_log("[TutorialResourceManager] Error removing tutorial enemy: " . $e->getMessage());
        }
    }

    /**
     * Cleanup previous/orphaned tutorial players for a real player
     *
     * Called before starting a new tutorial to ensure clean state.
     *
     * @param int $realPlayerId Real player's ID
     * @return int Number of players cleaned up
     */
    public function cleanupPrevious(int $realPlayerId): int
    {
        try {
            // Find all active tutorial players with their session IDs
            $sql = 'SELECT id, player_id, tutorial_session_id FROM tutorial_players
                    WHERE real_player_id = ? AND is_active = 1 AND deleted_at IS NULL';
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(1, $realPlayerId);
            $result = $stmt->executeQuery();

            $sessions = [];
            while ($row = $result->fetchAssociative()) {
                $sessions[] = [
                    'id' => $row['id'],
                    'player_id' => $row['player_id'],
                    'session_id' => $row['tutorial_session_id']
                ];
            }

            if (empty($sessions)) {
                return 0;
            }

            // Step 1: Clean up enemies first (no foreign key dependencies)
            $enemyCleanup = new TutorialEnemyCleanup($this->conn, new NullLogger());
            foreach ($sessions as $session) {
                if ($session['session_id']) {
                    try {
                        $enemyCleanup->removeBySessionId($session['session_id']);
                    } catch (\Exception $e) {
                        error_log("[TutorialResourceManager] Error cleaning enemy for session {$session['session_id']}: " . $e->getMessage());
                    }
                }
            }

            // Step 2: Clean up tutorial players (must be before map instance deletion)
            // This is critical because players reference coords via foreign key
            $playerCleanup = new TutorialPlayerCleanup($this->conn, new NullLogger());
            $cleanedCount = $playerCleanup->cleanupOrphanedTutorialPlayers($realPlayerId);

            // Step 3: Delete map instances (deletes coords - must be AFTER player deletion)
            foreach ($sessions as $session) {
                if ($session['session_id']) {
                    try {
                        $mapInstance = new TutorialMapInstance($this->conn);
                        $mapInstance->deleteInstance($session['session_id']);
                    } catch (\Exception $e) {
                        error_log("[TutorialResourceManager] Error deleting map instance for session {$session['session_id']}: " . $e->getMessage());
                    }
                }
            }

            error_log("[TutorialResourceManager] Cleaned up {$cleanedCount} orphaned tutorial player(s) for real player {$realPlayerId}");

            return $cleanedCount;

        } catch (\Exception $e) {
            error_log("[TutorialResourceManager] Error cleaning up previous tutorial players: " . $e->getMessage());
            return 0; // Don't fail - just log and continue
        }
    }

    /**
     * Get tutorial player for a session
     *
     * @param string $sessionId Tutorial session UUID
     * @return TutorialPlayer|null Tutorial player or null if not found
     */
    public function getTutorialPlayer(string $sessionId): ?TutorialPlayer
    {
        try {
            $sql = 'SELECT id FROM tutorial_players
                    WHERE tutorial_session_id = ? AND is_active = 1
                    LIMIT 1';
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(1, $sessionId);
            $result = $stmt->executeQuery();
            $row = $result->fetchAssociative();

            if (!$row) {
                return null;
            }

            return TutorialPlayer::load($this->conn, (int) $row['id']);

        } catch (\Exception $e) {
            error_log("[TutorialResourceManager] Error loading tutorial player: " . $e->getMessage());
            return null;
        }
    }
}
