<?php

namespace App\Service\Action\Xp;

use App\Interface\XpCalculatorInterface;
use App\Interface\ActorInterface;

/**
 * A flat reward: actor/target XP on success vs failure. Covers the non-combat
 * types whose XP was a constant (buff 2, heal 3, pray 1, rest 0, run/search 1).
 */
final class FixedXpCalculator implements XpCalculatorInterface
{
    public function calculate(array $params, bool $success, ActorInterface $actor, ActorInterface $target): array
    {
        $params = array_merge(self::defaults(), $params);

        return [
            'actor' => $success ? $params['actorSuccess'] : $params['actorFail'],
            'target' => $success ? $params['targetSuccess'] : $params['targetFail'],
        ];
    }

    public static function defaults(): array
    {
        return ['actorSuccess' => 0, 'actorFail' => 0, 'targetSuccess' => 0, 'targetFail' => 0];
    }
}
