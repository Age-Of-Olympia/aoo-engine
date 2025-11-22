<?php

namespace App\Tutorial\Steps;

use App\Tutorial\TutorialContext;

/**
 * UI Interaction step - Validates UI interactions (button clicks, panel opens, etc.)
 *
 * Used when tutorial needs to validate that player performed a specific UI action
 * like opening a panel, clicking a button, toggling a setting, etc.
 */
class UIInteractionStep extends AbstractStep
{
    /**
     * Validate UI interaction
     *
     * Supported validation types:
     * - ui_panel_opened: Check if a specific UI panel was opened
     * - ui_button_clicked: Check if a specific button was clicked
     * - ui_setting_changed: Check if a setting was toggled
     * - ui_element_hidden: Check if an element was hidden/removed
     * - ui_interaction: Check if a specific element was clicked (generic)
     */
    public function validate(array $data): bool
    {
        $validationType = $this->config['validation_type'] ?? 'ui_panel_opened';

        switch ($validationType) {
            case 'ui_panel_opened':
                // Check that the specified panel was opened
                // This is validated client-side and sent to us
                $requiredPanel = $this->config['validation_params']['panel'] ?? null;
                $panelVisible = $data['panel_visible'] ?? false;
                $panel = $data['panel'] ?? null;

                // Support characteristics, actions, and inventory panels
                if ($requiredPanel === 'characteristics') {
                    return $panel === 'characteristics' && $panelVisible === true;
                } elseif ($requiredPanel === 'actions') {
                    return $panel === 'actions' && $panelVisible === true;
                } elseif ($requiredPanel === 'inventory') {
                    return $panel === 'inventory' && $panelVisible === true;
                }

                return false;

            case 'ui_button_clicked':
                // Check that the specified button was clicked
                $requiredButton = $this->config['validation_params']['button'] ?? null;
                $clickedButton = $data['button'] ?? null;

                return $requiredButton && $clickedButton === $requiredButton;

            case 'ui_setting_changed':
                // Check that a setting was changed to a specific value
                $requiredSetting = $this->config['validation_params']['setting'] ?? null;
                $requiredValue = $this->config['validation_params']['value'] ?? null;
                $actualValue = $data['setting_value'] ?? null;

                return $requiredSetting &&
                       isset($data['setting']) &&
                       $data['setting'] === $requiredSetting &&
                       $actualValue === $requiredValue;

            case 'ui_element_hidden':
                // Check that an element was hidden
                $requiredElement = $this->config['validation_params']['element'] ?? null;
                $element = $data['element'] ?? null;
                $isHidden = $data['is_hidden'] ?? false;

                return $requiredElement && $element === $requiredElement && $isHidden === true;

            case 'ui_interaction':
                // Generic UI interaction - check if a specific element was clicked
                $requiredElement = $this->config['validation_params']['element_clicked'] ?? null;
                $clickedElement = $data['element_clicked'] ?? null;

                error_log("[UIInteractionStep] Validating ui_interaction: required={$requiredElement}, clicked={$clickedElement}");

                return $requiredElement && $clickedElement === $requiredElement;

            default:
                return false;
        }
    }

    /**
     * Get validation hint (public so TutorialManager can generate dynamic hints)
     */
    public function getValidationHint(): string
    {
        $validationType = $this->config['validation_type'] ?? 'ui_panel_opened';

        switch ($validationType) {
            case 'ui_panel_opened':
                $panel = $this->config['validation_params']['panel'] ?? 'le panneau';
                return "Ouvrez {$panel} pour continuer.";

            case 'ui_button_clicked':
                $button = $this->config['validation_params']['button'] ?? 'le bouton';
                return "Cliquez sur {$button} pour continuer.";

            case 'ui_setting_changed':
                $setting = $this->config['validation_params']['setting'] ?? 'le paramètre';
                return "Modifiez {$setting} pour continuer.";

            case 'ui_interaction':
                // Use the validation_hint from config if available
                return $this->config['validation_hint'] ?? "Cliquez sur l'élément indiqué pour continuer.";

            default:
                return parent::getValidationHint();
        }
    }

    /**
     * UI interaction steps always require validation
     */
    public function requiresValidation(): bool
    {
        return $this->config['requires_validation'] ?? true;
    }
}
