<?php

namespace App\Simulation;

/**
 * DB-free stand-in for an equipped Classes\Item, backing a SimulatedPlayer's
 * emplacements. It carries the `data` (item json) and `row` (DB row) the combat
 * engine reads — including `row->enchanted`, which the object-break path checks —
 * and no-ops every mutation so a simulation breaks/consumes nothing.
 */
final class SimulatedItem
{
    public object $data;
    public object $row;

    public function __construct(string $subtype, string $name, bool $enchanted = false)
    {
        $this->data = (object) ['subtype' => $subtype, 'name' => $name, 'addEffects' => []];
        $this->row = (object) ['enchanted' => $enchanted];
    }

    public function is_crafted_with(string $material): bool
    {
        return false;
    }

    public function add_item($player, int $quantity): void
    {
    }

    /**
     * @return array<string, int>
     */
    public function get_recipe(): array
    {
        return [];
    }
}
