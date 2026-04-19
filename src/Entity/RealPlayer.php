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

    /**
     * Has this player's last login passed the INACTIVE_TIME threshold?
     *
     * Phase 3.2 domain method — replaces legacy `$player->data->isInactive`
     * for entity callers (e.g. infos.php). Delegates to
     * PlayerService::isInactive so the rule lives in ONE place.
     *
     * Only meaningful for real players: tutorial characters are
     * ephemeral and NPCs never "log in" in the user sense. Keeping
     * this on RealPlayer (not PlayerEntity) prevents accidental
     * misuse on the other subclasses.
     */
    public function isInactive(\App\Service\PlayerService $playerService): bool
    {
        return $playerService->isInactive($this->getLastLoginTime());
    }
}
