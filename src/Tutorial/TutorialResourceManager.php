<?php

namespace App\Tutorial;

use App\Entity\EntityManagerFactory;
use App\Entity\TutorialPlayer;
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

    // Note: Phase 4.4 retired the legacy service-class methods
    // (createTutorialPlayer, getTutorialPlayer, deleteTutorialPlayer
    // that took/returned App\Tutorial\TutorialPlayer). The *AsEntity
    // methods below are the only public surface now — they operate
    // directly on TutorialPlayer.

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
                // Coords should exist from map instance creation
                // If they don't, something went wrong - log and create them

                $this->conn->insert('coords', [
                    'x' => $enemyX,
                    'y' => $enemyY,
                    'z' => 0,
                    'plan' => $plan
                ]);
                $enemyCoordsId = (int) $this->conn->lastInsertId();

                // Validate that the insert worked
                if (!$enemyCoordsId || $enemyCoordsId <= 0) {
                    throw new \RuntimeException("Failed to create coords for enemy at ({$enemyX}, {$enemyY}) on plan {$plan}");
                }

            }

            // CRITICAL: Verify coords_id actually exists in database before using it
            $verifyCoords = $this->conn->fetchOne("SELECT id FROM coords WHERE id = ?", [$enemyCoordsId]);
            if (!$verifyCoords) {
                throw new \RuntimeException("Coords validation failed! coords_id {$enemyCoordsId} does not exist in database for enemy spawn at ({$enemyX}, {$enemyY}) on plan {$plan}");
            }

            // Generate unique enemy ID using new ID system (NPCs use negative IDs)
            $enemyId = getNextEntityId('npc');
            $displayId = getNextDisplayId('npc');

            // Create enemy NPC (using 'ame' race - weak tutorial dummy)
            $this->conn->insert('players', [
                'id' => $enemyId,
                'player_type' => 'npc',
                'display_id' => $displayId,
                'name' => 'Âme d\'entraînement',
                'coords_id' => $enemyCoordsId,
                'race' => 'ame',
                'xp' => 0,
                'pi' => 0,
                'energie' => 100, // Enough HP to survive tutorial attacks
                'psw' => '',
                'mail' => '',
                'plain_mail' => '',
                'avatar' => 'img/avatars/ame/default.webp',
                'portrait' => 'img/portraits/ame/1.jpeg',
                'text' => 'Âme d\'entraînement pour le tutoriel'
            ]);

            // Initialize enemy caracs (characteristics)
            // The 'ame' race has proper base stats (PV: 100, F: 1, E: 1)
            require_once dirname(__FILE__) . '/../../Classes/Player.php';
            $enemyPlayer = new \Classes\Player($enemyId);
            $enemyPlayer->get_caracs(); // Generate caracs from race base stats


            // Track enemy in tutorial_enemies table
            $this->conn->insert('tutorial_enemies', [
                'tutorial_session_id' => $sessionId,
                'enemy_player_id' => $enemyId,
                'enemy_coords_id' => $enemyCoordsId
            ]);


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
            // Find all active tutorial players with their session IDs.
            // Phase 4.5: link is on players.real_player_id_ref; tutorial_players
            // keeps only id/session/activity bookkeeping.
            $sql = 'SELECT tp.id, tp.player_id, tp.tutorial_session_id
                    FROM tutorial_players tp
                    JOIN players p ON p.id = tp.player_id
                    WHERE p.real_player_id_ref = ? AND tp.is_active = 1 AND tp.deleted_at IS NULL';
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


            return $cleanedCount;

        } catch (\Exception $e) {
            error_log("[TutorialResourceManager] Error cleaning up previous tutorial players: " . $e->getMessage());
            return 0; // Don't fail - just log and continue
        }
    }

    /**
     * Create the tutorial player for a session and return the hydrated
     * TutorialPlayer. Creates the isolated map instance, seeds
     * the players + players_actions + players_options + tutorial_players
     * rows via TutorialPlayerFactory, then spawns the enemy NPC.
     *
     * Failure path: cleanup any partial creation via cleanupPrevious
     * and wrap the error in a TutorialException.
     */
    public function createTutorialPlayerAsEntity(
        int $realPlayerId,
        string $sessionId,
        ?string $race = null,
        string $templatePlan = 'tutorial',
        int $spawnX = 0,
        int $spawnY = 0
    ): TutorialPlayer {
        try {
            $entity = TutorialPlayerFactory::create(
                $this->conn,
                $realPlayerId,
                $sessionId,
                $race,
                $templatePlan,
                $spawnX,
                $spawnY
            );

            $this->spawnTutorialEnemy($sessionId);


            return $entity;
        } catch (\Exception $e) {
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
     * Return the active TutorialPlayer for a session, or null.
     * Direct Doctrine lookup via the tutorialSessionId field.
     */
    public function getTutorialPlayerAsEntity(string $sessionId): ?TutorialPlayer
    {
        return EntityManagerFactory::getEntityManager()
            ->getRepository(TutorialPlayer::class)
            ->findOneBy(['tutorialSessionId' => $sessionId]);
    }

    /**
     * Delete the tutorial player for a session and all associated
     * resources: enemy NPC, players + tutorial_players rows + FK
     * cascade, and map instance (coords).
     *
     * Phase 4.4 inlined path — no more service-class round-trip.
     * FK cascade still delegates to TutorialPlayerCleanup (unchanged,
     * covered by TutorialPlayerCleanupIntegrationTest from !376).
     */
    public function deleteTutorialPlayerAsEntity(
        TutorialPlayer $entity,
        string $sessionId
    ): void {
        try {
            // Step 1: enemy NPC (no FK dependencies).
            $this->removeTutorialEnemy($sessionId);

            // Step 2: tutorial_players row + players row + FK cascade.
            // TutorialPlayerCleanup::deleteTutorialPlayer takes
            // (tutorial_players.id, players.id). The entity's getId()
            // is the players.id; tutorial_players.id requires one
            // lookup by session.
            $row = $this->conn->fetchAssociative(
                'SELECT id FROM tutorial_players WHERE tutorial_session_id = ? LIMIT 1',
                [$sessionId]
            );

            if ($row !== false) {
                $cleanup = new TutorialPlayerCleanup($this->conn, new NullLogger());
                $cleanup->deleteTutorialPlayer(
                    (int) $row['id'],
                    (int) $entity->getId()
                );
            } else {
            }

            // Step 3: map instance + its coords (must be AFTER player delete).
            $mapInstance = new TutorialMapInstance($this->conn);
            $mapInstance->deleteInstance($sessionId);
        } catch (\Exception $e) {
            throw new TutorialException(
                "Failed to delete tutorial player for session {$sessionId}",
                ['session_id' => $sessionId, 'entity_id' => $entity->getId()],
                0,
                $e
            );
        }
    }
}
