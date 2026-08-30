<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A road. Discriminator 'route', id range ENTITY_ID_RANGES['route']
 * (80 000 000+).
 *
 * A structure like a plant is one: its type blocks neither the step nor the
 * arrow, so it is walked on rather than stood in. Being an entity is what
 * lets it carry life, an owner, repair and decay — none of which a line in
 * `map_routes` could hold.
 */
#[ORM\Entity]
class Road extends Structure
{
}
