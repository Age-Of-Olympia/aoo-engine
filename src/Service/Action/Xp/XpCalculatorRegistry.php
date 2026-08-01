<?php

namespace App\Service\Action\Xp;

/**
 * Maps an XP "mode" to its calculator. The mode is stored per action type in
 * action_type_xp; the resolver and the editor both go through here so the set of
 * modes (and their param keys) has a single source of truth.
 */
final class XpCalculatorRegistry
{
    public const MODE_FIXED = 'fixed';
    public const MODE_ATTACK = 'attack';
    public const MODE_STEAL = 'steal';
    public const MODE_TRAIN = 'train';

    /** @var array<string, XpCalculatorInterface> */
    private array $calculators;

    public function __construct()
    {
        $this->calculators = [
            self::MODE_FIXED => new FixedXpCalculator(),
            self::MODE_ATTACK => new AttackXpCalculator(),
            self::MODE_STEAL => new StealXpCalculator(),
            self::MODE_TRAIN => new TrainXpCalculator(),
        ];
    }

    public function has(string $mode): bool
    {
        return isset($this->calculators[$mode]);
    }

    public function get(string $mode): ?XpCalculatorInterface
    {
        return $this->calculators[$mode] ?? null;
    }

    /**
     * The default param set for a mode (also the keys the editor renders).
     *
     * @return array<string, int>
     */
    public function defaultsFor(string $mode): array
    {
        $calculator = $this->calculators[$mode] ?? null;

        return $calculator === null ? [] : $calculator::defaults();
    }

    /** @return array<int, string> */
    public function modes(): array
    {
        return array_keys($this->calculators);
    }
}
