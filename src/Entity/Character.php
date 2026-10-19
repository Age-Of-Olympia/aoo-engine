<?php

namespace App\Entity;

use App\Interface\ProgressesInterface;
use App\Interface\TakesTurnsInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * Character — abstract branch of the GameEntity STI for entities that are
 * played or play themselves: real players, tutorial players, NPCs.
 *
 * Carries what a map STRUCTURE must not: a story, a god, a faction RANK —
 * belonging to a faction is on GameEntity, since a forge belongs to one too.
 * The account lives in `accounts` (AccountService).
 *
 * Taking turns and progressing are NOT character-ness, and say so out loud:
 * they are capabilities, held here and by playable buildings
 * (docs/design-playable-buildings.md), stored in the `turns` and `progression`
 * satellites (TurnService, ProgressionService). A reader asks "does this take
 * turns?" instead of "is this a character?".
 *
 * No discriminator entry of its own — Doctrine allows abstract
 * intermediate classes in an STI hierarchy; querying Character returns
 * every row whose type maps into this subtree.
 */
#[ORM\Entity]
abstract class Character extends GameEntity implements TakesTurnsInterface, ProgressesInterface
{
    #[ORM\Column(type: "integer")]
    protected int $pr = 0;

    #[ORM\Column(type: "integer")]
    protected int $malus = 0;

    #[ORM\Column(type: "integer")]
    protected int $energie = 0;

    #[ORM\Column(type: "integer")]
    protected int $godId = 0;

    #[ORM\Column(type: "integer")]
    protected int $pf = 0;

    #[ORM\Column(type: "text")]
    protected string $story = 'Je préfère garder cela pour moi.';

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    protected ?string $quest = 'gaia';

    /* `faction` est monté sur GameEntity : une chose appartient à une
     * faction, qu'elle soit personnage ou forge. Le RÔLE, lui, reste ici —
     * porter une faction n'est pas y tenir un rang. */
    #[ORM\Column(type: "integer")]
    protected int $factionRole = 0;

    #[ORM\Column(type: "string", length: 255)]
    protected string $secretFaction = '';

    #[ORM\Column(type: "integer")]
    protected int $secretFactionRole = 0;

    #[ORM\Column(type: "integer")]
    protected int $lastTravelTime = 0;

    public function getPr(): int
    {
        return $this->pr;
    }

    public function getMalus(): int
    {
        return $this->malus;
    }

    public function getEnergie(): int
    {
        return $this->energie;
    }

    public function getGodId(): int
    {
        return $this->godId;
    }

    public function getStory(): string
    {
        return $this->story;
    }

    public function getFactionRole(): int
    {
        return $this->factionRole;
    }

    public function getSecretFaction(): string
    {
        return $this->secretFaction;
    }

    public function getSecretFactionRole(): int
    {
        return $this->secretFactionRole;
    }

}
