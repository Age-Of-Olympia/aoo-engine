<?php

namespace App\Tutorial;

use App\Tutorial\Steps\AbstractStep;
use App\Tutorial\Exceptions\TutorialStepException;
use App\Tutorial\Exceptions\TutorialValidationException;
use Classes\Player;

/**
 * Tutorial Progress Manager
 *
 * Handles tutorial step progression:
 * - Advancing to next step
 * - Step validation
 * - XP/reward tracking
 * - Step prerequisites
 * - Step data preparation for client
 *
 * This service coordinates between steps, context, and session manager
 * but does NOT handle session creation or resource management.
 */
class TutorialProgressManager
{
    private TutorialContext $context;
    private TutorialStepRepository $stepRepository;
    private TutorialSessionManager $sessionManager;

    public function __construct(
        TutorialContext $context,
        TutorialStepRepository $stepRepository,
        TutorialSessionManager $sessionManager
    ) {
        $this->context = $context;
        $this->stepRepository = $stepRepository;
        $this->sessionManager = $sessionManager;
    }

    /**
     * Advance to next step
     *
     * Validates current step, awards XP, applies context changes,
     * and moves to next step.
     *
     * @param string $sessionId Tutorial session ID
     * @param string $currentStepId Current step ID
     * @param string $version Tutorial version
     * @param array $validationData Data to validate current step completion
     * @return array Next step data or completion response
     * @throws TutorialStepException If step advancement fails
     * @throws TutorialValidationException If validation fails
     */
    public function advanceStep(
        string $sessionId,
        string $currentStepId,
        string $version,
        array $validationData = []
    ): array {
        // Load current step
        $step = $this->loadStep($currentStepId, $version);

        if (!$step) {
            throw new TutorialStepException(
                "Current step '{$currentStepId}' not found",
                ['step_id' => $currentStepId, 'version' => $version]
            );
        }

        // Validate step completion
        if ($step->requiresValidation()) {
            $isValid = $step->validate($validationData);

            if (!$isValid) {
                $hint = $step->getConfig()['validation_hint'] ?? 'Complete the required action to continue';

                throw new TutorialValidationException(
                    "Step validation failed for '{$currentStepId}'",
                    $hint,
                    ['step_id' => $currentStepId, 'validation_data' => $validationData]
                );
            }
        }

        // Step completed successfully - apply rewards and changes
        $step->onComplete($this->context);

        // Get next step
        $nextStepId = $step->getNextStep();

        if (!$nextStepId) {
            // Tutorial complete!
            return [
                'success' => true,
                'completed' => true,
                'message' => 'Tutorial completed!',
                'xp_earned' => $this->context->getTutorialXP(),
                'final_step_data' => $step->toArray()  // Include final step config for client
            ];
        }

        // Load next step
        $nextStep = $this->loadStep($nextStepId, $version);

        if (!$nextStep) {
            throw new TutorialStepException(
                "Next step '{$nextStepId}' not found",
                ['step_id' => $nextStepId, 'version' => $version]
            );
        }

        // Apply prerequisites for next step
        $this->applyStepPrerequisites($nextStep);

        // Update session progress
        $this->sessionManager->updateProgress(
            $sessionId,
            $nextStepId,
            $this->context->getTutorialXP(),
            $this->context->serializeState()
        );

        // Get step position for progress tracking
        $stepPosition = $this->calculateStepPosition($nextStepId, $version);

        // Get total steps from session
        $totalSteps = $this->stepRepository->getTotalSteps($version);

        // Prepare next step data
        $nextStepData = $this->prepareStepForClient($nextStep, $version);

        // Return next step data for client
        // Note: current_step contains step_id (string), current_step_position contains display position (int)
        return [
            'success' => true,
            'completed' => false,
            'current_step' => $nextStepId,  // step_id (string, e.g., "first_movement")
            'current_step_position' => $nextStepData['step_position'] ?? 1,  // display position (int, e.g., 1, 2, 3...)
            'total_steps' => $totalSteps,
            'xp_earned' => $this->context->getTutorialXP(),
            'level' => $this->context->getTutorialLevel(),
            'pi' => $this->context->getTutorialPI(),
            'next_step_data' => $nextStepData
        ];
    }

    /**
     * Get current step data for client
     *
     * @param string $stepId Step identifier
     * @param string $version Tutorial version
     * @param bool $applyPrerequisites Whether to apply step prerequisites
     * @return array|null Step data formatted for client or null if not found
     */
    public function getCurrentStepForClient(
        string $stepId,
        string $version,
        bool $applyPrerequisites = false
    ): ?array {
        $step = $this->loadStep($stepId, $version);

        if (!$step) {
            return null;
        }

        if ($applyPrerequisites) {
            $this->applyStepPrerequisites($step);
        }

        return $this->prepareStepForClient($step, $version);
    }

    /**
     * Jump to a specific step (debugging/testing only)
     *
     * @param string $sessionId Tutorial session ID
     * @param string $targetStepId Target step ID to jump to
     * @param string $version Tutorial version
     * @return bool True if successful
     * @throws TutorialStepException If jump fails
     */
    public function jumpToStep(
        string $sessionId,
        string $targetStepId,
        string $version
    ): bool {
        $step = $this->loadStep($targetStepId, $version);

        if (!$step) {
            throw new TutorialStepException(
                "Target step '{$targetStepId}' not found",
                ['step_id' => $targetStepId, 'version' => $version]
            );
        }

        try {
            // Apply prerequisites for target step
            $this->applyStepPrerequisites($step);

            // Update session to new step
            $this->sessionManager->updateProgress(
                $sessionId,
                $targetStepId,
                $this->context->getTutorialXP(),
                $this->context->serializeState()
            );

            error_log("[TutorialProgressManager] Jumped to step {$targetStepId} for session {$sessionId}");

            return true;

        } catch (\Exception $e) {
            throw new TutorialStepException(
                "Failed to jump to step '{$targetStepId}'",
                ['step_id' => $targetStepId, 'session_id' => $sessionId],
                0,
                $e
            );
        }
    }

    /**
     * Calculate step position (1-indexed for display)
     *
     * @param string $stepId Step identifier
     * @param string $version Tutorial version
     * @return int Step position (1-based)
     */
    public function calculateStepPosition(string $stepId, string $version): int
    {
        return $this->stepRepository->calculateStepPosition($stepId, $version);
    }

    /**
     * Apply prerequisites for a step
     *
     * Sets up the player state required for this step (movement points, etc.)
     *
     * @param AbstractStep $step Step to apply prerequisites for
     */
    private function applyStepPrerequisites(AbstractStep $step): void
    {
        $config = $step->getConfig();
        $prerequisites = $config['prerequisites'] ?? null;

        $player = $this->context->getPlayer();
        $playerId = $player->id;

        // Ensure prerequisites are met (if any)
        if ($prerequisites) {
            $this->context->ensurePrerequisites($prerequisites);
            error_log("[TutorialProgressManager] Applied prerequisites for step {$step->getStepId()}: " . json_encode($prerequisites));
        }

        // Set consume_movements flag in session from prerequisites OR context_changes
        // (consume_movements can be in either location depending on how it was configured)
        // This must happen regardless of whether prerequisites exist
        $consumeMovements = $prerequisites['consume_movements'] ?? $config['context_changes']['consume_movements'] ?? null;

        if ($consumeMovements !== null) {
            $_SESSION['tutorial_consume_movements'] = (bool)$consumeMovements;
            error_log("[TutorialProgressManager] SET SESSION tutorial_consume_movements = " . ($consumeMovements ? 'true' : 'false'));
        }
    }

    /**
     * Prepare step data for client (with placeholders replaced)
     *
     * @param AbstractStep $step Step object
     * @param string $version Tutorial version
     * @return array Step data ready for client
     */
    private function prepareStepForClient(AbstractStep $step, string $version): array
    {
        $stepData = $step->getData();

        // Calculate step position
        $stepData['step_position'] = $this->calculateStepPosition($step->getStepId(), $version);

        // Add context-aware data
        $stepData['tutorial_xp'] = $this->context->getTutorialXP();
        $stepData['tutorial_level'] = $this->context->getTutorialLevel();

        return $stepData;
    }

    /**
     * Load step by ID
     *
     * @param string $stepId Step identifier
     * @param string $version Tutorial version
     * @return AbstractStep|null Step object or null if not found
     */
    private function loadStep(string $stepId, string $version): ?AbstractStep
    {
        $stepData = $this->stepRepository->getStepById($stepId, $version);

        if (!$stepData) {
            return null;
        }

        return TutorialStepFactory::createFromData($stepData, $this->context);
    }

    /**
     * Get tutorial context
     *
     * @return TutorialContext
     */
    public function getContext(): TutorialContext
    {
        return $this->context;
    }
}
