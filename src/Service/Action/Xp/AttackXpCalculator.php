<?php

namespace App\Service\Action\Xp;

use App\Interface\ActorInterface;

/**
 * Combat XP, inherited by the whole attack family (melee, distance, technique,
 * spell, curses). Ported verbatim from AttackAction::calculate*Xp, with the
 * former literals lifted into params: base (was ACTION_XP), min, reducedXp (the
 * "1" floor for same-faction / inactive targets), diffCap (rank gap above which
 * no XP), targetFail.
 */
final class AttackXpCalculator implements XpCalculatorInterface
{
    public function calculate(array $params, bool $success, ActorInterface $actor, ActorInterface $target): array
    {
        $params = array_merge(self::defaults(), $params);

        return ['actor' => $this->actorXp($params, $success, $actor, $target), 'target' => $success ? 0 : $params['targetFail']];
    }

    /**
     * @param array<string, int> $params
     */
    private function actorXp(array $params, bool $success, ActorInterface $actor, ActorInterface $target): int
    {
        if (!$success) {
            return 0;
        }

        if (!isset($actor->data)) {
            $actor->get_data();
        }
        if (!isset($target->data)) {
            $target->get_data();
        }

        $diff = $actor->data->rank - $target->data->rank;
        $reducAction = $actor->get_upgrades()->a;
        $xp = $params['base'] - $diff - $reducAction;

        if ($xp < $params['min']) {
            $xp = $params['min'];
        }
        if ($actor->data->faction != '' && $actor->data->faction == $target->data->faction) {
            $xp = $params['reducedXp'];
        }
        if ($actor->data->secretFaction != '' && $actor->data->secretFaction == $target->data->secretFaction) {
            $xp = $params['reducedXp'];
        }
        if ($target->data->isInactive) {
            $xp = $params['reducedXp'];
        }
        if ($diff > $params['diffCap']) {
            $xp = 0;
        }

        return (int) $xp;
    }

    public static function defaults(): array
    {
        return ['base' => 5, 'min' => 2, 'reducedXp' => 1, 'diffCap' => 3, 'targetFail' => 2];
    }
}
