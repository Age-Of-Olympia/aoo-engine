<?php

namespace App\Simulation;

use App\Action\Combat\PassiveValueCalculator;
use App\Entity\ActionPassive;
use Classes\Player;

/**
 * DB-free stand-in for PlayerPassiveService used by SimulatedPlayer. It holds
 * the real ActionPassive configs the simulated character was given and computes
 * their values through the shared PassiveValueCalculator against the simulated
 * player's state, so value- and advantage-based passives apply in a simulation.
 * The selected passives are treated as active (their equipment/category
 * conditions are assumed met for the hypothetical).
 */
final class SimulatedPassiveService
{
    private PassiveValueCalculator $calculator;

    /**
     * @param list<ActionPassive> $passives
     */
    public function __construct(
        private array $passives = [],
        private ?Player $player = null,
        ?PassiveValueCalculator $calculator = null,
    ) {
        $this->calculator = $calculator ?? new PassiveValueCalculator();
    }

    /**
     * @return list<ActionPassive>
     */
    public function getPassivesByPlayerId(int $playerId): array
    {
        return $this->passives;
    }

    public function hasPassiveByPlayerIdByName(int $playerId, string $name): bool
    {
        foreach ($this->passives as $passive) {
            if ($passive->getName() === $name) {
                return true;
            }
        }

        return false;
    }

    public function getComputedValueByPlayerIdById(int $playerId, $id): int
    {
        $passive = $this->findPassive($id);
        if ($passive === null || $this->player === null) {
            return 0;
        }

        return $this->calculator->compute($passive, $this->player);
    }

    public function checkPassiveConditionsByPlayerById($player, $passive, $conditionObject): bool
    {
        return true;
    }

    public function setEsquivePlayer($player): void
    {
    }

    /**
     * Callers reference a passive by id (actor side) or by name (target side).
     */
    private function findPassive(int|string $idOrName): ?ActionPassive
    {
        foreach ($this->passives as $passive) {
            if ($passive->getId() === $idOrName || $passive->getName() === $idOrName) {
                return $passive;
            }
        }

        return null;
    }
}
