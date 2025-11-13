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
     * Get validation hint for user
     */
    protected function getValidationHint(): string
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

        if (isset($changes['set_mvt_limit'])) {
            $context->setState('mvt_limit', $changes['set_mvt_limit']);
            // Apply to player
            $context->getPlayer()->data->mvt = $changes['set_mvt_limit'];
        }

        if (isset($changes['set_action_limit'])) {
            $context->setState('action_limit', $changes['set_action_limit']);
            // Apply to player
            $context->getPlayer()->data->a = $changes['set_action_limit'];
        }
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
