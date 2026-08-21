<?php

namespace App\Tutorial;

use App\Factory\TutorialPlayerFactory;
use App\Factory\EntityManagerFactory;
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

    // Note: the legacy service-class methods were retired
    // (createTutorialPlayer, getTutorialPlayer, deleteTutorialPlayer
    // that took/returned App\Tutorial\TutorialPlayer). The *AsEntity
    // methods below are the only public surface now — they operate
    // directly on TutorialPlayer.

    /**
     * Spawn the dynamic NPCs configured for this tutorial version.
     *
     * Replaces the old hardcoded "spawn the dummy at (2,1)"
     * path. Reads tutorial_npcs (spawn_mode='dynamic',
     * spawn_at_step_id IS NULL = at session start) — each row gets
     * a fresh players row at its (x, y) on the arena.
     *
     * Each spawn is recorded in tutorial_enemies so the existing
     * cleanup paths still find them on session end.
     *
     * Failures are swallowed (logged only) — a missing dynamic NPC
     * shouldn't block tutorial start.
     */
    private function spawnTutorialEnemy(string $sessionId): void
    {
        // Session-start dynamic NPCs: spawn_at_step_id IS NULL.
        $repo = new TutorialNpcRepository($this->conn);
        $this->spawnDynamicNpcs($sessionId, $repo->listActive('1.0.0', 'dynamic'));
    }

    /**
     * Spawn the dynamic NPCs whose `spawn_at_step_id` matches the given
     * step name (joined via tutorial_steps.step_id). Called by the
     * progression manager when transitioning to a step. Public so other
     * runtime paths can call it; safe to invoke even with no matching
     * NPCs (no-op).
     *
     * @return int Number of NPCs spawned.
     */
    public function spawnDynamicNpcsAtStep(string $sessionId, string $stepName, string $version = '1.0.0'): int
    {
        $repo = new TutorialNpcRepository($this->conn);
        $list = $repo->listForStepName($version, 'dynamic', $stepName);
        if (empty($list)) {
            return 0;
        }
        $this->spawnDynamicNpcs($sessionId, $list);
        return count($list);
    }

    /**
     * Insert a players row + tutorial_enemies bookkeeping row for each
     * config in $npcs. Each NPC's (x,y) is an ABSOLUTE arena tile.
     *
     * It used to be an offset from the player's current tile — a relic
     * of « the dummy appears at (2,1) from spawn » when the player was
     * guaranteed to stand at (0,0). The gather flow no longer pins the
     * player anywhere, so an offset drifted away from the step's fixed
     * validation coordinates (adjacent_to_position 2,1) and from the
     * step text — the arena is a fixed stage, the roster places on it.
     *
     * Failures swallow + log — a missing dynamic NPC shouldn't break
     * tutorial progression.
     */
    private function spawnDynamicNpcs(string $sessionId, array $npcs): void
    {
        if (empty($npcs)) {
            return;
        }
        try {
            $playerData = $this->conn->fetchAssociative("
                SELECT c.plan
                FROM tutorial_players tp
                JOIN players p ON tp.player_id = p.id
                JOIN coords c ON p.coords_id = c.id
                WHERE tp.tutorial_session_id = ?
            ", [$sessionId]);
            if (!$playerData) {
                throw new \RuntimeException("Tutorial player not found for session {$sessionId}");
            }
            $plan = $playerData['plan'];

            require_once dirname(__FILE__) . '/../../Classes/Player.php';

            foreach ($npcs as $npc) {
                $enemyX = (int) $npc['x'];
                $enemyY = (int) $npc['y'];

                $coords = $this->conn->fetchAssociative(
                    "SELECT id FROM coords WHERE x = ? AND y = ? AND plan = ?",
                    [$enemyX, $enemyY, $plan]
                );
                if ($coords) {
                    $enemyCoordsId = (int) $coords['id'];
                } else {
                    $this->conn->insert('coords', [
                        'x' => $enemyX,
                        'y' => $enemyY,
                        'z' => 0,
                        'plan' => $plan,
                    ]);
                    $enemyCoordsId = (int) $this->conn->lastInsertId();
                    if ($enemyCoordsId <= 0) {
                        throw new \RuntimeException(
                            "Failed to create coords for npc role={$npc['role']} at ({$enemyX},{$enemyY}) on plan {$plan}"
                        );
                    }
                }

                $enemyId = getNextEntityId('npc');
                $displayId = getNextDisplayId('npc');

                $this->conn->insert('players', [
                    'id'          => $enemyId,
                    'player_type' => 'npc',
                    'display_id'  => $displayId,
                    'name'        => $npc['name'],
                    'coords_id'   => $enemyCoordsId,
                    'slot'        => \App\Service\Map\EntityLocationService::SLOT_INSTALLED,
                    'race'        => $npc['race'],
                    'xp'          => 0,
                    'pi'          => 0,
                    'energie'     => $npc['energie'],
                    'psw'         => '',
                    'mail'        => '',
                    'plain_mail'  => '',
                    'avatar'      => $npc['avatar'],
                    'portrait'    => $npc['portrait'],
                    'text'        => $npc['text'] ?? '',
                ]);

                $enemyPlayer = new \Classes\Player($enemyId);
                $enemyPlayer->get_caracs();

                /* Le rôle voyage avec l'apparition : sans lui, la ligne dit
                 * seulement « un PNJ est né pour cette session », et qui la
                 * relit ne peut plus distinguer l'adversaire d'un marchand.
                 * Le damier en avait besoin pour poser `.tutorial-enemy` — il
                 * s'en remettait faute de mieux au nom du personnage. */
                $this->conn->insert('tutorial_enemies', [
                    'tutorial_session_id' => $sessionId,
                    'enemy_player_id'     => $enemyId,
                    'enemy_coords_id'     => $enemyCoordsId,
                    'role'                => $npc['role'] ?? null,
                ]);
            }

            $this->invalidateTutorialPlayerCache($sessionId);
        } catch (\Exception $e) {
            error_log("[TutorialResourceManager] Error spawning dynamic tutorial NPCs: " . $e->getMessage());
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
            // Link is on players.real_player_id_ref; tutorial_players
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
     * Global sweep of stale tutorial instances — the ones no per-player
     * path ever reclaims.
     *
     * complete.php and cancel.php tear their session down, and start.php
     * cleans a player's PREVIOUS sessions; but a player who abandons the
     * tutorial and never comes back leaves an instance forever, and a
     * teardown that failed halfway (its errors are logged, not rethrown)
     * leaves an instance that no is_active=1 query will ever find again.
     *
     * A tut_* plan is swept unless a session is genuinely IN PROGRESS on
     * it: an active tutorial player, not completed, younger than
     * $maxAgeHours. Everything else goes — completed sessions, sessions
     * past the age limit, and plans with no session at all (including the
     * files-era leftovers imported by the plan seed). A real player
     * standing on the plan blocks the sweep and is reported instead.
     *
     * @return array{swept: list<string>, skipped: array<string, string>}
     */
    public function cleanupStale(int $maxAgeHours = 48): array
    {
        $report = ['swept' => [], 'skipped' => []];

        // Instance identity is derived from the session id (tut_ + its 10
        // first chars); enumerate every plan that LOOKS like an instance,
        // config row or coords, so orphans of either half are seen.
        $plans = $this->conn->fetchFirstColumn("
            SELECT slug FROM plans WHERE slug LIKE 'tut\\_%'
            UNION
            SELECT DISTINCT plan FROM coords WHERE plan LIKE 'tut\\_%'
        ");

        $enemyCleanup = new TutorialEnemyCleanup($this->conn, new NullLogger());
        $playerCleanup = new TutorialPlayerCleanup($this->conn, new NullLogger());
        $mapInstance = new TutorialMapInstance($this->conn);

        foreach ($plans as $plan) {
            $plan = (string) $plan;
            $sessionPrefix = substr($plan, strlen('tut_'));

            $sessions = $this->conn->fetchAllAssociative(
                "SELECT tp.id, tp.player_id, tp.tutorial_session_id,
                        (tp.created_at >= (NOW() - INTERVAL ? HOUR)) AS recent,
                        COALESCE((SELECT MAX(pr.completed) FROM tutorial_progress pr
                                   WHERE pr.tutorial_session_id = tp.tutorial_session_id), 0) AS completed
                   FROM tutorial_players tp
                  WHERE tp.is_active = 1 AND tp.deleted_at IS NULL
                    AND SUBSTRING(tp.tutorial_session_id, 1, 10) = ?",
                [$maxAgeHours, $sessionPrefix]
            );

            $inProgress = array_filter(
                $sessions,
                static fn(array $s): bool => (int) $s['completed'] === 0 && (int) $s['recent'] === 1
            );
            if ($inProgress !== []) {
                continue; // someone is playing here
            }

            try {
                // Stale sessions first: enemies, then their tutorial player
                // (players holds coords by foreign key, it must leave before
                // the instance).
                foreach ($sessions as $session) {
                    $enemyCleanup->removeBySessionId((string) $session['tutorial_session_id']);
                    $playerCleanup->deleteTutorialPlayer((int) $session['id'], (int) $session['player_id']);
                }

                // Leftover tutorial characters whose bookkeeping row is
                // already inactive or gone (a half-failed teardown).
                $leftovers = $this->conn->fetchAllAssociative(
                    "SELECT p.id,
                            (SELECT tp.id FROM tutorial_players tp WHERE tp.player_id = p.id LIMIT 1) AS tp_id
                       FROM players p
                       JOIN coords c ON c.id = p.coords_id
                      WHERE c.plan = ? AND p.player_type = 'tutorial'",
                    [$plan]
                );
                foreach ($leftovers as $leftover) {
                    $playerCleanup->deleteTutorialPlayer((int) ($leftover['tp_id'] ?? 0), (int) $leftover['id']);
                }

                // A real player on the plan is not ours to delete: report,
                // and leave the instance standing.
                $realCount = (int) $this->conn->fetchOne(
                    "SELECT COUNT(*) FROM players p JOIN coords c ON c.id = p.coords_id
                      WHERE c.plan = ? AND p.player_type = 'real'",
                    [$plan]
                );
                if ($realCount > 0) {
                    $report['skipped'][$plan] = $realCount . ' joueur(s) réel(s) sur le plan';

                    continue;
                }

                $mapInstance->deleteInstanceByPlan($plan);
                $report['swept'][] = $plan;
            } catch (\Exception $e) {
                $report['skipped'][$plan] = $e->getMessage();
                error_log("[TutorialResourceManager] Error sweeping stale instance {$plan}: " . $e->getMessage());
            }
        }

        // Enemies whose session vanished entirely, wherever they stand.
        $enemyCleanup->removeOrphanedEnemies();

        return $report;
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
     * Inlined path — no more service-class round-trip.
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
