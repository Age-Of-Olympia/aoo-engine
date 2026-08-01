<?php

namespace App\Service\Action\Xp;

use App\Interface\ActorInterface;

/**
 * Computes the XP an action grants, for one "mode" (a family of XP rules). The
 * algorithm lives in code; its tuning constants come from $params (configured
 * per action type in action_type_xp). A fixed-reward mode is just constants; the
 * combat/steal/train modes are real algorithms reading rank/faction/energie.
 */
interface XpCalculatorInterface
{
    /**
     * @param array<string, int> $params the configured knobs (merged over defaults())
     * @return array{actor: int, target: int}
     */
    public function calculate(array $params, bool $success, ActorInterface $actor, ActorInterface $target): array;

    /**
     * The knobs this mode reads, with their built-in default — also the key set
     * the editor renders.
     *
     * @return array<string, int>
     */
    public static function defaults(): array;
}
