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
                // Check that player has used all movements
                $player = TutorialHelper::loadActivePlayer(loadCaracs: true, throwOnFailure: false);

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
                $player = TutorialHelper::loadActivePlayer(loadCaracs: false, throwOnFailure: false);
                $player->getCoords();

                $requiredX = $this->config['validation_params']['x'] ?? null;
                $requiredY = $this->config['validation_params']['y'] ?? null;

                if ($requiredX === null || $requiredY === null) {
                    error_log("[MovementStep] Position validation missing x or y parameters");
                    return false;
                }

                $currentX = $player->coords->x ?? null;
                $currentY = $player->coords->y ?? null;

                // Use strict comparison for coordinates
                $isAtPosition = ($currentX === $requiredX && $currentY === $requiredY);

                error_log("[MovementStep] Position validation: player at ({$currentX},{$currentY}), required ({$requiredX},{$requiredY}), result: " . ($isAtPosition ? 'TRUE' : 'FALSE'));

                return $isAtPosition;

            case 'adjacent_to_position':
                // Check that player is adjacent to a specific position (including diagonals)
                $player = TutorialHelper::loadActivePlayer(loadCaracs: false, throwOnFailure: false);
                $player->getCoords();

                $targetX = $this->config['validation_params']['target_x'] ?? null;
                $targetY = $this->config['validation_params']['target_y'] ?? null;

                if ($targetX === null || $targetY === null) {
                    error_log("[MovementStep] Adjacent position validation missing target_x or target_y parameters");
                    return false;
                }

                $currentX = $player->coords->x ?? null;
                $currentY = $player->coords->y ?? null;

                // Validate coordinates are numeric
                if (!is_numeric($currentX) || !is_numeric($currentY) || !is_numeric($targetX) || !is_numeric($targetY)) {
                    error_log("[MovementStep] Invalid coordinate types for adjacent validation");
                    return false;
                }

                // Check if player is adjacent (Chebyshev distance = 1, allows all 8 directions including diagonals)
                $deltaX = abs((int)$currentX - (int)$targetX);
                $deltaY = abs((int)$currentY - (int)$targetY);
                $isAdjacent = ($deltaX <= 1 && $deltaY <= 1 && ($deltaX + $deltaY > 0));

                error_log("[MovementStep] Adjacent validation: player at ({$currentX},{$currentY}), target ({$targetX},{$targetY}), deltaX={$deltaX}, deltaY={$deltaY}, result: " . ($isAdjacent ? 'TRUE' : 'FALSE'));

                return $isAdjacent;

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
                // Get live movement count from database
                $player = TutorialHelper::loadActivePlayer(loadCaracs: true, throwOnFailure: false);
                $mvtRemaining = $player->getRemaining('mvt');
                return "Il vous reste encore {$mvtRemaining} mouvement(s). Continuez à vous déplacer!";

            case 'specific_count':
                $requiredMoves = $this->config['required_moves'] ?? 1;
                return "Déplacez-vous {$requiredMoves} fois pour continuer.";

            case 'position':
                $requiredX = $this->config['validation_params']['x'] ?? '?';
                $requiredY = $this->config['validation_params']['y'] ?? '?';
                return "Déplacez-vous sur la case ({$requiredX},{$requiredY}) marquée en jaune.";

            case 'adjacent_to_position':
                return $this->config['validation_hint'] ?? "Déplacez-vous sur une case adjacente à l'objectif.";

            default:
                return parent::getValidationHint();
        }
    }
}
