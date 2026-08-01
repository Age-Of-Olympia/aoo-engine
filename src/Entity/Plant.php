<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Plant — something growing on a cell, picked rather than struck.
 * Discriminator 'plant', id range ENTITY_ID_RANGES['plant'] (60 000 000+).
 *
 * Walkable where a resource blocks: one steps over a flower. What it yields
 * belongs to its type, like every other harvestable.
 *
 * It reached the `players` table before it reached this map, so every lookup
 * through the entity root answered null for a flower — the same omission the
 * {@see Resource} docblock records for scenery.
 */
#[ORM\Entity]
class Plant extends Structure
{
}
