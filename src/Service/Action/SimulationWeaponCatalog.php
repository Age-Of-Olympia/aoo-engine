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
     * Every non-main-hand slot (emplacement), for the per-side equipment pickers:
     * emplacement => [item name => display label]. EVERY slot of the game's real
     * slot model (ITEM_EMPLACEMENT_FORMAT) is listed — even slots with no item yet
     * — so the whole loadout is testable; the main-hand weapon has its own picker.
     * Falls back to the slots that have items when the constant is absent.
     *
     * @return array<string, array<string, string>>
     */
    public function equipmentSlots(): array
    {
        $byEmplacement = [];
        foreach ($this->items as $name => $data) {
            $emplacement = (string) ($data->emplacement ?? '');
            if ($emplacement === '') {
                continue;
            }
            $byEmplacement[$emplacement][$name] = (string) ($data->name ?? $name);
        }

        $order = defined('ITEM_EMPLACEMENT_FORMAT') ? ITEM_EMPLACEMENT_FORMAT : array_keys($byEmplacement);
        $slots = [];
        foreach ($order as $slot) {
            if ($slot === 'main1') {
                continue; // the main-hand weapon has its own picker
            }
            $items = $byEmplacement[$slot] ?? [];
            asort($items);
            $slots[$slot] = $items;
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
