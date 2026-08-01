<?php

namespace App\Service\Action\Xp;

use App\Interface\ActorInterface;

/**
 * Steal XP, ported from StealAction::calculate*Xp: on success the actor gains
 * its action-XP against the target, capped (was MAX_XP_FOR_STEALING); the target
 * gains targetFail XP only when the steal fails.
 */
final class StealXpCalculator implements XpCalculatorInterface
{
    public function calculate(array $params, bool $success, ActorInterface $actor, ActorInterface $target): array
    {
        $params = array_merge(self::defaults(), $params);

        $actorXp = 0;
        if ($success) {
            $actorXp = (int) $actor->get_action_xp($target);
            if ($actorXp > $params['cap']) {
                $actorXp = $params['cap'];
            }
        }

        return ['actor' => $actorXp, 'target' => $success ? 0 : $params['targetFail']];
    }

    public static function defaults(): array
    {
        return ['cap' => 3, 'targetFail' => 2];
    }
}
