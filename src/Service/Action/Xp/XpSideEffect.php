<?php

namespace App\Service\Action\Xp;

use App\Interface\ActorInterface;

/**
 * Opt-in companion to {@see XpCalculator} for the rare XP rule that also mutates
 * the fighters (today only training, which spends one energie per side).
 *
 * Keeping this off the XpCalculator interface lets calculate() stay a pure
 * function of its inputs — a what-if/preview caller can compute XP without
 * spending resources. The executor invokes applySideEffects() exactly once, at
 * the real mutation point, for calculators that declare it.
 */
interface XpSideEffect
{
    /**
     * @param array<string, int> $params the configured knobs (merged over defaults())
     */
    public function applySideEffects(array $params, bool $success, ActorInterface $actor, ActorInterface $target): void;
}
