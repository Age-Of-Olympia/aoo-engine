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

    public function __construct(Player $player, string $mode = 'first_time')
    {
        $this->context = new TutorialContext($player, $mode);
        $this->sessionId = $this->generateSessionId();
        $this->db = new Db();
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
        $totalSteps = $this->getTotalSteps($version);

        // Create tutorial character (temporary character for this session)
        $em = EntityManagerFactory::getEntityManager();
        $conn = $em->getConnection();
        $startingCoordsId = $this->getOrCreateTutorialStartCoords($conn);

        $this->tutorialPlayer = TutorialPlayer::create(
            $conn,
            $player->id,
            $this->sessionId,
            $startingCoordsId,
            $player->data->race ?? null
        );

        // Create progress record in database
        $sql = 'INSERT INTO tutorial_progress
                (player_id, tutorial_session_id, current_step, total_steps, tutorial_mode, tutorial_version, data)
                VALUES (?, ?, 0, ?, ?, ?, ?)';

        $initialData = $this->context->serializeState();

        $this->db->exe($sql, [
            $player->id,
            $this->sessionId,
            $totalSteps,
            $mode,
            $version,
            $initialData
        ]);

        return [
            'success' => true,
            'session_id' => $this->sessionId,
            'tutorial_player_id' => $this->tutorialPlayer->actualPlayerId, // Use actual players table ID
            'current_step' => 0,
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
        $sql = 'SELECT * FROM tutorial_configurations
                WHERE version = ? AND step_number = ? AND is_active = 1';

        $result = $this->db->exe($sql, [$version, $stepNumber]);

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $config = json_decode($row['config'], true);

            return [
                'step_number' => (int)$row['step_number'],
                'step_type' => $row['step_type'],
                'title' => $row['title'],
                'config' => $config,
                'xp_reward' => (int)$row['xp_reward']
            ];
        }

        return null;
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
        $currentStep = (int)$progress['current_step'];
        $totalSteps = (int)$progress['total_steps'];
        $version = $progress['tutorial_version'];

        // Get current step object
        $step = $this->getStep($currentStep, $version);

        if (!$step) {
            return [
                'success' => false,
                'error' => 'Step not found'
            ];
        }

        // Validate step if required
        if ($step->requiresValidation()) {
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

        // Check if tutorial is complete
        $nextStep = $currentStep + 1;
        if ($nextStep >= $totalSteps) {
            return $this->completeTutorial();
        }

        // Prepare resources for next step (if configured in current step)
        $stepData = $step->getData();
        if (isset($stepData['config']['prepare_next_step'])) {
            $this->context->prepareForNextStep($stepData['config']['prepare_next_step']);
        }

        // Update progress in database
        $updateSql = 'UPDATE tutorial_progress
                      SET current_step = ?,
                          xp_earned = ?,
                          data = ?
                      WHERE tutorial_session_id = ?';

        $this->db->exe($updateSql, [
            $nextStep,
            $this->context->getTutorialXP(),
            $this->context->serializeState(),
            $this->sessionId
        ]);

        // Apply prerequisites for the NEXT step (only when advancing to it)
        $nextStepObj = $this->getStep($nextStep, $version);
        if ($nextStepObj) {
            $nextStepConfig = $nextStepObj->getData();
            if (isset($nextStepConfig['config']['prerequisites'])) {
                $this->context->ensurePrerequisites($nextStepConfig['config']['prerequisites']);
            }
        }

        // Get next step data for client
        $nextStepData = $this->getCurrentStepForClient($nextStep, $version);

        return [
            'success' => true,
            'current_step' => $nextStep,
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
        // Transfer tutorial rewards to real player
        if ($this->tutorialPlayer) {
            $this->tutorialPlayer->transferRewardsToRealPlayer();
            $this->tutorialPlayer->delete(); // Soft delete tutorial character
        }

        // Mark as completed in database
        $sql = 'UPDATE tutorial_progress
                SET completed = 1, completed_at = CURRENT_TIMESTAMP
                WHERE tutorial_session_id = ?';

        $this->db->exe($sql, [$this->sessionId]);

        $xpEarned = $this->tutorialPlayer ? $this->tutorialPlayer->xp : $this->context->getTutorialXP();
        $piEarned = $this->tutorialPlayer ? $this->tutorialPlayer->pi : 0;

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
        $sql = 'SELECT COUNT(*) as total FROM tutorial_configurations
                WHERE version = ? AND is_active = 1';
        $result = $this->db->exe($sql, [$version]);

        if ($result) {
            $row = $result->fetch_assoc();
            return (int)$row['total'];
        }

        return 0;
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
