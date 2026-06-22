<?php

namespace App\Service\Action;

use Classes\Json;

/**
 * The real equippable items (from datas/.../items) available to the simulator,
 * so an action can be tested with actual equipment — with its real subtype and
 * stats (spellMalus, cc, …) — instead of a bare type string. That lets the
 * simulator exercise the equipment-dependent conditions (AntiSpell reads
 * spellMalus; Dodge reads the weapon subtype) and fold stat bonuses into caracs,
 * for both fighters and across every slot (weapon, helmet, ring, …).
 */
final class SimulationWeaponCatalog
{
    /** @var array<string, object> item name => item data */
    private array $items = [];

    /**
     * @param array<string, object>|null $items name => item data (defaults to all items)
     */
    public function __construct(?array $items = null)
    {
        $items ??= (new Json())->get_all('items');
        foreach ($items as $name => $data) {
            if (($data->type ?? null) === 'equipement') {
                $this->items[(string) $name] = $data;
            }
        }
    }

    /**
     * Main-hand weapons grouped by subtype, for the weapon optgroup picker:
     * subtype => [weapon name => display label].
     *
     * @return array<string, array<string, string>>
     */
    public function groupedBySubtype(): array
    {
        $groups = [];
        foreach ($this->mainHand() as $name => $data) {
            $subtype = (string) ($data->subtype ?? 'autre');
            $groups[$subtype][$name] = (string) ($data->name ?? $name);
        }
        ksort($groups);
        foreach ($groups as &$weapons) {
            asort($weapons);
        }

        return $groups;
    }

    /**
     * Non-main-hand equipment grouped by slot (emplacement), for the per-side
     * equipment pickers: emplacement => [item name => display label].
     *
     * @return array<string, array<string, string>>
     */
    public function defenseSlots(): array
    {
        $slots = [];
        foreach ($this->items as $name => $data) {
            $emplacement = (string) ($data->emplacement ?? '');
            if ($emplacement === '' || $emplacement === 'main1') {
                continue;
            }
            $slots[$emplacement][$name] = (string) ($data->name ?? $name);
        }
        ksort($slots);
        foreach ($slots as &$items) {
            asort($items);
        }

        return $slots;
    }

    public function has(string $name): bool
    {
        return isset($this->items[$name]);
    }

    public function dataFor(string $name): ?object
    {
        return $this->items[$name] ?? null;
    }

    /**
     * @return array<string, object> main-hand items, name => data
     */
    private function mainHand(): array
    {
        return array_filter($this->items, static fn (object $data): bool => ($data->emplacement ?? null) === 'main1');
    }
}
