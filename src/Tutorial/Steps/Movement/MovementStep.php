<?php

namespace App\Tutorial\Steps\Movement;

use App\Tutorial\Steps\AbstractStep;
use App\Tutorial\TutorialContext;

/**
 * Movement step - Validates that player has moved
 *
 * Used to teach player about movement mechanics
 */
class MovementStep extends AbstractStep
{
    /**
     * Movement steps require validation
     */
    public function requiresValidation(): bool
    {
        return true;
    }

    /**
     * Validate that player has moved
     */
    public function validate(array $data): bool
    {
        $validationType = $this->config['validation_type'] ?? 'any_movement';

        switch ($validationType) {
            case 'any_movement':
                // Just check that player moved at all
                return isset($data['action']) && $data['action'] === 'move';

            case 'movements_depleted':
                // Check that player has used all movements
                $player = $this->context->getPlayer();
                $mvtRemaining = $player->data->mvt ?? 0;
                return $mvtRemaining === 0;

            case 'specific_count':
                // Check that player moved X times
                $requiredMoves = $this->config['required_moves'] ?? 1;
                $moveCount = $data['move_count'] ?? 0;
                return $moveCount >= $requiredMoves;

            default:
                return false;
        }
    }

    /**
     * Get validation hint
     */
    protected function getValidationHint(): string
    {
        $validationType = $this->config['validation_type'] ?? 'any_movement';

        switch ($validationType) {
            case 'movements_depleted':
                $player = $this->context->getPlayer();
                $mvtRemaining = $player->data->mvt ?? 0;
                return "Il vous reste encore {$mvtRemaining} mouvement(s). Continuez à vous déplacer!";

            case 'specific_count':
                $requiredMoves = $this->config['required_moves'] ?? 1;
                return "Déplacez-vous {$requiredMoves} fois pour continuer.";

            default:
                return parent::getValidationHint();
        }
    }
}
