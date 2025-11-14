<?php

namespace App\Tutorial\Steps;

use App\Tutorial\TutorialContext;

/**
 * Base class for all tutorial steps
 *
 * Each step represents one instruction/task in the tutorial sequence.
 * Steps are loaded from tutorial_configurations table and instantiated dynamically.
 */
abstract class AbstractStep
{
    protected TutorialContext $context;
    protected array $config;
    protected int $stepNumber;
    protected string $stepType;
    protected string $title;
    protected int $xpReward;

    public function __construct(
        TutorialContext $context,
        int $stepNumber,
        string $stepType,
        string $title,
        array $config,
        int $xpReward = 0
    ) {
        $this->context = $context;
        $this->stepNumber = $stepNumber;
        $this->stepType = $stepType;
        $this->title = $title;
        $this->config = $config;
        $this->xpReward = $xpReward;
    }

    /**
     * Get step data for rendering on frontend
     *
     * @return array Data to send to client
     */
    public function getData(): array
    {
        return [
            'step_number' => $this->stepNumber,
            'step_type' => $this->stepType,
            'title' => $this->title,
            'text' => $this->getText(),
            'target_selector' => $this->getTargetSelector(),
            'tooltip_position' => $this->getTooltipPosition(),
            'requires_validation' => $this->requiresValidation(),
            'validation_hint' => $this->getValidationHint(),
            'xp_reward' => $this->xpReward,
            'interaction_mode' => $this->getInteractionMode(),
            'config' => $this->config
        ];
    }

    /**
     * Get step text (with placeholder replacement)
     *
     * @return string
     */
    protected function getText(): string
    {
        $text = $this->config['text'] ?? '';
        return $this->replacePlaceholders($text);
    }

    /**
     * Replace placeholders in text
     *
     * Supported placeholders:
     * - {PLAYER_NAME}
     * - {CURRENT_XP}
     * - {CURRENT_LEVEL}
     * - {CURRENT_PI}
     * - {CURRENT_MVT}
     * - etc.
     */
    protected function replacePlaceholders(string $text): string
    {
        $player = $this->context->getPlayer();

        $replacements = [
            '{PLAYER_NAME}' => $player->data->name ?? 'Aventurier',
            '{CURRENT_XP}' => (string)$this->context->getTutorialXP(),
            '{CURRENT_LEVEL}' => (string)$this->context->getTutorialLevel(),
            '{CURRENT_PI}' => (string)$this->context->getTutorialPI(),
            '{CURRENT_MVT}' => (string)($player->data->mvt ?? 0),
            '{MAX_MVT}' => (string)($player->data->mvt ?? 4),
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $text
        );
    }

    /**
     * Get element selector to highlight
     */
    protected function getTargetSelector(): ?string
    {
        return $this->config['target_selector'] ?? null;
    }

    /**
     * Get tooltip position (top, bottom, left, right, center)
     */
    protected function getTooltipPosition(): string
    {
        return $this->config['tooltip_position'] ?? 'bottom';
    }

    /**
     * Does this step require validation before advancing?
     */
    public function requiresValidation(): bool
    {
        return $this->config['requires_validation'] ?? false;
    }

    /**
     * Get validation hint for user (public so TutorialManager can generate dynamic hints)
     */
    public function getValidationHint(): string
    {
        return $this->config['validation_hint'] ?? '';
    }

    /**
     * Validate step completion
     *
     * Override in subclasses for steps that require validation
     *
     * @param array $data Validation data from client
     * @return bool True if validation passes
     */
    public function validate(array $data): bool
    {
        // Default: no validation required
        return true;
    }

    /**
     * Get interaction mode (blocking, semi-blocking, or open)
     *
     * Can be overridden in config or by subclasses
     */
    protected function getInteractionMode(): string
    {
        // Explicit override in config
        if (isset($this->config['interaction_mode'])) {
            return $this->config['interaction_mode'];
        }

        // Default based on step type
        $defaults = [
            'info' => 'blocking',
            'welcome' => 'blocking',
            'dialog' => 'blocking',
            'ui_interaction' => 'semi-blocking',
            'movement' => 'semi-blocking',
            'movement_limit' => 'semi-blocking',
            'action' => 'semi-blocking',
            'action_intro' => 'blocking',
            'combat' => 'semi-blocking',
            'combat_intro' => 'blocking',
            'exploration' => 'open'
        ];

        return $defaults[$this->stepType] ?? 'blocking';
    }

    /**
     * Called when step is completed
     *
     * Awards XP and executes any step-specific completion logic
     */
    public function onComplete(TutorialContext $context): void
    {
        // Award XP
        if ($this->xpReward > 0) {
            $context->awardXP($this->xpReward);
        }

        // Apply context changes if specified
        if (isset($this->config['context_changes'])) {
            $this->applyContextChanges($context, $this->config['context_changes']);
        }

        // Apply prerequisites for NEXT step if specified
        // This ensures resources are ready for the following step
        if (isset($this->config['prepare_next_step'])) {
            $this->prepareNextStep($context, $this->config['prepare_next_step']);
        }
    }

    /**
     * Apply context changes from config
     */
    protected function applyContextChanges(TutorialContext $context, array $changes): void
    {
        if (isset($changes['unlimited_mvt'])) {
            $context->setState('unlimited_mvt', $changes['unlimited_mvt']);
        }

        if (isset($changes['unlimited_actions'])) {
            $context->setState('unlimited_actions', $changes['unlimited_actions']);
        }

        // Control whether movements are consumed when player moves
        // By default (legacy), tutorial does NOT consume movements
        // Set consume_movements: true to enable movement consumption
        if (isset($changes['consume_movements'])) {
            $consumeMovements = (bool) $changes['consume_movements'];
            $context->setState('consume_movements', $consumeMovements);
            $_SESSION['tutorial_consume_movements'] = $consumeMovements;
            error_log("[AbstractStep] Set consume_movements: " . ($consumeMovements ? 'true' : 'false'));
        }

        if (isset($changes['set_mvt_limit'])) {
            $context->setState('mvt_limit', $changes['set_mvt_limit']);
            // Use ensurePrerequisites() to properly SET movements (not add)
            // This goes through the turn/bonus system, not direct data->mvt
            $context->ensurePrerequisites([
                'mvt' => $changes['set_mvt_limit'],
                'auto_restore' => true
            ]);
        }

        if (isset($changes['set_action_limit'])) {
            $context->setState('action_limit', $changes['set_action_limit']);
            // Apply to player
            $context->getPlayer()->data->a = $changes['set_action_limit'];
        }
    }

    /**
     * Prepare resources for next step (called after current step completes)
     *
     * This ensures the next step has the resources it needs
     * Delegates to TutorialContext for proper resource management
     */
    protected function prepareNextStep(TutorialContext $context, array $preparation): void
    {
        // Delegate to TutorialContext which has proper movement/action restoration logic
        $context->prepareForNextStep($preparation);
    }

    /**
     * Get step number
     */
    public function getStepNumber(): int
    {
        return $this->stepNumber;
    }

    /**
     * Get step type
     */
    public function getStepType(): string
    {
        return $this->stepType;
    }

    /**
     * Get title
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Get XP reward
     */
    public function getXPReward(): int
    {
        return $this->xpReward;
    }
}
