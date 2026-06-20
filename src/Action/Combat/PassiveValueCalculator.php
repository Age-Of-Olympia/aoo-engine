<?php

namespace App\Action\Combat;

use App\Entity\ActionPassive;
use Classes\Player;

/**
 * Pure computation of a passive's contributed value from its config (carac kind
 * + value) and a player's current state. No DB / no entity manager, so it is
 * shared by the live PlayerPassiveService and the simulator's stand-in. The
 * caller is responsible for having the player's caracs loaded.
 */
final class PassiveValueCalculator
{
    public function compute(ActionPassive $passive, Player $player): int
    {
        $value = $passive->getValue();

        return match ($passive->getCarac()) {
            'fixed' => (int) $value,
            'lostPV' => (int) floor(($player->caracs->pv - $player->getRemaining('pv')) * $value),
            'effects' => (int) floor(count($player->playerEffectService->getEffectsByPlayerId($player->getId())) * $value),
            default => (int) floor(($player->caracs->{$passive->getCarac()} ?? 0) * $value),
        };
    }
}
