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
                // Check that player is at a specific position.
                //
                // TutorialStepRepository::convertRowToStepData emits
                // `target_x` / `target_y` from tutorial_step_validation
                // (same keys used by `adjacent_to_position`). Earlier
                // drafts of this branch read `x` / `y`, which silently
                // returned false for every DB-configured step. We keep
                // a `x` / `y` fallback so hand-built fixtures and any
                // legacy admin JSON that still uses the short keys keep
                // working.
                $player = TutorialHelper::loadActivePlayer(loadCaracs: false, throwOnFailure: false);
                $player->getCoords();

                $params = $this->config['validation_params'] ?? [];
                $requiredX = $params['target_x'] ?? $params['x'] ?? null;
                $requiredY = $params['target_y'] ?? $params['y'] ?? null;

                if ($requiredX === null || $requiredY === null) {
                    return false;
                }

                $currentX = $player->coords->x ?? null;
                $currentY = $player->coords->y ?? null;

                // Use strict comparison for coordinates
                $isAtPosition = ($currentX === $requiredX && $currentY === $requiredY);


                return $isAtPosition;

            case 'adjacent_to_position':
                // Check that player is adjacent to a specific position (including diagonals)
                $player = TutorialHelper::loadActivePlayer(loadCaracs: false, throwOnFailure: false);
                $player->getCoords();

                $targetX = $this->config['validation_params']['target_x'] ?? null;
                $targetY = $this->config['validation_params']['target_y'] ?? null;

                if ($targetX === null || $targetY === null) {
                    return false;
                }

                $currentX = $player->coords->x ?? null;
                $currentY = $player->coords->y ?? null;

                // Validate coordinates are numeric
                if (!is_numeric($currentX) || !is_numeric($currentY) || !is_numeric($targetX) || !is_numeric($targetY)) {
                    return false;
                }

                // Check if player is adjacent (Chebyshev distance = 1, allows all 8 directions including diagonals)
                $deltaX = abs((int)$currentX - (int)$targetX);
                $deltaY = abs((int)$currentY - (int)$targetY);
                $isAdjacent = ($deltaX <= 1 && $deltaY <= 1 && ($deltaX + $deltaY > 0));


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
                // Get live movement count from context player
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
                return parent::getValidationHint();
        }
    }
}
