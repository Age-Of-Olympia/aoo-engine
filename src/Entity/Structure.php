<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Structure — abstract branch of the GameEntity STI for immobile map
 * entities: buildings and unique objects
 * (docs/design-buildings-entities.md §4.3).
 *
 * A structure is a `players` row so it inherits the whole damageable
 * machinery for free (§4.9): targetable by attacks, wounds in
 * players_bonus, distance/obstacle conditions, tile blocking, map
 * rendering, observation panel. Its max PV comes from a non-playable
 * pseudo-race (races.playable = false, §4.6) through the same caracs
 * computation as characters.
 *
 * What a structure does NOT have lives on Character: account data,
 * progression, faction membership, turn timing. Type-specific data
 * (owner, build state) lives in satellite tables, never as new players
 * columns (§4.5).
 */
#[ORM\Entity]
abstract class Structure extends GameEntity
{
    public function isRealPlayer(): bool
    {
        return false;
    }

    public function isTutorialPlayer(): bool
    {
        return false;
    }

    public function isNPC(): bool
    {
        return false;
    }
}
