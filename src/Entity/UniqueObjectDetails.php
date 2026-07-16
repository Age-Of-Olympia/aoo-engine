<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * UniqueObjectDetails — 1:1 satellite row of a UniqueObject's `players`
 * row (component pattern, docs/design-buildings-entities.md §4.5).
 *
 * - archetype: the object kind ('cristal', 'porte'…). Also the name of
 *   the non-playable pseudo-race carrying its base stats.
 * - interaction: free JSON for interaction config (dialog id, trigger,
 *   loot table ref…) — deliberately schemaless while the unique-object
 *   semantics are settled (plan open question #6).
 */
#[ORM\Entity]
#[ORM\Table(name: "unique_objects")]
class UniqueObjectDetails
{
    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $player_id;

    #[ORM\Column(type: "string", length: 64)]
    private string $archetype = '';

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $interaction = null;

    public function getPlayerId(): int
    {
        return $this->player_id;
    }

    public function setPlayerId(int $player_id): self
    {
        $this->player_id = $player_id;
        return $this;
    }

    public function getArchetype(): string
    {
        return $this->archetype;
    }

    public function setArchetype(string $archetype): self
    {
        $this->archetype = $archetype;
        return $this;
    }

    public function getInteraction(): ?string
    {
        return $this->interaction;
    }

    public function setInteraction(?string $interaction): self
    {
        $this->interaction = $interaction;
        return $this;
    }
}
