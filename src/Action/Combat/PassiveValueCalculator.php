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
    /**
     * Special "carac" kinds the compute understands, beyond a plain CARACS code
     * (the `default` branch reads $player->caracs->{carac}). Single source for the
     * passive editor's carac dropdown. 'advantage' is a roll flag read by
     * {@see \App\Action\Condition\AbstractComputeCondition}, not a value here.
     *
     * @var array<string, string>
     */
    public const SPECIAL_CARACS = [
        'fixed' => 'Valeur fixe',
        'lostPV' => 'PV perdus',
        'effects' => "Nombre d'effets actifs",
        'advantage' => 'Avantage (jet)',
    ];

    public function compute(ActionPassive $passive, Player $player): int
    {
        $value = $passive->getValue();

        // The 'fixed' / 'lostPV' / 'effects' cases are the SPECIAL_CARACS keys.
        return match ($passive->getCarac()) {
            'fixed' => (int) $value,
            'lostPV' => (int) floor(($player->caracs->pv - $player->getRemaining('pv')) * $value),
            'effects' => (int) floor(count($player->playerEffectService->getEffectsByPlayerId($player->getId())) * $value),
            default => (int) floor(($player->caracs->{$passive->getCarac()} ?? 0) * $value),
        };
    }
}
