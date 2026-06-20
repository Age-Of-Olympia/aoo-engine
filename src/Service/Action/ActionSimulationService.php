<?php

namespace App\Service\Action;

use App\Action\Combat\CombatResolver;
use App\Action\Combat\RollSimulation;
use App\Entity\Action;
use App\Entity\ActionCondition;

final class ActionSimulationService
{
    private CombatResolver $resolver;

    public function __construct(?CombatResolver $resolver = null)
    {
        $this->resolver = $resolver ?? new CombatResolver();
    }

    /**
     * Preview the opposed roll of an action's compute condition from hypothetical
     * stats, without a Player or the DB. Returns null if the action has no roll.
     * Forced rolls make the preview deterministic; otherwise the resolver rolls.
     *
     * @param array<string, int> $actorStats  trait => value
     * @param array<string, int> $targetStats trait => value
     */
    public function simulateRoll(
        Action $action,
        array $actorStats,
        array $targetStats,
        ?int $forcedActorRoll = null,
        ?int $forcedTargetRoll = null,
    ): ?RollSimulation {
        $condition = $this->findComputeCondition($action);
        if ($condition === null) {
            return null;
        }

        $params = $condition->getParameters() ?? [];
        $actorTrait = (string) ($params['actorRollType'] ?? '');
        $targetTrait = (string) ($params['targetRollType'] ?? '');
        $actorBonus = (int) ($params['actorRollBonus'] ?? 0);
        $targetBonus = (int) ($params['targetRollBonus'] ?? 0);

        $actorValue = $this->traitValue($actorStats, $actorTrait);
        $targetValue = $this->traitValue($targetStats, $targetTrait);

        $actorRoll = $forcedActorRoll ?? array_sum($this->resolver->roll(
            $actorValue,
            (bool) ($params['actorAdvantage'] ?? false),
            (bool) ($params['actorDisadvantage'] ?? false),
        ));
        $targetRoll = $forcedTargetRoll ?? array_sum($this->resolver->roll(
            $targetValue,
            (bool) ($params['targetAdvantage'] ?? false),
            (bool) ($params['targetDisadvantage'] ?? false),
        ));

        $actorTotal = $actorRoll + $actorBonus;
        $targetTotal = $targetRoll + $targetBonus;

        return new RollSimulation(
            $actorTrait,
            $actorValue,
            $actorRoll,
            $actorBonus,
            $actorTotal,
            $targetTrait,
            $targetValue,
            $targetRoll,
            $targetBonus,
            $targetTotal,
            $this->resolver->resolve($actorTotal, $targetTotal)->hit,
        );
    }

    private function findComputeCondition(Action $action): ?ActionCondition
    {
        foreach ($action->getConditions() as $condition) {
            if (str_contains($condition->getConditionType(), 'Compute')) {
                return $condition;
            }
        }

        return null;
    }

    /**
     * @param array<string, int> $stats
     */
    private function traitValue(array $stats, string $trait): int
    {
        if ($trait === '') {
            return 0;
        }

        $parts = explode('/', $trait);
        if (count($parts) > 1) {
            return max(array_map(static fn(string $part): int => (int) ($stats[$part] ?? 0), $parts));
        }

        return (int) ($stats[$trait] ?? 0);
    }
}
