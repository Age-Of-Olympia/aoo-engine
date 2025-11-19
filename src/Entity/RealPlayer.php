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
class RealPlayer extends PlayerEntity
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

    /**
     * Check if player can appear in public lists (rankings, leaderboards)
     */
    public function isPubliclyVisible(): bool
    {
        return true;
    }

    /**
     * Check if player has admin privileges
     */
    public function isAdmin(): bool
    {
        // Check if player has isAdmin option
        // This would require loading from players_options table
        // For now, return false - implement when needed
        return false;
    }
}
