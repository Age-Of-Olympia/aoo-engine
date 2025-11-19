<?php

namespace App\Tutorial;

use Classes\Player;
use Classes\Db;
use App\Entity\EntityManagerFactory;

/**
 * Tutorial Manager (Phase 0 - Skeleton)
 *
 * Main orchestrator for tutorial system that:
 * - Manages tutorial sessions
 * - Tracks progress in database
 * - Loads tutorial steps from database
 * - Coordinates with TutorialContext for state management
 * - Creates temporary tutorial characters for each session
 */
class TutorialManager
{
    private TutorialContext $context;
    private string $sessionId;
    private Db $db;
    private ?TutorialPlayer $tutorialPlayer = null;
    private TutorialStepRepository $stepRepository;

    public function __construct(Player $player, string $mode = 'first_time')
    {
        $this->context = new TutorialContext($player, $mode);
        $this->sessionId = $this->generateSessionId();
        $this->db = new Db();
        $this->stepRepository = new TutorialStepRepository();
    }

    /**
     * Start a new tutorial session
     *
     * @param string $version Tutorial version to use
     * @return array Session data
     */
    public function startTutorial(string $version = '1.0.0'): array
    {
        $player = $this->context->getPlayer();
        $mode = $this->context->getMode();

        // Get total steps for this version
        $totalSteps = $this->stepRepository->getTotalSteps($version);

        // Create tutorial character (temporary character for this session)
        $em = EntityManagerFactory::getEntityManager();
        $conn = $em->getConnection();
        $startingCoordsId = $this->getOrCreateTutorialStartCoords($conn);

        // Clean up any previous active tutorial players for this real player
        // This prevents accumulation of orphaned tutorial characters
        $this->cleanupPreviousTutorialPlayers($conn, $player->id);

        $this->tutorialPlayer = TutorialPlayer::create(
            $conn,
            $player->id,
            $this->sessionId,
            $startingCoordsId,
            $player->data->race ?? null
        );

        // Spawn tutorial dummy enemy for combat training
        $this->spawnTutorialEnemy($conn, $this->sessionId);

        // Get first step_id for this version (lowest step_number)
        $firstStepId = $this->stepRepository->getFirstStepId($version) ?? 'gaia_welcome';

        // Create progress record in database
        $sql = 'INSERT INTO tutorial_progress
                (player_id, tutorial_session_id, current_step, total_steps, tutorial_mode, tutorial_version, data)
                VALUES (?, ?, ?, ?, ?, ?, ?)';

        $initialData = $this->context->serializeState();

        $this->db->exe($sql, [
            $player->id,
            $this->sessionId,
            $firstStepId,  // Use step_id instead of 0
            $totalSteps,
            $mode,
            $version,
            $initialData
        ]);

        return [
            'success' => true,
            'session_id' => $this->sessionId,
            'tutorial_player_id' => $this->tutorialPlayer->actualPlayerId, // Use actual players table ID
            'current_step' => $firstStepId,  // Return step_id
            'total_steps' => $totalSteps,
            'mode' => $mode,
            'version' => $version
        ];
    }

    /**
     * Get or create tutorial starting coordinates
     *
     * Tutorial map is at x=0, y=0, z=0, plan='tutorial'
     */
    private function getOrCreateTutorialStartCoords($conn): int
    {
        // Try to find existing tutorial start coords
        $stmt = $conn->prepare("SELECT id FROM coords WHERE x = 0 AND y = 0 AND z = 0 AND plan = 'tutorial'");
        $result = $stmt->executeQuery();
        $coords = $result->fetchAssociative();

        if ($coords) {
            return (int) $coords['id'];
        }

        // Create tutorial starting coordinates
        $conn->insert('coords', [
            'x' => 0,
            'y' => 0,
            'z' => 0,
            'plan' => 'tutorial'
        ]);

        return (int) $conn->lastInsertId();
    }

    /**
     * Resume existing tutorial session
     *
     * @param string $sessionId
     * @return array Session data
     */
    public function resumeTutorial(string $sessionId): array
    {
        $this->sessionId = $sessionId;

        // Load progress from database
        $sql = 'SELECT * FROM tutorial_progress WHERE tutorial_session_id = ?';
        $result = $this->db->exe($sql, [$sessionId]);

        if ($result && $result->num_rows > 0) {
            $progress = $result->fetch_assoc();

            // Restore context state
            if ($progress['data']) {
                $this->context->restoreState($progress['data']);
            }

            // Load tutorial player
            $em = EntityManagerFactory::getEntityManager();
            $conn = $em->getConnection();
            $this->tutorialPlayer = TutorialPlayer::loadBySession($conn, $sessionId);

            // CRITICAL: Switch context to use tutorial player instead of main player
            // This ensures movement checks, validation hints, etc. use tutorial player's data
            if ($this->tutorialPlayer) {
                $tutorialPlayerObj = new Player($this->tutorialPlayer->id);
                $tutorialPlayerObj->get_data();
                $this->context->setPlayer($tutorialPlayerObj);
                error_log("[TutorialManager] Switched context to tutorial player {$this->tutorialPlayer->id}");
            }

            return [
                'success' => true,
                'session_id' => $sessionId,
                'tutorial_player_id' => $this->tutorialPlayer ? $this->tutorialPlayer->id : null,
                'current_step' => (int)$progress['current_step'],
                'total_steps' => (int)$progress['total_steps'],
                'mode' => $progress['tutorial_mode'],
                'version' => $progress['tutorial_version'],
                'xp_earned' => (int)$progress['xp_earned']
            ];
        }

        return [
            'success' => false,
            'error' => 'Tutorial session not found'
        ];
    }

    /**
     * Get tutorial player for this session
     */
    public function getTutorialPlayer(): ?TutorialPlayer
    {
        return $this->tutorialPlayer;
    }

    /**
     * Get current step data from database
     *
     * @param int $stepNumber
     * @param string $version
     * @return array|null
     */
    public function getStepData(int $stepNumber, string $version = '1.0.0'): ?array
    {
        return $this->stepRepository->getStepByNumber((float)$stepNumber, $version);
    }

    /**
     * Get current step as AbstractStep object
     *
     * @param int $stepNumber
     * @param string $version
     * @return Steps\AbstractStep|null
     */
    public function getStep(int $stepNumber, string $version = '1.0.0'): ?Steps\AbstractStep
    {
        $stepData = $this->getStepData($stepNumber, $version);

        if (!$stepData) {
            return null;
        }

        return TutorialStepFactory::createFromData($stepData, $this->context);
    }

    /**
     * Get step data by step_id (name)
     *
     * @param string $stepId
     * @param string $version
     * @return array|null
     */
    public function getStepDataById(string $stepId, string $version = '1.0.0'): ?array
    {
        return $this->stepRepository->getStepById($stepId, $version);
    }

    /**
     * Get step object by step_id (name)
     *
     * @param string $stepId
     * @param string $version
     * @return Steps\AbstractStep|null
     */
    public function getStepById(string $stepId, string $version = '1.0.0'): ?Steps\AbstractStep
    {
        $stepData = $this->getStepDataById($stepId, $version);

        if (!$stepData) {
            return null;
        }

        return TutorialStepFactory::createFromData($stepData, $this->context);
    }

    /**
     * Get current step with full data for client (by step_id)
     *
     * @param string $stepId
     * @param string $version
     * @param bool $applyPrerequisites - Whether to apply prerequisites (true for resume, false for normal rendering)
     * @return array|null
     */
    public function getCurrentStepForClientById(string $stepId, string $version = '1.0.0', bool $applyPrerequisites = false): ?array
    {
        $step = $this->getStepById($stepId, $version);

        if (!$step) {
            return null;
        }

        $stepData = $step->getData();

        // Calculate actual step position (1st, 2nd, 3rd...) for progression display
        // This counts how many steps come before this one (ordered by step_number)
        $stepData['step_position'] = $this->calculateStepPosition($stepId, $version);

        // Apply prerequisites ONLY when explicitly requested (e.g., on resume)
        // NOT during normal rendering to avoid resetting resources on every render
        if ($applyPrerequisites && isset($stepData['config']['prerequisites'])) {
            $this->context->ensurePrerequisites($stepData['config']['prerequisites']);
        }

        // Generate dynamic validation hint (e.g., for movement steps showing remaining movements)
        // This ensures tooltips show current state, not static text
        if ($step->requiresValidation() && method_exists($step, 'getValidationHint')) {
            $dynamicHint = $step->getValidationHint();
            if ($dynamicHint) {
                $stepData['validation_hint'] = $dynamicHint;
            }
        }

        return array_merge($stepData, [
            'tutorial_state' => $this->context->getPublicState()
        ]);
    }

    /**
     * Calculate the actual position of a step in the sequence (1-indexed)
     *
     * @param string $stepId
     * @param string $version
     * @return int Position in sequence (1 for first step, 2 for second, etc.)
     */
    private function calculateStepPosition(string $stepId, string $version = '1.0.0'): int
    {
        return $this->stepRepository->calculateStepPosition($stepId, $version);
    }

    /**
     * Get current step with full data for client
     *
     * @param int $stepNumber
     * @param string $version
     * @param bool $applyPrerequisites - Whether to apply prerequisites (true for resume, false for normal rendering)
     * @return array|null
     */
    public function getCurrentStepForClient(int $stepNumber, string $version = '1.0.0', bool $applyPrerequisites = false): ?array
    {
        $step = $this->getStep($stepNumber, $version);

        if (!$step) {
            return null;
        }

        $stepData = $step->getData();

        // Apply prerequisites ONLY when explicitly requested (e.g., on resume)
        // NOT during normal rendering to avoid resetting resources on every render
        if ($applyPrerequisites && isset($stepData['config']['prerequisites'])) {
            $this->context->ensurePrerequisites($stepData['config']['prerequisites']);
        }

        // Generate dynamic validation hint (e.g., for movement steps showing remaining movements)
        // This ensures tooltips show current state, not static text
        if ($step->requiresValidation() && method_exists($step, 'getValidationHint')) {
            $dynamicHint = $step->getValidationHint();
            if ($dynamicHint) {
                $stepData['validation_hint'] = $dynamicHint;
            }
        }

        return array_merge($stepData, [
            'tutorial_state' => $this->context->getPublicState()
        ]);
    }

    /**
     * Advance to next step
     *
     * @param array $validationData Data from client for validation
     * @return array Result
     */
    public function advanceStep(array $validationData = []): array
    {
        error_log("[TutorialManager] advanceStep() called with validationData: " . json_encode($validationData));
        error_log("[TutorialManager] Stack trace: " . json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)));

        // Get current progress
        $sql = 'SELECT * FROM tutorial_progress WHERE tutorial_session_id = ?';
        $result = $this->db->exe($sql, [$this->sessionId]);

        if (!$result || $result->num_rows === 0) {
            return [
                'success' => false,
                'error' => 'Session not found'
            ];
        }

        $progress = $result->fetch_assoc();
        $currentStepId = $progress['current_step'];  // Now a step_id (string), not number
        $totalSteps = (int)$progress['total_steps'];
        $version = $progress['tutorial_version'];

        // Get current step object by step_id
        $step = $this->getStepById($currentStepId, $version);

        // DEBUG
        file_put_contents('/var/www/html/tmp/action_debug.log', "[TutorialManager] Current step ID: $currentStepId\n", FILE_APPEND);
        file_put_contents('/var/www/html/tmp/action_debug.log', "[TutorialManager] Step class: " . ($step ? get_class($step) : 'NULL') . "\n", FILE_APPEND);

        if (!$step) {
            return [
                'success' => false,
                'error' => 'Step not found: ' . $currentStepId
            ];
        }

        // Validate step if required
        file_put_contents('/var/www/html/tmp/action_debug.log', "[TutorialManager] Requires validation: " . ($step->requiresValidation() ? 'YES' : 'NO') . "\n", FILE_APPEND);
        if ($step->requiresValidation()) {
            file_put_contents('/var/www/html/tmp/action_debug.log', "[TutorialManager] Calling validate() with data: " . json_encode($validationData) . "\n", FILE_APPEND);
            $isValid = $step->validate($validationData);

            if (!$isValid) {
                return [
                    'success' => false,
                    'error' => 'Step validation failed',
                    'hint' => $step->getData()['validation_hint'] ?? 'Veuillez compléter l\'étape correctement.'
                ];
            }
        }

        // Execute step completion logic (awards XP, applies context changes)
        $step->onComplete($this->context);

        // Get step data to find next_step
        $stepData = $step->getData();
        $nextStepId = $stepData['next_step'] ?? null;

        // Check if tutorial is complete (no next step)
        if ($nextStepId === null) {
            return $this->completeTutorial();
        }

        // Prepare resources for next step (if configured in current step)
        if (isset($stepData['config']['prepare_next_step'])) {
            $this->context->prepareForNextStep($stepData['config']['prepare_next_step']);
        }

        // Update progress in database with next step_id
        $updateSql = 'UPDATE tutorial_progress
                      SET current_step = ?,
                          xp_earned = ?,
                          data = ?
                      WHERE tutorial_session_id = ?';

        $this->db->exe($updateSql, [
            $nextStepId,
            $this->context->getTutorialXP(),
            $this->context->serializeState(),
            $this->sessionId
        ]);

        // Apply prerequisites for the NEXT step (only when advancing to it)
        $nextStepObj = $this->getStepById($nextStepId, $version);
        if ($nextStepObj) {
            $nextStepConfig = $nextStepObj->getData();
            if (isset($nextStepConfig['config']['prerequisites'])) {
                $this->context->ensurePrerequisites($nextStepConfig['config']['prerequisites']);
            }
        }

        // Get next step data for client
        $nextStepData = $this->getCurrentStepForClientById($nextStepId, $version);

        return [
            'success' => true,
            'current_step' => $nextStepId,  // Return step_id
            'current_step_number' => $nextStepData['step_number'] ?? null,  // Step number (for ordering)
            'current_step_position' => $nextStepData['step_position'] ?? 1,  // Actual position (for display)
            'total_steps' => $totalSteps,
            'xp_earned' => $this->context->getTutorialXP(),
            'level' => $this->context->getTutorialLevel(),
            'pi' => $this->context->getTutorialPI(),
            'next_step_data' => $nextStepData
        ];
    }

    /**
     * Complete tutorial
     */
    private function completeTutorial(): array
    {
        // Get Doctrine connection for cleanup operations
        $em = EntityManagerFactory::getEntityManager();
        $conn = $em->getConnection();

        // Clean up tutorial map instance
        try {
            $mapInstance = new TutorialMapInstance($conn);
            $mapInstance->deleteInstance($this->sessionId);
            error_log("[TutorialManager] Deleted tutorial map instance for session {$this->sessionId}");
        } catch (\Exception $e) {
            error_log("[TutorialManager] Error deleting map instance: " . $e->getMessage());
        }

        // Clean up tutorial enemy
        $this->removeTutorialEnemy($conn, $this->sessionId);

        // Capture XP/PI before deletion
        $xpEarned = $this->tutorialPlayer ? $this->tutorialPlayer->xp : $this->context->getTutorialXP();
        $piEarned = $this->tutorialPlayer ? $this->tutorialPlayer->pi : 0;

        // Transfer tutorial rewards to real player
        if ($this->tutorialPlayer) {
            try {
                $this->tutorialPlayer->transferRewardsToRealPlayer();
                $this->tutorialPlayer->delete(); // Soft delete tutorial character
                error_log("[TutorialManager] Tutorial player deleted successfully");
            } catch (\Exception $e) {
                error_log("[TutorialManager] Error deleting tutorial player: " . $e->getMessage());
                error_log("[TutorialManager] Stack trace: " . $e->getTraceAsString());
                // Continue anyway - rewards already transferred, just cleanup failed
            }
        }

        // Mark as completed in database (even if deletion failed)
        $sql = 'UPDATE tutorial_progress
                SET completed = 1, completed_at = CURRENT_TIMESTAMP
                WHERE tutorial_session_id = ?';

        $this->db->exe($sql, [$this->sessionId]);

        return [
            'success' => true,
            'completed' => true,
            'xp_earned' => $xpEarned,
            'pi_earned' => $piEarned,
            'final_level' => $this->context->getTutorialLevel(),
            'message' => "Félicitations! Tu as terminé le tutoriel! Tu as gagné {$xpEarned} XP et {$piEarned} PI!"
        ];
    }

    /**
     * Check if player has completed tutorial
     *
     * @param int $playerId
     * @return bool
     */
    public static function hasCompletedTutorial(int $playerId): bool
    {
        $db = new Db();
        $sql = 'SELECT COUNT(*) as n FROM tutorial_progress
                WHERE player_id = ? AND completed = 1 AND tutorial_mode = "first_time"';
        $result = $db->exe($sql, [$playerId]);

        if ($result) {
            $row = $result->fetch_assoc();
            return $row['n'] > 0;
        }

        return false;
    }

    /**
     * Get context
     */
    public function getContext(): TutorialContext
    {
        return $this->context;
    }

    /**
     * Get session ID
     */
    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    /**
     * Get total steps for version
     */
    private function getTotalSteps(string $version): int
    {
        return $this->stepRepository->getTotalSteps($version);
    }

    /**
     * Clean up previous tutorial players for this real player
     *
     * Deactivates and deletes any existing tutorial players to prevent accumulation
     * of orphaned tutorial characters in the database.
     *
     * @param \Doctrine\DBAL\Connection $conn
     * @param int $realPlayerId
     */
    private function cleanupPreviousTutorialPlayers($conn, int $realPlayerId): void
    {
        try {
            // Find all active tutorial players for this real player
            $sql = 'SELECT id, player_id, tutorial_session_id FROM tutorial_players
                    WHERE real_player_id = ? AND is_active = 1 AND deleted_at IS NULL';
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(1, $realPlayerId);
            $result = $stmt->executeQuery();

            $cleanedCount = 0;
            while ($row = $result->fetchAssociative()) {
                $tutorialPlayerId = $row['id'];
                $actualPlayerId = $row['player_id'];
                $sessionId = $row['tutorial_session_id'];

                // Clean up associated tutorial enemy for this session
                if ($sessionId) {
                    $this->removeTutorialEnemy($conn, $sessionId);
                }

                // Soft delete in tutorial_players table
                $conn->update('tutorial_players', [
                    'is_active' => 0,
                    'deleted_at' => date('Y-m-d H:i:s')
                ], [
                    'id' => $tutorialPlayerId
                ]);

                // Hard delete from players table and related records
                if ($actualPlayerId) {
                    // Delete related records first to avoid foreign key constraints
                    // Tutorial players shouldn't have many records, but logs and actions are common
                    $conn->delete('players_logs', ['player_id' => $actualPlayerId]);
                    $conn->delete('players_logs', ['target_id' => $actualPlayerId]);
                    $conn->delete('players_actions', ['player_id' => $actualPlayerId]);
                    $conn->delete('players_items', ['player_id' => $actualPlayerId]);
                    $conn->delete('players_effects', ['player_id' => $actualPlayerId]);
                    $conn->delete('players_options', ['player_id' => $actualPlayerId]);
                    $conn->delete('players_connections', ['player_id' => $actualPlayerId]);
                    $conn->delete('players_bonus', ['player_id' => $actualPlayerId]);

                    // Now safe to delete the player
                    $conn->delete('players', ['id' => $actualPlayerId]);
                }

                $cleanedCount++;
            }

            if ($cleanedCount > 0) {
                error_log("[TutorialManager] Cleaned up {$cleanedCount} orphaned tutorial player(s) for real player {$realPlayerId}");
            }
        } catch (\Exception $e) {
            // Log but don't block tutorial start
            error_log("[TutorialManager] Error cleaning up tutorial players: " . $e->getMessage());
        }
    }

    /**
     * Spawn tutorial enemy for combat training
     *
     * Creates a weak enemy NPC (negative ID) near the tutorial starting position
     * Each tutorial session gets its own unique enemy instance
     *
     * @param mixed $conn Database connection
     * @param string $sessionId Tutorial session ID to track the enemy
     */
    private function spawnTutorialEnemy($conn, string $sessionId): void
    {
        try {
            // Generate unique negative ID for this tutorial enemy
            // Use -100000 range to avoid conflicts with regular NPCs (-9001 to -3)
            $enemyId = -100000 - mt_rand(1, 899999); // Range: -100001 to -999999

            // Ensure ID is unique
            $checkStmt = $conn->prepare("SELECT id FROM players WHERE id = ?");
            $checkStmt->bindValue(1, $enemyId);
            $result = $checkStmt->executeQuery();

            // If ID exists (unlikely), try again with current timestamp
            if ($result->fetchOne()) {
                $enemyId = -100000 - (int)(microtime(true) * 1000);
            }

            // Create coordinates for enemy (position 3,0 on instance plan - away from Gaïa at 1,0)
            // Get instance plan name from tutorial_players table
            $planStmt = $conn->prepare("
                SELECT c.plan FROM coords c
                INNER JOIN tutorial_players tp ON c.id = (
                    SELECT coords_id FROM players WHERE id = tp.player_id LIMIT 1
                )
                WHERE tp.tutorial_session_id = ?
                LIMIT 1
            ");
            $planStmt->bindValue(1, $sessionId);
            $planResult = $planStmt->executeQuery();
            $instancePlan = $planResult->fetchOne() ?: 'tutorial';

            $stmt = $conn->prepare("
                INSERT INTO coords (x, y, z, plan)
                VALUES (0, -1, 0, ?)
            ");
            $stmt->bindValue(1, $instancePlan);
            $stmt->executeQuery();
            $enemyCoordsId = $conn->lastInsertId();

            // Create tutorial dummy NPC with explicit negative ID
            // NPCs (enemies) use negative matricules in this game
            // Use player_type='npc' discriminator to mark as NPC
            $stmt = $conn->prepare("
                INSERT INTO players (id, player_type, name, psw, mail, plain_mail, coords_id, race, xp, bonus_points, pi, avatar, portrait, text)
                VALUES (?, 'npc', ?, '', 'dummy@tutorial.local', 'dummy@tutorial.local', ?, 'ame', 10, 0, 0, 'img/avatars/nain/1.png', 'img/portraits/nain/1.jpeg', 'Âme d''entraînement')
            ");
            $stmt->bindValue(1, $enemyId);
            $stmt->bindValue(2, 'Âme d\'entraînement');
            $stmt->bindValue(3, $enemyCoordsId);
            $stmt->executeQuery();

            // Track the enemy in tutorial_enemies table for cleanup
            $stmt = $conn->prepare("
                INSERT INTO tutorial_enemies (tutorial_session_id, enemy_player_id, enemy_coords_id, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->bindValue(1, $sessionId);
            $stmt->bindValue(2, $enemyId);
            $stmt->bindValue(3, $enemyCoordsId);
            $stmt->executeQuery();

            error_log("[TutorialManager] Spawned tutorial enemy NPC (id={$enemyId}) at coords_id={$enemyCoordsId} for session {$sessionId}");
        } catch (\Exception $e) {
            error_log("[TutorialManager] Error spawning tutorial enemy: " . $e->getMessage());
            // Don't block tutorial start if enemy spawn fails
        }
    }

    /**
     * Remove tutorial enemy for a session
     *
     * Cleans up the tutorial dummy enemy when tutorial completes or is cancelled
     *
     * @param mixed $conn Database connection
     * @param string $sessionId Tutorial session ID
     */
    private function removeTutorialEnemy($conn, string $sessionId): void
    {
        try {
            // Find all enemies for this session
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

                // Delete player and related records
                if ($enemyId) {
                    // Delete related records first to avoid foreign key constraints
                    $conn->delete('players_logs', ['player_id' => $enemyId]);
                    $conn->delete('players_logs', ['target_id' => $enemyId]);
                    $conn->delete('players_actions', ['player_id' => $enemyId]);
                    $conn->delete('players_items', ['player_id' => $enemyId]);
                    $conn->delete('players_effects', ['player_id' => $enemyId]);
                    $conn->delete('players_kills', ['player_id' => $enemyId]);
                    $conn->delete('players_kills', ['target_id' => $enemyId]);
                    $conn->delete('players_assists', ['player_id' => $enemyId]);
                    $conn->delete('players_assists', ['target_id' => $enemyId]);

                    // Now safe to delete the enemy NPC
                    $conn->delete('players', ['id' => $enemyId]);
                }

                // Delete coordinates
                if ($coordsId) {
                    $conn->delete('coords', ['id' => $coordsId]);
                }

                $cleanedCount++;
            }

            // Delete tracking records
            $conn->delete('tutorial_enemies', ['tutorial_session_id' => $sessionId]);

            if ($cleanedCount > 0) {
                error_log("[TutorialManager] Removed {$cleanedCount} tutorial enemy/enemies for session {$sessionId}");
            }
        } catch (\Exception $e) {
            error_log("[TutorialManager] Error removing tutorial enemy: " . $e->getMessage());
        }
    }

    /**
     * Jump to a specific step (for debugging/testing)
     *
     * @param string $sessionId Tutorial session ID
     * @param int $targetStepNumber Step number to jump to
     * @return bool Success
     */
    public function jumpToStep(string $sessionId, int $targetStepNumber): bool
    {
        $em = EntityManagerFactory::getEntityManager();

        // Get tutorial progress
        $progress = $em->getRepository(\App\Entity\TutorialProgress::class)
            ->findOneBy(['tutorialSessionId' => $sessionId]);

        if (!$progress) {
            error_log("Tutorial progress not found for session: $sessionId");
            return false;
        }

        // Get all steps
        $steps = $em->getRepository(\App\Entity\TutorialConfiguration::class)
            ->findBy([], ['stepNumber' => 'ASC']);

        // Find target step
        $targetStep = null;
        foreach ($steps as $step) {
            if ($step->getStepNumber() === $targetStepNumber) {
                $targetStep = $step;
                break;
            }
        }

        if (!$targetStep) {
            error_log("Step number $targetStepNumber not found");
            return false;
        }

        // Update progress
        $progress->setCurrentStep($targetStepNumber);
        $progress->setData(array_merge(
            json_decode($progress->getData(), true) ?? [],
            [
                'jumped_to_step' => $targetStepNumber,
                'jump_timestamp' => time()
            ]
        ));

        $em->flush();

        // Apply any step prerequisites
        $this->applyStepPrerequisites($progress->getPlayerId(), $targetStep);

        return true;
    }

    /**
     * Apply step prerequisites (mvt, pa, auto_restore, etc.)
     */
    private function applyStepPrerequisites(int $playerId, $stepConfig): void
    {
        $config = json_decode($stepConfig->getConfig(), true);
        $prerequisites = $config['prerequisites'] ?? null;

        if (!$prerequisites) {
            return;
        }

        $player = new Player($playerId);

        // Set MVT limit if specified
        if (isset($prerequisites['mvt'])) {
            $_SESSION['tutorial_mvt_limit'] = $prerequisites['mvt'];

            if ($prerequisites['auto_restore'] ?? false) {
                // Restore MVT to limit
                $player->putBonus(['mvt' => $prerequisites['mvt']]);
            }
        }

        // Set PA limit if specified
        if (isset($prerequisites['pa'])) {
            $_SESSION['tutorial_pa_limit'] = $prerequisites['pa'];

            if ($prerequisites['auto_restore'] ?? false) {
                // Restore PA to limit
                $player->putBonus(['pa' => $prerequisites['pa']]);
            }
        }

        // Set action limit if specified
        if (isset($prerequisites['action_limit'])) {
            $_SESSION['tutorial_action_limit'] = $prerequisites['action_limit'];
        }
    }

    /**
     * Generate unique session ID
     */
    private function generateSessionId(): string
    {
        return sprintf(
            'tut_%s_%s',
            uniqid('', true),
            bin2hex(random_bytes(4))
        );
    }
}
