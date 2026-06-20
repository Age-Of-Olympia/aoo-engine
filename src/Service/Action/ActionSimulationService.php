<?php

namespace App\Service\Action;

use App\Action\Combat\CombatResolver;
use App\Action\Combat\DamageCalculator;
use App\Action\Combat\DamageModifiers;
use App\Action\Combat\DamageSimulation;
use App\Action\Combat\RollSimulation;
use App\Action\Condition\DistanceComputeCondition;
use App\Action\Condition\MeleeComputeCondition;
use App\Action\OutcomeInstruction\LifeLossOutcomeInstruction;
use App\Entity\Action;
use App\Entity\ActionCondition;
use App\Service\OutcomeInstructionService;

final class ActionSimulationService
{
    private CombatResolver $resolver;
    private DamageCalculator $damageCalculator;
    private ?OutcomeInstructionService $instructionService;

    public function __construct(
        ?CombatResolver $resolver = null,
        ?DamageCalculator $damageCalculator = null,
        ?OutcomeInstructionService $instructionService = null,
    ) {
        $this->resolver = $resolver ?? new CombatResolver();
        $this->damageCalculator = $damageCalculator ?? new DamageCalculator();
        // Lazily created in findLifeLossParams() so a roll-only simulation
        // (and its unit tests) never touch the entity manager / DB.
        $this->instructionService = $instructionService;
    }

    /**
     * Preview the base damage of the action's first LifeLoss outcome from
     * hypothetical stats. Null if the action deals no LifeLoss damage.
     * Excludes distance, crit, encaisse, passives and effects.
     *
     * @param array<string, int> $actorStats
     * @param array<string, int> $targetStats
     */
    public function simulateDamage(Action $action, array $actorStats, array $targetStats): ?DamageSimulation
    {
        $params = $this->findLifeLossParams($action);
        if ($params === null) {
            return null;
        }

        $actorDamages = $this->traitValue($actorStats, (string) ($params['actorDamagesTrait'] ?? ''));
        $targetDefense = $this->traitValue($targetStats, (string) ($params['targetDamagesTrait'] ?? ''));

        $modifiers = new DamageModifiers(
            bonusDamages: $this->bonusValue($params['bonusDamagesTrait'] ?? null, $actorStats),
            othersDamages: 0,
            agressivite: 0,
            faiblesse: 0,
            bonusDefense: $this->bonusValue($params['bonusDefenseTrait'] ?? null, $targetStats),
            othersDefense: 0,
            armure: 0,
            fragilite: 0,
        );

        return new DamageSimulation(
            $actorDamages,
            $targetDefense,
            $this->damageCalculator->additionalDamages($modifiers),
            $this->damageCalculator->totalDamage($actorDamages, $targetDefense, $modifiers),
        );
    }

    /**
     * Traits the simulation reads, so the UI can ask for their values.
     *
     * @return array{actor: array<int, string>, target: array<int, string>}
     */
    public function relevantTraits(Action $action): array
    {
        $actor = [];
        $target = [];

        $condition = $this->findComputeCondition($action);
        if ($condition !== null) {
            $params = $condition->getParameters() ?? [];
            $actor[] = (string) ($params['actorRollType'] ?? '');
            $target = array_merge($target, explode('/', (string) ($params['targetRollType'] ?? '')));
        }

        $lifeLoss = $this->findLifeLossParams($action);
        if ($lifeLoss !== null) {
            foreach (['actorDamagesTrait', 'bonusDamagesTrait'] as $key) {
                $actor[] = $this->traitName($lifeLoss[$key] ?? null);
            }
            foreach (['targetDamagesTrait', 'bonusDefenseTrait'] as $key) {
                $target[] = $this->traitName($lifeLoss[$key] ?? null);
            }
        }

        return [
            'actor' => array_values(array_unique(array_filter($actor))),
            'target' => array_values(array_unique(array_filter($target))),
        ];
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
        ?int $distance = null,
    ): ?RollSimulation {
        $condition = $this->findComputeCondition($action);
        if ($condition === null) {
            return null;
        }

        $type = $condition->getConditionType();
        $params = $condition->getParameters() ?? [];
        $actorTrait = (string) ($params['actorRollType'] ?? '');
        $targetTrait = (string) ($params['targetRollType'] ?? '');
        $actorBonus = (int) ($params['actorRollBonus'] ?? 0);
        $targetBonus = (int) ($params['targetRollBonus'] ?? 0);

        $actorValue = $this->traitValue($actorStats, $actorTrait);
        [$targetValue, $targetFormula] = $this->targetDefense($type, $targetStats, $targetTrait);

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

        $isDistance = str_contains($type, 'Distance');
        $distanceMalus = ($distance !== null && $isDistance) ? DistanceComputeCondition::distanceMalusFor($distance) : 0;
        $threshold = ($distance !== null && $distance > 1 && $isDistance) ? DistanceComputeCondition::distanceThresholdFor($distance) : 0;

        $actorTotal = $actorRoll + $actorBonus - $distanceMalus;
        $targetTotal = $targetRoll + $targetBonus;
        $reachedThreshold = $threshold === 0 || $actorTotal >= $threshold;

        return new RollSimulation(
            $actorTrait,
            $actorValue,
            $actorRoll,
            $actorBonus,
            $distanceMalus,
            $actorTotal,
            $targetTrait,
            $targetValue,
            $targetFormula,
            $targetRoll,
            $targetBonus,
            $targetTotal,
            $threshold,
            $reachedThreshold,
            $this->resolver->resolve($actorTotal, $targetTotal, $reachedThreshold)->hit,
        );
    }

    /**
     * The target's defense roll value and a human-readable derivation, using the
     * same per-condition-type formula as live combat.
     *
     * @param array<string, int> $stats
     * @return array{0: int, 1: string}
     */
    private function targetDefense(string $conditionType, array $stats, string $paramTrait): array
    {
        $cc = (int) ($stats['cc'] ?? 0);
        $agi = (int) ($stats['agi'] ?? 0);

        if (str_contains($conditionType, 'Distance')) {
            $value = DistanceComputeCondition::targetDefenseValue($cc, $agi);
            return [$value, "floor(max(¾·cc + ¼·agi, ¼·cc + ¾·agi)) avec cc={$cc}, agi={$agi} = {$value}"];
        }

        if (str_contains($conditionType, 'Melee')) {
            $value = MeleeComputeCondition::targetDefenseValue($cc, $agi);
            return [$value, "max(cc={$cc}, agi={$agi}) = {$value}"];
        }

        $value = $this->traitValue($stats, $paramTrait);
        $parts = explode('/', $paramTrait);
        if (count($parts) > 1) {
            $detail = implode(', ', array_map(static fn(string $part): string => $part . '=' . (int) ($stats[$part] ?? 0), $parts));
            return [$value, "max({$detail}) = {$value}"];
        }

        return [$value, "{$paramTrait} = {$value}"];
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

    /**
     * @return array<string, mixed>|null
     */
    private function findLifeLossParams(Action $action): ?array
    {
        $instructionService = $this->instructionService ??= new OutcomeInstructionService();
        foreach ($action->getOutcomes() as $outcome) {
            foreach ($instructionService->getOutcomeInstructionsByOutcome((int) $outcome->getId()) as $instruction) {
                if ($instruction instanceof LifeLossOutcomeInstruction) {
                    return $instruction->getParameters() ?? [];
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, int> $stats
     */
    private function bonusValue(mixed $param, array $stats): int
    {
        if (is_numeric($param)) {
            return (int) $param;
        }

        return is_string($param) ? $this->traitValue($stats, $param) : 0;
    }

    private function traitName(mixed $param): string
    {
        return is_string($param) && !is_numeric($param) ? $param : '';
    }
}
