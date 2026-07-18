<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * UniqueObjectDetails — 1:1 satellite row of a UniqueObject's `players`
 * row (component pattern, docs/design-buildings-entities.md §4.5).
 *
 * The object's TYPE lives in players.race (a races row of kind
 * 'structure') — not duplicated here.
 *
 * - interaction: free JSON for interaction config (dialog id, trigger,
 *   loot table ref…) — deliberately schemaless while the unique-object
 *   semantics are settled (plan open question #6).
 */
#[ORM\Entity]
#[ORM\Table(name: "unique_objects")]
class UniqueObjectDetails
{
    #[ORM\Id]
    #[ORM\Column(type: "integer", name: "player_id")]
    private int $playerId;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $interaction = null;

    public function getPlayerId(): int
    {
        return $this->playerId;
    }

    public function setPlayerId(int $playerId): self
    {
        $this->playerId = $playerId;
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
