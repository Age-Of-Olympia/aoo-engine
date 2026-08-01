<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Exemplar — one individuated item, as an entity.
 * Discriminator 'item', id range ENTITY_ID_RANGES['item'] (70 000 000+).
 *
 * Anything carrying an `item_instances` row has an identity to keep — wear,
 * name, maker — which is what makes it an individual rather than a unit of a
 * stack. Stacks stay stacks and never get one of these.
 *
 * Unlike its neighbours it is usually NOWHERE: held in a bag, its location is a
 * holder rather than a cell. It takes its life from the `items` catalogue
 * through {@see OwnsCaracsInterface}, not from `races`.
 *
 * Named for what it is rather than `Item`, which is already the catalogue row —
 * the type, not the individual.
 */
#[ORM\Entity]
class Exemplar extends Structure
{
}
