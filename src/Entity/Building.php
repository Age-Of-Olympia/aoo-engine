<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Building — a placed, owned, attackable, destructible structure
 * (tower, palisade, warehouse…). Discriminator 'building', id range
 * ENTITY_ID_RANGES['building'] (20 000 000+).
 *
 * The `players` row carries identity/position/PV surface (via
 * GameEntity); everything building-specific — type (players.race), owner,
 * faction allegiance, build state — lives in the `buildings`
 * satellite row (BuildingDetails), per the component pattern of
 * docs/design-buildings-entities.md §4.5.
 */
#[ORM\Entity]
class Building extends Structure
{
}
