<?php

namespace App\Simulation;

use App\Enum\EquipResult;
use Classes\Item;
use Classes\Player;

/**
 * A DB-free Player for simulation: it satisfies the concrete `Player` typehint
 * that outcome instructions require, reads all state from injected values, and
 * no-ops every mutation so running the real ActionExecutorService against it
 * persists nothing. Reads that would hit the DB on a real Player are overridden
 * to use the injected state; effect/passive lookups go through the simulated
 * services. See src/Simulation/Simulated{Effect,Passive}Service.
 */
class SimulatedPlayer extends Player
{
    /**
     * @param array<string, int> $caracs    trait => value
     * @param array<string, int> $remaining pa/pv/pm/mvt => value
     * @param array<string, mixed> $data    name/malus/antiBerserkTime/rank/faction/energie overrides
     * @param array<string, int> $effects   effect name => value
     * @param list<string> $passives        passive names
     */
    public function __construct(
        int $id,
        array $caracs,
        array $remaining,
        object $coords,
        array $data = [],
        ?object $emplacements = null,
        array $effects = [],
        array $passives = [],
    ) {
        // Deliberately NOT calling parent::__construct() — it news PlayerService($id) (DB).
        $this->id = $id;
        // Default every known trait to 0 so any defense/roll formula that reads a
        // trait the caller didn't provide returns 0 instead of warning.
        $caracDefaults = defined('CARACS') ? array_fill_keys(array_keys(CARACS), 0) : [];
        $this->caracs = (object) array_merge($caracDefaults, $caracs);
        $this->turn = (object) $remaining;
        $this->coords = $coords;
        $this->emplacements = $emplacements ?? (object) [];
        // Degressive-XP reduction read by AttackAction::calculateActorXp.
        $this->upgrades = (object) ['a' => 0];
        $this->data = (object) array_merge(
            [
                'name' => 'Simulé', 'race' => 'humain', 'malus' => 0, 'antiBerserkTime' => 0,
                'rank' => 1, 'faction' => '', 'secretFaction' => '', 'isInactive' => false, 'energie' => 100,
            ],
            $data,
        );
        $this->playerEffectService = new SimulatedEffectService($effects);
        $this->playerPassiveService = new SimulatedPassiveService($passives);
    }

    /* --- reads overridden to use injected state instead of the DB --- */

    public function getCoords(bool $refresh = true): object
    {
        return $this->coords;
    }

    public function getRemaining(string $trait): int
    {
        return (int) ($this->turn->{$trait} ?? $this->caracs->{$trait} ?? 0);
    }

    public function get_caracs(bool $nude = false): bool
    {
        return true;
    }

    public function get_data(bool $forceRefresh = true)
    {
        return $this->data;
    }

    public function getEquipedItems(): array
    {
        return [];
    }

    public function getPassives(int $id): array
    {
        return [];
    }

    public function get_upgrades()
    {
        return $this->upgrades;
    }

    public function get_action_xp($target)
    {
        return 0;
    }

    public function have_option($name): int
    {
        return 0;
    }

    public function have_effects_to_purge(): bool
    {
        return false;
    }

    public function getMunition(Item $object, bool $equiped = false): ?Item
    {
        return null;
    }

    /* --- mutations no-op'd so a simulation persists nothing --- */

    public function putBonus($bonus): bool
    {
        return true;
    }

    public function put_malus($malus): void
    {
    }

    public function put_xp($xp)
    {
        return 0;
    }

    public function put_assist($target, $damages)
    {
    }

    public function putEnergie($energie): void
    {
    }

    public function go($goCoords)
    {
    }

    public function equip(Item $item, bool $doNotRefresh = false): EquipResult
    {
        return EquipResult::DoNothing;
    }

    public function purge_effects(): int
    {
        return 0;
    }
}
