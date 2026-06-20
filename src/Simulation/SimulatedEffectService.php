<?php

namespace App\Simulation;

/**
 * DB-free stand-in for PlayerEffectService used by SimulatedPlayer. Effect
 * values come from an injected map; writes are no-ops so a simulation never
 * persists anything.
 */
final class SimulatedEffectService
{
    /**
     * @param array<string, int> $effects effect name => value
     */
    public function __construct(private array $effects = [])
    {
    }

    public function getEffectValueByPlayerIdByEffectName(int $playerId, string $name): int
    {
        return $this->effects[$name] ?? 0;
    }

    public function hasEffectByPlayerIdByEffectName(int $playerId, string $name): bool
    {
        return isset($this->effects[$name]);
    }

    public function addEffectByPlayerId(int $playerId, string $name, int $endTime, int $value, bool $stackable): void
    {
    }

    public function removeEffectByPlayerId(int $playerId, string $name): void
    {
    }
}
