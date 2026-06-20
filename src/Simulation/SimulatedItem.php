<?php

namespace App\Simulation;

use Classes\Item;

/**
 * DB-free stand-in for an equipped Classes\Item, backing a SimulatedPlayer's
 * emplacements. It IS a Classes\Item (so it satisfies the Item typehints the
 * combat code requires, e.g. Player::getMunition) but skips the DB constructor
 * and no-ops every mutation, so a simulation breaks/consumes nothing.
 */
class SimulatedItem extends Item
{
    public function __construct(string $subtype, string $name, bool $enchanted = false)
    {
        // Deliberately NOT calling parent::__construct() — it loads the item from the DB.
        $this->id = 0;
        $this->data = (object) ['subtype' => $subtype, 'name' => $name, 'addEffects' => []];
        $this->row = (object) ['name' => $name, 'enchanted' => $enchanted];
    }

    public function get_data()
    {
        return $this->data;
    }

    public function is_crafted_with($ingredients)
    {
        return false;
    }

    public function add_item($player, int $n, bool $bank = false): bool
    {
        return true;
    }

    public function get_recipe(bool $deprecated = false): array
    {
        return [];
    }
}
