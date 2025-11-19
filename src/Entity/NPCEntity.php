<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * NPCEntity - Non-Player Characters (enemies, allies, quest givers)
 *
 * NPCs are characterized by:
 * - Negative IDs (traditionally -1 to -99,999)
 * - Controlled by game logic, not players
 * - Can be enemies, allies, merchants, quest givers
 * - Don't appear in player lists/rankings
 * - May have special behaviors and loot tables
 *
 * Discriminator: player_type = 'npc'
 *
 * Note: Tutorial enemies also use this entity type but have IDs
 * in the -100,000+ range and are tracked separately.
 */
#[ORM\Entity]
class NPCEntity extends PlayerEntity
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

    /**
     * NPCs don't appear in player lists
     */
    public function isPubliclyVisible(): bool
    {
        return false;
    }

    /**
     * Check if this is a tutorial enemy (negative ID in tutorial range)
     */
    public function isTutorialEnemy(): bool
    {
        return $this->id !== null && $this->id <= -100000;
    }

    /**
     * Check if this is a regular game NPC
     */
    public function isRegularNPC(): bool
    {
        return $this->id !== null && $this->id > -100000 && $this->id < 0;
    }

    /**
     * Get NPC type based on ID range
     */
    public function getNPCType(): string
    {
        if ($this->isTutorialEnemy()) {
            return 'tutorial_enemy';
        }
        return 'regular_npc';
    }
}
