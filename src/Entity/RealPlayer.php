<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * RealPlayer - Represents actual game players (not tutorial, not NPC)
 *
 * These are the permanent player accounts that:
 * - Appear in rankings and leaderboards
 * - Participate in the real game world
 * - Have persistent progress and items
 * - Are visible to other players
 */
#[ORM\Entity]
class RealPlayer extends Character
{
    /**
     * Real players are the actual game participants
     */
    public function isRealPlayer(): bool
    {
        return true;
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
