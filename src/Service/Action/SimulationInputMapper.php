<?php

namespace App\Service\Action;

/**
 * Maps the simulate panel's POST payload to a SimulationInput. Pure request
 * translation — no rendering, no DB — so it is unit-testable on its own.
 */
final class SimulationInputMapper
{
    private const BASE_REMAINING = ['pa' => 6, 'pv' => 20, 'pm' => 15, 'mvt' => 6];
    private const DEFAULT_TARGET_WEAPON = 'melee';
    // Capped low: each run executes the real engine, so a high count is slow.
    private const MAX_RUNS = 100;

    /**
     * @param array<string, mixed> $post
     */
    public function fromPost(array $post): SimulationInput
    {
        return new SimulationInput(
            actorCaracs: $this->intMap($post['actor_trait'] ?? []),
            targetCaracs: $this->intMap($post['target_trait'] ?? []),
            actorRemaining: array_merge(self::BASE_REMAINING, $this->intMap($post['actor_remaining'] ?? [])),
            targetRemaining: array_merge(self::BASE_REMAINING, $this->intMap($post['target_remaining'] ?? [])),
            distance: max(0, (int) ($post['distance'] ?? 1)),
            actorWeapon: $this->weapon($post['actor_weapon'] ?? '', null),
            targetWeapon: $this->weapon($post['target_weapon'] ?? '', self::DEFAULT_TARGET_WEAPON),
            actorEffects: $this->effectRows($post, 'actor'),
            targetEffects: $this->effectRows($post, 'target'),
            actorPassives: $this->stringList($post['actor_passives'] ?? []),
            targetPassives: $this->stringList($post['target_passives'] ?? []),
            plan: !empty($post['enfers']) ? 'enfers' : 'gaia',
            actorBerserk: !empty($post['actor_berserk']),
            actorEquipment: $this->equipment($post['actor_equipment'] ?? []),
            targetEquipment: $this->equipment($post['target_equipment'] ?? []),
            tileTypes: $this->checkedKeys($post['tile'] ?? []),
            actorRank: max(1, (int) ($post['actor_rank'] ?? 1)),
            targetRank: max(1, (int) ($post['target_rank'] ?? 1)),
        );
    }

    /**
     * The keys of the checked tile checkboxes (name="tile[<type>]" value="1").
     *
     * @return list<string>
     */
    private function checkedKeys(mixed $raw): array
    {
        $keys = [];
        foreach ((array) $raw as $key => $value) {
            if (!empty($value)) {
                $keys[] = (string) $key;
            }
        }

        return $keys;
    }

    /**
     * Slot => item name, dropping empty selections.
     *
     * @return array<string, string>
     */
    private function equipment(mixed $raw): array
    {
        $equipment = [];
        foreach ((array) $raw as $slot => $name) {
            $name = trim((string) $name);
            if ($name !== '') {
                $equipment[(string) $slot] = $name;
            }
        }

        return $equipment;
    }

    /**
     * @param array<string, mixed> $post
     */
    public function runs(array $post): int
    {
        return max(1, min(self::MAX_RUNS, (int) ($post['runs'] ?? 1)));
    }

    /**
     * @return array<string, int>
     */
    private function intMap(mixed $raw): array
    {
        return array_map('intval', (array) $raw);
    }

    private function weapon(mixed $raw, ?string $default): ?string
    {
        $weapon = (string) $raw;

        return $weapon !== '' ? $weapon : $default;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $raw): array
    {
        return array_values(array_filter((array) $raw, 'is_string'));
    }

    /**
     * Zip the posted effect-row name[] / value[] arrays into a name => value map.
     *
     * @param array<string, mixed> $post
     * @return array<string, int>
     */
    private function effectRows(array $post, string $side): array
    {
        $names = (array) ($post[$side . '_effect_name'] ?? []);
        $values = (array) ($post[$side . '_effect_value'] ?? []);
        $map = [];
        foreach ($names as $i => $name) {
            $name = trim((string) $name);
            if ($name !== '') {
                $map[$name] = (int) ($values[$i] ?? 0);
            }
        }

        return $map;
    }
}
