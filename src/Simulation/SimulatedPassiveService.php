<?php

namespace App\Simulation;

/**
 * DB-free stand-in for PlayerPassiveService used by SimulatedPlayer. Supports
 * name-based passive checks (e.g. reflexes_fulgurants, couverture); the
 * value-computing passive pipeline is intentionally empty in v1, so
 * getPassivesByPlayerId() returns [] and the conditions' passive loops are
 * skipped for a clean simulated character.
 */
final class SimulatedPassiveService
{
    /**
     * @param list<string> $passiveNames
     */
    public function __construct(private array $passiveNames = [])
    {
    }

    /**
     * @return array<int, object>
     */
    public function getPassivesByPlayerId(int $playerId): array
    {
        return [];
    }

    public function hasPassiveByPlayerIdByName(int $playerId, string $name): bool
    {
        return in_array($name, $this->passiveNames, true);
    }

    public function getComputedValueByPlayerIdById(int $playerId, $id): int
    {
        return 0;
    }

    public function checkPassiveConditionsByPlayerById($player, $passive, $conditionObject): bool
    {
        return false;
    }

    public function setEsquivePlayer($player): void
    {
    }
}
