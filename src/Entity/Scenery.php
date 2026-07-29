<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Scenery — a decor object standing on the map (anvil, statue, hut…).
 * Discriminator 'scenery', id range ENTITY_ID_RANGES['scenery']
 * (40 000 000+).
 *
 * A structure like the others, and the only one with no satellite row: a
 * decor carries nothing of its own beyond what its type says. Its cut-out
 * lives in `entity_type_footprints`, its cells in `entity_cells`.
 *
 * It was missing from the discriminator map, so anything looking an entity
 * up through the STI root got NULL for a decor — the observation card
 * answered "error target id" on a perfectly ordinary anvil.
 */
#[ORM\Entity]
class Scenery extends Structure
{
}
