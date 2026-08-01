<?php

namespace App\Interface;

use App\Interface\XpCalculatorInterface;
use App\Entity\Action;

use App\Interface\ActorInterface;

/**
 * Opt-in companion to {@see XpCalculatorInterface} for the rare XP rule that, beyond
 * computing XP, also changes the actors' state — today only training, which
 * spends one energie per side. This is a distinct fighter mutation, not a
 * consequence of the XP value itself.
 *
 * Keeping it off the XpCalculatorInterface interface lets calculate() stay a pure
 * function of its inputs — a what-if/preview caller can compute XP without
 * spending resources. The executor invokes applyMutations() exactly once, at
 * the real mutation point, for calculators that declare it.
 */
interface MutatesActorsInterface
{
    /**
     * @param array<string, int> $params the configured knobs (merged over defaults())
     */
    public function applyMutations(array $params, bool $success, ActorInterface $actor, ActorInterface $target): void;
}
