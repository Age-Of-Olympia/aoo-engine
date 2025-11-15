<?php

namespace App\Tutorial\Steps\Movement;

use App\Tutorial\Steps\AbstractStep;
use App\Tutorial\TutorialContext;
use App\Tutorial\TutorialHelper;
use Classes\Player;

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
                // Check that player has used all movements // Loading it first, maybe better way to check ?
                $activePlayerId = TutorialHelper::getActivePlayerId();
                // Load player
                $player = new Player($activePlayerId);
                // Not working, wrong player (main character)
                //$player = $this->context->getPlayer();
                // Force fresh reload to get latest turn data from database
                $player->get_data();
                $player->get_caracs(); // This loads turn data from DB into $player->turn

                // Use getRemaining() which checks $player->turn (live data from DB)
                $mvtRemaining = $player->getRemaining('mvt');

                return $mvtRemaining === 0;

            case 'specific_count':
                // Check that player moved X times
                $requiredMoves = $this->config['required_moves'] ?? 1;
                $moveCount = $data['move_count'] ?? 0;
                return $moveCount >= $requiredMoves;

            case 'position':
                // Check that player is at a specific position
                $activePlayerId = TutorialHelper::getActivePlayerId();
                $player = new Player($activePlayerId);
                $player->get_data();
                $player->getCoords();

                $requiredX = $this->config['validation_params']['x'] ?? null;
                $requiredY = $this->config['validation_params']['y'] ?? null;

                if ($requiredX === null || $requiredY === null) {
                    error_log("[MovementStep] Position validation missing x or y parameters");
                    return false;
                }

                $currentX = $player->coords->x ?? null;
                $currentY = $player->coords->y ?? null;

                $isAtPosition = ($currentX == $requiredX && $currentY == $requiredY);

                error_log("[MovementStep] Position validation: player at ({$currentX},{$currentY}), required ({$requiredX},{$requiredY}), result: " . ($isAtPosition ? 'TRUE' : 'FALSE'));

                return $isAtPosition;

            default:
                return false;
        }
    }

    /**
     * Get validation hint (public so TutorialManager can generate dynamic hints)
     */
    public function getValidationHint(): string
    {
        $validationType = $this->config['validation_type'] ?? 'any_movement';

        switch ($validationType) {
            case 'movements_depleted':
                // Use authoritative source for player ID (consistent with validate())
                $activePlayerId = TutorialHelper::getActivePlayerId();
                $player = new Player($activePlayerId);
                $player->get_data();
                // Get live movement count from database
                $player->get_caracs();
                $mvtRemaining = $player->getRemaining('mvt');
                return "Il vous reste encore {$mvtRemaining} mouvement(s). Continuez à vous déplacer!";

            case 'specific_count':
                $requiredMoves = $this->config['required_moves'] ?? 1;
                return "Déplacez-vous {$requiredMoves} fois pour continuer.";

            case 'position':
                $requiredX = $this->config['validation_params']['x'] ?? '?';
                $requiredY = $this->config['validation_params']['y'] ?? '?';
                return "Déplacez-vous sur la case ({$requiredX},{$requiredY}) marquée en jaune.";

            default:
                return parent::getValidationHint();
        }
    }
}
