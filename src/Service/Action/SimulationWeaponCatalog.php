<?php

namespace App\Service\Action;

use Classes\Json;

/**
 * The real main-hand weapons (from datas/.../items) available to the simulator,
 * so an action can be tested with an actual weapon — with its real subtype and
 * spellMalus — instead of a bare type string. That lets the simulator exercise
 * the weapon-dependent conditions (AntiSpell reads spellMalus; Dodge reads the
 * subtype).
 */
final class SimulationWeaponCatalog
{
    /** @var array<string, object> weapon name => item data */
    private array $weapons = [];

    /**
     * @param array<string, object>|null $items name => item data (defaults to all items)
     */
    public function __construct(?array $items = null)
    {
        $items ??= (new Json())->get_all('items');
        foreach ($items as $name => $data) {
            if (($data->type ?? null) === 'equipement' && ($data->emplacement ?? null) === 'main1') {
                $this->weapons[(string) $name] = $data;
            }
        }
    }

    /**
     * Weapons grouped by subtype, for an optgroup picker:
     * subtype => [weapon name => display label].
     *
     * @return array<string, array<string, string>>
     */
    public function groupedBySubtype(): array
    {
        $groups = [];
        foreach ($this->weapons as $name => $data) {
            $subtype = (string) ($data->subtype ?? 'autre');
            $groups[$subtype][$name] = (string) ($data->name ?? $name);
        }
        ksort($groups);
        foreach ($groups as &$weapons) {
            asort($weapons);
        }

        return $groups;
    }

    public function has(string $name): bool
    {
        return isset($this->weapons[$name]);
    }

    public function dataFor(string $name): ?object
    {
        return $this->weapons[$name] ?? null;
    }
}
