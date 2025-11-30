<?php

namespace App\Tutorial;

use Classes\Player;
use Classes\Db;

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

    // Phase 4: Service layer for separation of concerns
    private TutorialSessionManager $sessionManager;
    private TutorialProgressManager $progressManager;
    private TutorialResourceManager $resourceManager;

    public function __construct(Player $player, string $mode = 'first_time')
    {
        $this->context = new TutorialContext($player, $mode);
        $this->sessionId = $this->generateSessionId();
        $this->db = new Db();
        $this->stepRepository = new TutorialStepRepository();

        // Initialize service layer
        $this->sessionManager = new TutorialSessionManager($this->db);
        $this->resourceManager = new TutorialResourceManager();
        $this->progressManager = new TutorialProgressManager(
            $this->context,
            $this->stepRepository,
            $this->sessionManager
        );
    }

    /**
     * Start a new tutorial session
     *
     * Phase 4: Refactored to use service layer (60 lines → 35 lines, -42%)
     *
     * @param string $version Tutorial version to use
     * @return array Session data
     */
    public function startTutorial(string $version = '1.0.0'): array
    {
        $player = $this->context->getPlayer();

        // Cleanup previous sessions
        $this->resourceManager->cleanupPrevious($player->id);

        // Get first step
        $firstStepId = $this->stepRepository->getFirstStepId($version) ?? 'gaia_welcome';
        $totalSteps = $this->stepRepository->getTotalSteps($version);

        // Create session
        $session = $this->sessionManager->createSession(
            $player->id,
            $this->context->getMode(),
            $version,
            $totalSteps,
            $firstStepId
        );

        // Update local session ID to match created session
        $this->sessionId = $session['session_id'];

        // Create tutorial player and resources
        $this->tutorialPlayer = $this->resourceManager->createTutorialPlayer(
            $player->id,
            $this->sessionId,
            $player->data->race ?? null
        );

        // Update context to use tutorial player (for placeholder replacement)
        $tutorialPlayerInstance = new \Classes\Player($this->tutorialPlayer->actualPlayerId);
        $tutorialPlayerInstance->get_data();
        $this->context->setPlayer($tutorialPlayerInstance);

        // Get first step data with prerequisites applied
        $stepData = $this->progressManager->getCurrentStepForClient(
            $firstStepId,
            $version,
            true // apply prerequisites
        );

        return array_merge($session, [
            'success' => true,
            'tutorial_player_id' => $this->tutorialPlayer->actualPlayerId,
            'step_data' => $stepData
        ]);
    }


    /**
     * Resume existing tutorial session
     *
     * Phase 4: Refactored to use service layer (50 lines → 25 lines, -50%)
     *
     * @param string $sessionId
     * @return array Session data
     */
    public function resumeTutorial(string $sessionId): array
    {
        $this->sessionId = $sessionId;

        // Load session
        $session = $this->sessionManager->loadSession($sessionId);

        if (!$session) {
            return [
                'success' => false,
                'error' => 'Tutorial session not found'
            ];
        }

        // Restore context state
        $this->context->restoreState($session['data']);

        // Load tutorial player
        $this->tutorialPlayer = $this->resourceManager->getTutorialPlayer($sessionId);

        // CRITICAL: Switch context to use tutorial player instead of main player
        // This ensures movement checks, validation hints, etc. use tutorial player's data
        if ($this->tutorialPlayer) {
            $tutorialPlayerObj = new Player($this->tutorialPlayer->actualPlayerId);
            $tutorialPlayerObj->get_data();
            $this->context->setPlayer($tutorialPlayerObj);
            error_log("[TutorialManager] Switched context to tutorial player {$this->tutorialPlayer->actualPlayerId}");
        }

        // Get current step data WITHOUT applying prerequisites
        // Prerequisites should only be applied when ADVANCING TO a step, not when resuming
        // Otherwise resources get restored on every validation check
        $stepData = $this->progressManager->getCurrentStepForClient(
            $session['current_step'],
            $session['version'],
            false // DO NOT apply prerequisites on resume
        );

        return array_merge($session, [
            'success' => true,
            'tutorial_player_id' => $this->tutorialPlayer ? $this->tutorialPlayer->actualPlayerId : null,
            'step_data' => $stepData
        ]);
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

        // Process dynamic placeholders in step text (e.g., {max_mvt})
        $stepData = $this->processPlaceholders($stepData);

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
     * Process dynamic placeholders in step data
     *
     * Replaces placeholders like {max_mvt} with actual values from the tutorial player.
     * This allows step text to adapt to different races and player stats.
     *
     * @param array $stepData Step data from AbstractStep::getData()
     * @return array Step data with placeholders replaced
     */
    private function processPlaceholders(array $stepData): array
    {
        // Get the tutorial player to access their race and stats
        $tutorialPlayerId = $this->context->getPlayer()->id;
        $tutorialPlayer = new Player($tutorialPlayerId);

        // Create placeholder service
        $placeholderService = new TutorialPlaceholderService($tutorialPlayer);

        // Process text fields that may contain placeholders
        $textFields = ['title', 'text', 'validation_hint'];

        foreach ($textFields as $field) {
            if (isset($stepData[$field]) && is_string($stepData[$field])) {
                $stepData[$field] = $placeholderService->replacePlaceholders($stepData[$field]);
            }
        }

        return $stepData;
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

        // Process dynamic placeholders in step text (e.g., {max_mvt})
        $stepData = $this->processPlaceholders($stepData);

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
     * Phase 4: Refactored to use service layer (110 lines → 35 lines, -68%)
     *
     * @param array $validationData Data from client for validation
     * @return array Result
     */
    public function advanceStep(array $validationData = []): array
    {
        error_log("[TutorialManager] advanceStep() called with validationData: " . json_encode($validationData));

        // Load session
        $session = $this->sessionManager->loadSession($this->sessionId);

        if (!$session) {
            return [
                'success' => false,
                'error' => 'Session not found'
            ];
        }

        // Delegate to ProgressManager
        try {
            $result = $this->progressManager->advanceStep(
                $this->sessionId,
                $session['current_step'],
                $session['version'],
                $validationData
            );

            // If tutorial completed, handle completion
            if ($result['completed'] ?? false) {
                $completionResult = $this->completeTutorial();

                // Include final step data for client-side config (e.g., redirect_delay)
                if (isset($result['final_step_data'])) {
                    $completionResult['final_step_data'] = $result['final_step_data'];
                }

                return $completionResult;
            }

            return $result;

        } catch (Exceptions\TutorialValidationException $e) {
            return [
                'success' => false,
                'error' => 'Step validation failed',
                'hint' => $e->getHint() ?? 'Veuillez compléter l\'étape correctement.'
            ];
        } catch (Exceptions\TutorialException $e) {
            error_log("[TutorialManager] Error advancing step: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Complete tutorial
     *
     * Phase 4: Refactored to use service layer (50 lines → 22 lines, -56%)
     *
     * Rewards (XP/PI) are only given on first completion.
     * Replays show a message indicating no rewards.
     */
    private function completeTutorial(): array
    {
        // Capture rewards from TutorialContext (source of truth for progression)
        $xpEarned = $this->context->getTutorialXP();

        // PI is awarded 1:1 with XP
        $piEarned = $xpEarned;

        // Check if this is a replay (player already completed tutorial before)
        $realPlayerId = $this->tutorialPlayer ? $this->tutorialPlayer->realPlayerId : $this->context->getPlayer()->id;
        $isReplay = $this->sessionManager->hasCompletedBefore($realPlayerId);

        // Only transfer rewards on first completion
        $actualXpAwarded = 0;
        $actualPiAwarded = 0;

        if (!$isReplay && $this->tutorialPlayer) {
            $this->tutorialPlayer->transferRewardsToRealPlayer($xpEarned, $piEarned);
            $actualXpAwarded = $xpEarned;
            $actualPiAwarded = $piEarned;

            // Remove invisibleMode from real player now that they completed tutorial
            $realPlayer = new \Classes\Player($realPlayerId);
            $realPlayer->end_option('invisibleMode');

            // Initialize player with race actions
            $realPlayer->get_data();
            $raceJson = json()->decode('races', $realPlayer->data->race);

            // Add all race-specific actions (keep tuto/attaquer for legacy compatibility)
            if ($raceJson && !empty($raceJson->actions)) {
                foreach($raceJson->actions as $actionName) {
                    $realPlayer->add_action($actionName);
                }
                error_log("[Tutorial Complete] Player {$realPlayerId} initialized with " . count($raceJson->actions) . " actions for race {$realPlayer->data->race}");
            }
        }

        // Delete tutorial resources
        if ($this->tutorialPlayer) {
            $this->resourceManager->deleteTutorialPlayer($this->tutorialPlayer, $this->sessionId);
        }

        // Complete session in database
        $this->sessionManager->completeSession($this->sessionId, $xpEarned);

        // Build completion message based on whether rewards were given
        if ($isReplay) {
            $message = "Félicitations ! Tu as terminé le tutoriel ! Tu l'avais déjà complété auparavant, donc tu ne reçois pas de récompenses cette fois.";
        } else {
            $message = "Félicitations ! Tu as terminé le tutoriel ! Tu as gagné {$actualXpAwarded} XP et {$actualPiAwarded} PI !";
        }

        return [
            'success' => true,
            'completed' => true,
            'xp_earned' => $actualXpAwarded,
            'pi_earned' => $actualPiAwarded,
            'final_level' => $this->context->getTutorialLevel(),
            'is_replay' => $isReplay,
            'message' => $message
        ];
    }

    /**
     * Check if player has completed tutorial
     *
     * Phase 4: Refactored to use service layer
     *
     * @param int $playerId
     * @return bool
     */
    public static function hasCompletedTutorial(int $playerId): bool
    {
        $sessionManager = new TutorialSessionManager();
        return $sessionManager->hasCompletedBefore($playerId);
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
     * Jump to a specific step (for debugging/testing)
     *
     * @param string $sessionId Tutorial session ID
     * @param int $targetStepNumber Step number to jump to
     * @return bool Success
     */
    public function jumpToStep(string $sessionId, int $targetStepNumber): bool
    {
        // Load session to get version
        $session = $this->sessionManager->loadSession($sessionId);

        if (!$session) {
            error_log("[TutorialManager] Tutorial session not found: $sessionId");
            return false;
        }

        $version = $session['version'] ?? '1.0.0';

        // Convert step number to step ID using repository
        $stepData = $this->stepRepository->getStepByNumber((float)$targetStepNumber, $version);

        if (!$stepData) {
            error_log("[TutorialManager] Step number $targetStepNumber not found for version $version");
            return false;
        }

        $targetStepId = $stepData['step_id'];

        try {
            // Delegate to ProgressManager which handles prerequisites and session updates
            $success = $this->progressManager->jumpToStep($sessionId, $targetStepId, $version);

            if ($success) {
                error_log("[TutorialManager] Successfully jumped to step $targetStepNumber ($targetStepId) for session $sessionId");
            }

            return $success;

        } catch (Exceptions\TutorialStepException $e) {
            error_log("[TutorialManager] Failed to jump to step: " . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            error_log("[TutorialManager] Unexpected error jumping to step: " . $e->getMessage());
            return false;
        }
    }


    /**
     * Generate unique session ID (temporary, replaced by SessionManager UUID)
     *
     * Phase 4: This is only used for initialization. The actual session ID
     * is generated by TutorialSessionManager and replaces this in startTutorial().
     */
    private function generateSessionId(): string
    {
        return sprintf(
            'tut_temp_%s',
            uniqid('', true)
        );
    }
}
