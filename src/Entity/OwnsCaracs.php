<?php

namespace App\Entity;

/**
 * What a TYPE gives to the individual that belongs to it.
 *
 * Both catalogues carry the same sixteen carac columns, but in opposite
 * directions: `races.pv` is the life a member HAS, `items.pv` is the life an
 * item LENDS its bearer. An item's own life is its `durability_max`.
 *
 * So an item's own block holds one entry and the other fifteen columns stay
 * conferred-only. Reading them as owned would give a breastplate a max life of
 * 5 instead of 100.
 *
 * Always sixteen keys: combat reads `caracs->{trait}` for whichever trait the
 * action names, so a short block is an undefined property mid-fight.
 */
interface OwnsCaracs
{
    /** @return array<string, int> the sixteen CARACS keys, complete */
    public function ownCaracs(): array;
}
