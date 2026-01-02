<?php

namespace App\Tutorial\Steps\Actions;

use App\Tutorial\Steps\AbstractStep;
use App\Tutorial\TutorialContext;

/**
 * ActionStep - Validates that player used a specific action
 *
 * Validation types:
 * - action_used: Player clicked an action button
 * - action_available: Check if player has a specific action available
 */
class ActionStep extends AbstractStep
{
    /**
     * Validate that the required action was used
     */
    public function validate(array $data): bool
    {
        // Write debug to a file we can access
        $debug = "[ActionStep] validate() called for step: " . ($this->stepId ?? 'unknown') . "\n";
        $debug .= "[ActionStep] This class: " . get_class($this) . "\n";
        $debug .= "[ActionStep] Data received: " . json_encode($data) . "\n";

        $validationType = $this->config['validation_type'] ?? 'action_used';
        $debug .= "[ActionStep] validation_type: " . $validationType . "\n";

        file_put_contents('/var/www/html/tmp/action_debug.log', $debug, FILE_APPEND);

        switch ($validationType) {
            case 'action_used':
                $result = $this->validateActionUsed($data);
                file_put_contents('/var/www/html/tmp/action_debug.log', "[ActionStep] validateActionUsed returned: " . ($result ? 'TRUE' : 'FALSE') . "\n", FILE_APPEND);
                return $result;

            case 'action_available':
                return $this->validateActionAvailable($data);

            default:
                // Unknown validation type - pass by default
                return true;
        }
    }

    /**
     * Validate that player used the required action
     */
    protected function validateActionUsed(array $data): bool
    {
        // Check for action_name in validation_params first (new format), then root level (legacy)
        $requiredAction = $this->config['validation_params']['action_name']
                       ?? $this->config['action_name']
                       ?? null;

        // TEMPORARY DEBUG LOGGING
        $debug = "[ActionStep] validateActionUsed called\n";
        $debug .= "[ActionStep] Step ID: " . ($this->stepId ?? 'unknown') . "\n";
        $debug .= "[ActionStep] Required action: " . json_encode($requiredAction) . "\n";
        $debug .= "[ActionStep] Received data: " . json_encode($data) . "\n";
        $debug .= "[ActionStep] validation_params: " . json_encode($this->config['validation_params'] ?? []) . "\n";
        file_put_contents('/var/www/html/tmp/action_debug.log', $debug, FILE_APPEND);

        if (!$requiredAction) {
            error_log("[ActionStep] No required action, returning true");
            // No specific action required
            return true;
        }

        // Check if the action_name from validation data matches
        $usedAction = $data['action_name'] ?? null;
        file_put_contents('/var/www/html/tmp/action_debug.log', "[ActionStep] Used action: " . json_encode($usedAction) . "\n", FILE_APPEND);
        file_put_contents('/var/www/html/tmp/action_debug.log', "[ActionStep] Comparing: " . json_encode($usedAction) . " === " . json_encode($requiredAction) . "\n", FILE_APPEND);

        if ($usedAction === $requiredAction) {
            file_put_contents('/var/www/html/tmp/action_debug.log', "[ActionStep] Exact match! Returning true\n", FILE_APPEND);
            return true;
        }

        // Special case: "attaquer" can be either "melee" or "distance" in backend
        // but button always has data-action="attaquer"
        if ($requiredAction === 'attaquer' && in_array($usedAction, ['melee', 'distance', 'attaquer'])) {
            return true;
        }

        // Special case: allow validation by melee/distance if that's what's configured
        if (in_array($requiredAction, ['melee', 'distance']) && $usedAction === 'attaquer') {
            return true;
        }

        return false;
    }

    /**
     * Validate that player has the required action available
     */
    protected function validateActionAvailable(array $data): bool
    {
        // Check for action_name in validation_params first (new format), then root level (legacy)
        $requiredAction = $this->config['validation_params']['action_name']
                       ?? $this->config['action_name']
                       ?? null;

        if (!$requiredAction) {
            return true;
        }

        // This would need to check player's available actions from database
        // For now, assume validation passes (can be enhanced later)
        return true;
    }

    /**
     * Get validation hint message
     */
    public function getValidationHint(): string
    {
        $validationType = $this->config['validation_type'] ?? 'action_used';

        // Use custom hint if provided
        if (!empty($this->config['validation_hint'])) {
            return $this->config['validation_hint'];
        }

        // Generate default hint based on validation type
        // Check for action_name in validation_params first (new format), then root level (legacy)
        $actionName = $this->config['validation_params']['action_name']
                   ?? $this->config['action_name']
                   ?? 'action';

        switch ($validationType) {
            case 'action_used':
                return "Utilisez l'action {$actionName} pour continuer.";

            case 'action_available':
                return "Vous devez avoir l'action {$actionName} disponible.";

            default:
                return "Effectuez l'action requise pour continuer.";
        }
    }
}
