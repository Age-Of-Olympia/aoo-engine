<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Resource — a harvestable thing standing on the map (tree, stone, peat).
 * Discriminator 'resource', id range ENTITY_ID_RANGES['resource']
 * (50 000 000+).
 *
 * A structure like the walls it came from: it blocks the step and it can be
 * hit. What it YIELDS belongs to the (plan, type) pair in `race_harvest`; what
 * it currently IS — dry or standing — belongs to its own `resources` satellite,
 * because an exhausted resource stays on the board and regrows in place.
 *
 * Declared BEFORE any row wears the type, and that order matters: `scenery`
 * was added to the table before it reached this map, so every lookup through
 * the entity root returned null for a decor until someone noticed.
 */
#[ORM\Entity]
class Resource extends Structure
{
}
