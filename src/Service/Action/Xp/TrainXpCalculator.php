<?php

namespace App\Service\Action\Xp;

use App\Interface\ActorInterface;

/**
 * Training XP, ported from TrainAction::calculate*Xp. Both fighters gain base XP
 * plus bonuses for spare energie and for sparring up in rank. Both also spend
 * one energie point — that mutation is the {@see XpSideEffect}, applied by the
 * executor after the (pure) XP is computed, so the energie read by calculate()
 * is always the pre-spend value (matching the old in-calculateXp behaviour).
 * base / energieHighBonus / energieAnyBonus / rankBonus are knobs.
 */
final class TrainXpCalculator implements XpCalculator, XpSideEffect
{
    public function calculate(array $params, bool $success, ActorInterface $actor, ActorInterface $target): array
    {
        $params = array_merge(self::defaults(), $params);

        return [
            'actor' => $this->sideXp($params, $actor, $target),
            'target' => $this->sideXp($params, $target, $actor),
        ];
    }

    public function applySideEffects(array $params, bool $success, ActorInterface $actor, ActorInterface $target): void
    {
        $actor->putEnergie(-1);
        $target->putEnergie(-1);
    }

    /**
     * XP for $self sparring against $other.
     *
     * @param array<string, int> $params
     */
    private function sideXp(array $params, ActorInterface $self, ActorInterface $other): int
    {
        $energie = $self->data->energie;
        $xp = $params['base'];
        if ($energie > 2) {
            $xp += $params['energieHighBonus'];
        }
        if ($energie > 0) {
            $xp += $params['energieAnyBonus'];
        }
        if ($self->data->rank < $other->data->rank) {
            $xp += $params['rankBonus'];
        }

        return (int) $xp;
    }

    public static function defaults(): array
    {
        return ['base' => 1, 'energieHighBonus' => 1, 'energieAnyBonus' => 1, 'rankBonus' => 1];
    }
}
