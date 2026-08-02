<?php

namespace App\Entity;

use App\Interface\ProgressesInterface;
use App\Interface\TakesTurnsInterface;
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
 *
 * **It plays.** A building takes turns and earns its own experience — the two
 * capabilities `Character` happened to have first — without ever gaining an
 * account, and without being reparented under it
 * (docs/design-playable-buildings.md §1). Its turn is its action pool and its
 * clock; it has no body to recover.
 *
 * Holding the capability says a building *may* play. Whether a given one does
 * is its TYPE's answer — `races.playable`, read through
 * {@see \App\Service\PlaysTurns} — exactly as blocking or locking are the
 * type's answer rather than the branch's. Whoever drives it comes from faction
 * access, never from an account of its own (§3.4, §3.6).
 */
#[ORM\Entity]
class Building extends Structure implements TakesTurnsInterface, ProgressesInterface
{
}
