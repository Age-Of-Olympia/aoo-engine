<?php

namespace App\Service\Action\Xp;

use App\Enum\EntityCategory;
use App\Interface\MutatesActorsInterface;
use App\Interface\XpCalculatorInterface;
use App\Interface\ActorInterface;

/**
 * Combat XP, inherited by the whole attack family (melee, distance, technique,
 * spell, curses). Ported verbatim from AttackAction::calculate*Xp, with the
 * former literals lifted into params: base (was ACTION_XP), min, reducedXp (the
 * "1" floor for same-faction / inactive targets), diffCap (rank gap above which
 * no XP), targetFail.
 *
 * A structure (building, wall, tree, stone…) is no opponent: a hit on it
 * earns the training XP, spends one energie like training does, and the
 * structure itself earns nothing. Its rank is read like any target's, so
 * structures that gain levels will raise the rank bonus by themselves.
 */
final class AttackXpCalculator implements XpCalculatorInterface, MutatesActorsInterface
{
    public function calculate(array $params, bool $success, ActorInterface $actor, ActorInterface $target): array
    {
        $params = array_merge(self::defaults(), $params);

        if (self::isStructure($target)) {
            return ['actor' => $success ? TrainXpCalculator::sideXp(self::structureKnobs($params), $actor, $target) : 0, 'target' => 0];
        }

        return ['actor' => $this->actorXp($params, $success, $actor, $target), 'target' => $success ? 0 : $params['targetFail']];
    }

    public function applyMutations(array $params, bool $success, ActorInterface $actor, ActorInterface $target): void
    {
        if ($success && self::isStructure($target)) {
            $actor->putEnergie(-1);
        }
    }

    private static function isStructure(ActorInterface $target): bool
    {
        if (!isset($target->data)) {
            $target->get_data();
        }

        return EntityCategory::fromPlayerType($target->data->player_type ?? null)->isStructure();
    }

    /**
     * The training knobs, under the structure* keys of this rule.
     *
     * @param array<string, int> $params
     * @return array{base: int, energieHighBonus: int, energieAnyBonus: int, rankBonus: int}
     */
    private static function structureKnobs(array $params): array
    {
        return [
            'base' => $params['structureBase'],
            'energieHighBonus' => $params['structureEnergieHighBonus'],
            'energieAnyBonus' => $params['structureEnergieAnyBonus'],
            'rankBonus' => $params['structureRankBonus'],
        ];
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
        return [
            'base' => 5, 'min' => 2, 'reducedXp' => 1, 'diffCap' => 3, 'targetFail' => 2,
            'structureBase' => 1, 'structureEnergieHighBonus' => 1, 'structureEnergieAnyBonus' => 1, 'structureRankBonus' => 1,
        ];
    }
}
