<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * NonPlayerCharacter — NPCs (enemies, allies, quest givers).
 *
 * NPCs are characterized by:
 * - Negative IDs (traditionally -1 to -99,999)
 * - Controlled by game logic, not players
 * - Can be enemies, allies, merchants, quest givers
 * - Don't appear in player lists/rankings
 * - May have special behaviors and loot tables
 *
 * Discriminator: player_type = 'npc'.
 *
 * Note: Tutorial enemies also use this entity type but have IDs
 * in the -100,000+ range and are tracked separately.
 */
#[ORM\Entity]
class NonPlayerCharacter extends Character
{
    /**
     * NPCs are not players
     */
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
        return true;
    }

}
