<?php

namespace App\Tutorial\Steps;

use App\Factory\PlayerFactory;
use App\Service\DialogService;
use App\Tutorial\TutorialContext;
use App\Tutorial\TutorialHelper;
use App\Tutorial\TutorialPlaceholderService;

/**
 * One step of the tutorial. Its step_type picks the family that validates it
 * (movement, ui, action, dialog); any other type only displays text.
 */
class TutorialStep
{
    private TutorialContext $context;
    /** @var array<string, mixed> */
    private array $config;
    private ?string $stepId;  // step_id (name)
    private ?string $nextStep;  // next_step_id
    private float $stepNumber;  // For display/ordering only
    private string $stepType;
    private string $title;
    private int $xpReward;

    /** @param array<string, mixed> $config */
    public function __construct(
        TutorialContext $context,
        float $stepNumber,
        string $stepType,
        string $title,
        array $config,
        int $xpReward = 0,
        ?string $stepId = null,
        ?string $nextStep = null
    ) {
        $this->context = $context;
        $this->stepNumber = $stepNumber;
        $this->stepType = $stepType;
        $this->title = $title;
        $this->config = $config;
        $this->xpReward = $xpReward;
        $this->stepId = $stepId;
        $this->nextStep = $nextStep;
    }

    /** @param array<string, mixed> $stepData */
    public static function fromData(array $stepData, TutorialContext $context): self
    {
        $config = is_array($stepData['config']) ? $stepData['config'] : json_decode($stepData['config'], true);

        return new self(
            $context,
            (float) ($stepData['step_number'] ?? 0),
            $stepData['step_type'],
            $stepData['title'],
            $config ?? [],
            (int) ($stepData['xp_reward'] ?? 0),
            $stepData['step_id'] ?? null,
            $stepData['next_step'] ?? null
        );
    }

    private function family(): string
    {
        return match ($this->stepType) {
            'movement', 'movement_intro', 'movement_limit' => 'movement',
            'ui_interaction' => 'ui',
            'action' => 'action',
            'dialog' => 'dialog',
            default => 'info',
        };
    }

    /**
     * Get step data for rendering on frontend
     *
     * @return array<string, mixed> Data to send to client
     */
    public function getData(): array
    {
        $data = [
            'step_id' => $this->stepId,
            'next_step' => $this->nextStep,
            'step_number' => $this->stepNumber,
            'step_type' => $this->stepType,
            'title' => $this->title,
            'text' => (string) ($this->config['text'] ?? ''),
            'target_selector' => $this->getTargetSelector(),
            'tooltip_position' => $this->getTooltipPosition(),
            'requires_validation' => $this->requiresValidation(),
            'validation_hint' => $this->getValidationHint(),
            'xp_reward' => $this->xpReward,
            'interaction_mode' => $this->getInteractionMode(),
            'config' => $this->config
        ];

        $placeholders = new TutorialPlaceholderService(PlayerFactory::legacy($this->context->getPlayer()->id));
        foreach (['title', 'text', 'validation_hint'] as $field) {
            $data[$field] = $placeholders->replacePlaceholders($data[$field]);
        }

        if ($this->family() === 'dialog' && isset($this->config['dialog_id'])) {
            $data['dialog'] = (new DialogService(true))->getDialogData($this->config['dialog_id'], $this->context->getPlayer());
        }

        return $data;
    }

    /**
     * Get element selector to highlight
     */
    private function getTargetSelector(): ?string
    {
        return $this->config['target_selector'] ?? null;
    }

    /**
     * Get tooltip position (top, bottom, left, right, center)
     */
    private function getTooltipPosition(): string
    {
        return $this->config['tooltip_position'] ?? 'bottom';
    }

    public function requiresValidation(): bool
    {
        return match ($this->family()) {
            'movement' => true,
            'ui' => $this->config['requires_validation'] ?? true,
            default => $this->config['requires_validation'] ?? false,
        };
    }

    public function getValidationHint(): string
    {
        return match ($this->family()) {
            'movement' => $this->movementHint(),
            'ui' => $this->uiHint(),
            'action' => $this->actionHint(),
            default => $this->configHint(),
        };
    }

    /**
     * @param array<string, mixed> $data Validation data from client
     */
    public function validate(array $data): bool
    {
        return match ($this->family()) {
            'movement' => $this->validateMovement($data),
            'ui' => $this->validateUi($data),
            'action' => $this->validateAction($data),
            default => true,
        };
    }

    private function configHint(): string
    {
        return $this->config['validation_hint'] ?? '';
    }

    /** @param array<string, mixed> $data */
    private function validateMovement(array $data): bool
    {
        $validationType = $this->config['validation_type'] ?? 'any_movement';

        switch ($validationType) {
            case 'any_movement':
                return isset($data['action']) && $data['action'] === 'move';

            case 'movements_depleted':
                // getRemaining() reads $player->turn, live from the database
                $player = TutorialHelper::loadActivePlayer(loadCaracs: true, throwOnFailure: false);

                return $player->getRemaining('mvt') === 0;

            case 'specific_count':
                $requiredMoves = $this->config['required_moves'] ?? 1;
                $moveCount = $data['move_count'] ?? 0;
                return $moveCount >= $requiredMoves;

            case 'position':
                // The repository emits target_x / target_y; x / y stay for
                // hand-built fixtures and old admin JSON.
                $player = TutorialHelper::loadActivePlayer(loadCaracs: false, throwOnFailure: false);
                $player->getCoords();

                $params = $this->config['validation_params'] ?? [];
                $requiredX = $params['target_x'] ?? $params['x'] ?? null;
                $requiredY = $params['target_y'] ?? $params['y'] ?? null;

                if ($requiredX === null || $requiredY === null) {
                    return false;
                }

                return ($player->coords->x ?? null) === $requiredX && ($player->coords->y ?? null) === $requiredY;

            case 'adjacent_to_position':
                $player = TutorialHelper::loadActivePlayer(loadCaracs: false, throwOnFailure: false);
                $player->getCoords();

                $targetX = $this->config['validation_params']['target_x'] ?? null;
                $targetY = $this->config['validation_params']['target_y'] ?? null;
                $currentX = $player->coords->x ?? null;
                $currentY = $player->coords->y ?? null;

                if (!is_numeric($currentX) || !is_numeric($currentY) || !is_numeric($targetX) || !is_numeric($targetY)) {
                    return false;
                }

                // Chebyshev distance 1: the eight neighbours, not the cell itself
                $deltaX = abs((int) $currentX - (int) $targetX);
                $deltaY = abs((int) $currentY - (int) $targetY);

                return $deltaX <= 1 && $deltaY <= 1 && ($deltaX + $deltaY > 0);

            default:
                return false;
        }
    }

    private function movementHint(): string
    {
        $validationType = $this->config['validation_type'] ?? 'any_movement';

        switch ($validationType) {
            case 'movements_depleted':
                $player = $this->context->getPlayer();
                $player->get_caracs();
                $mvtRemaining = $player->getRemaining('mvt');
                return "Il vous reste encore {$mvtRemaining} mouvement(s). Continuez à vous déplacer!";

            case 'specific_count':
                $requiredMoves = $this->config['required_moves'] ?? 1;
                return "Déplacez-vous {$requiredMoves} fois pour continuer.";

            case 'position':
                $params = $this->config['validation_params'] ?? [];
                $requiredX = $params['target_x'] ?? $params['x'] ?? '?';
                $requiredY = $params['target_y'] ?? $params['y'] ?? '?';
                return "Déplacez-vous sur la case ({$requiredX},{$requiredY}) marquée en jaune.";

            case 'adjacent_to_position':
                return $this->config['validation_hint'] ?? "Déplacez-vous sur une case adjacente à l'objectif.";

            default:
                return $this->configHint();
        }
    }

    /** @param array<string, mixed> $data */
    private function validateUi(array $data): bool
    {
        $validationType = $this->config['validation_type'] ?? 'ui_panel_opened';
        $params = $this->config['validation_params'] ?? [];

        switch ($validationType) {
            case 'ui_panel_opened':
                // The client checks the panel and reports it
                $requiredPanel = $params['panel'] ?? null;

                return in_array($requiredPanel, ['characteristics', 'actions', 'inventory'], true)
                    && ($data['panel'] ?? null) === $requiredPanel
                    && ($data['panel_visible'] ?? false) === true;

            case 'ui_button_clicked':
                $requiredButton = $params['button'] ?? null;

                return $requiredButton && ($data['button'] ?? null) === $requiredButton;

            case 'ui_setting_changed':
                $requiredSetting = $params['setting'] ?? null;

                return $requiredSetting
                    && isset($data['setting'])
                    && $data['setting'] === $requiredSetting
                    && ($data['setting_value'] ?? null) === ($params['value'] ?? null);

            case 'ui_element_hidden':
                $requiredElement = $params['element'] ?? null;

                return $requiredElement && ($data['element'] ?? null) === $requiredElement && ($data['is_hidden'] ?? false) === true;

            case 'ui_element_visible':
                $requiredElement = $params['element'] ?? null;

                return $requiredElement && ($data['element'] ?? null) === $requiredElement && ($data['is_visible'] ?? false) === true;

            case 'ui_interaction':
                $requiredElement = $params['element_clicked'] ?? null;

                return $requiredElement && ($data['element_clicked'] ?? null) === $requiredElement;

            default:
                return false;
        }
    }

    private function uiHint(): string
    {
        $validationType = $this->config['validation_type'] ?? 'ui_panel_opened';
        $params = $this->config['validation_params'] ?? [];

        switch ($validationType) {
            case 'ui_panel_opened':
                $panel = $params['panel'] ?? 'le panneau';
                return "Ouvrez {$panel} pour continuer.";

            case 'ui_button_clicked':
                $button = $params['button'] ?? 'le bouton';
                return "Cliquez sur {$button} pour continuer.";

            case 'ui_setting_changed':
                $setting = $params['setting'] ?? 'le paramètre';
                return "Modifiez {$setting} pour continuer.";

            case 'ui_element_visible':
                return $this->config['validation_hint'] ?? "Attendez que l'élément apparaisse.";

            case 'ui_interaction':
                return $this->config['validation_hint'] ?? "Cliquez sur l'élément indiqué pour continuer.";

            default:
                return $this->configHint();
        }
    }

    private function requiredAction(): ?string
    {
        // validation_params is the current format, the root key the old one
        return $this->config['validation_params']['action_name'] ?? $this->config['action_name'] ?? null;
    }

    /** @param array<string, mixed> $data */
    private function validateAction(array $data): bool
    {
        $validationType = $this->config['validation_type'] ?? 'action_used';
        $requiredAction = $this->requiredAction();

        // action_available has never checked anything; unknown types pass too
        if ($validationType !== 'action_used' || !$requiredAction) {
            return true;
        }

        $usedAction = $data['action_name'] ?? null;

        if ($usedAction === $requiredAction) {
            return true;
        }

        // A step asking for "attaquer" wants an attack of either kind (melee
        // or distance); the reverse covers bases where "attaquer" still exists.
        if ($requiredAction === 'attaquer' && in_array($usedAction, ['melee', 'distance', 'attaquer'])) {
            return true;
        }

        return in_array($requiredAction, ['melee', 'distance']) && $usedAction === 'attaquer';
    }

    private function actionHint(): string
    {
        if (!empty($this->config['validation_hint'])) {
            return $this->config['validation_hint'];
        }

        $actionName = $this->requiredAction() ?? 'action';

        return match ($this->config['validation_type'] ?? 'action_used') {
            'action_used' => "Utilisez l'action {$actionName} pour continuer.",
            'action_available' => "Vous devez avoir l'action {$actionName} disponible.",
            default => "Effectuez l'action requise pour continuer.",
        };
    }

    /**
     * Get interaction mode (blocking, semi-blocking, or open)
     *
     * Can be overridden in config or by subclasses
     */
    private function getInteractionMode(): string
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
            $context->prepareForNextStep($this->config['prepare_next_step']);
        }
    }

    /**
     * Apply context changes from config
     */
    /** @param array<string, mixed> $changes */
    private function applyContextChanges(TutorialContext $context, array $changes): void
    {
        // Control whether movements are consumed when player moves
        // By default (legacy), tutorial does NOT consume movements
        // Set consume_movements: true to enable movement consumption
        if (isset($changes['consume_movements'])) {
            $_SESSION['tutorial_consume_movements'] = $this->toBool($changes['consume_movements']);
        }

        if (isset($changes['set_mvt_limit'])) {
            // Use ensurePrerequisites() to properly SET movements (not add)
            // This goes through the turn/bonus system, not direct data->mvt
            $context->ensurePrerequisites([
                'mvt' => $changes['set_mvt_limit'],
                'auto_restore' => true
            ]);
        }

        if (isset($changes['set_action_limit'])) {
            // Apply to player
            $context->getPlayer()->data->a = $changes['set_action_limit'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get step ID
     */
    public function getStepId(): string
    {
        return $this->stepId ?? '';
    }

    /**
     * Get next step ID
     */
    public function getNextStep(): ?string
    {
        return $this->nextStep;
    }

    /**
     * Convert string or mixed value to boolean
     * Handles 'true'/'false' strings from database
     */
    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return strtolower($value) === 'true' || $value === '1';
        }
        return (bool) $value;
    }
}
