<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * UniqueObject — a singular interactable/attackable map object (magic
 * crystal, gate, artifact…). Discriminator 'unique', id range
 * ENTITY_ID_RANGES['unique'] (30 000 000+).
 *
 * Type-specific data (interaction config) lives in the
 * `unique_objects` satellite row (UniqueObjectDetails), per the
 * component pattern of docs/design-buildings-entities.md §4.5.
 */
#[ORM\Entity]
class UniqueObject extends Structure
{
}
