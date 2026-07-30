<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Resource — a harvestable thing standing on the map (tree, stone, peat).
 * Discriminator 'resource', id range ENTITY_ID_RANGES['resource']
 * (50 000 000+).
 *
 * A structure like the walls it came from: it blocks the step, it can be hit,
 * and it holds no satellite row. What it yields belongs to the (plan, type)
 * pair in `race_harvest`, not to the instance.
 *
 * Declared BEFORE any row wears the type, and that order matters: `scenery`
 * was added to the table before it reached this map, so every lookup through
 * the entity root returned null for a decor until someone noticed.
 */
#[ORM\Entity]
class Resource extends Structure
{
}
