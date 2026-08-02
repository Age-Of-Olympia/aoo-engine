<?php

namespace App\Entity;

use App\Interface\ProgressesInterface;
use App\Interface\TakesTurnsInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * Character — abstract branch of the GameEntity STI for entities that are
 * played or play themselves: real players, tutorial players, NPCs
 * (docs/design-buildings-entities.md §4.3).
 *
 * Carries what a map STRUCTURE must not: account data (psw/mail — the
 * §3 ideal eventually splits these into their own table, Phase D),
 * progression (xp, pi, rank…), faction RANK and turn timing — belonging to a
 * faction is on GameEntity, since a forge belongs to one too.
 *
 * Two of those are NOT character-ness, and say so out loud: taking turns and
 * progressing are capabilities, held here today and by playable buildings
 * tomorrow (docs/design-playable-buildings.md). A reader asks "does this take
 * turns?" instead of "is this a character?", so the gates written from now on
 * do not have to be swept later.
 *
 * The columns behind those two capabilities are mapped here but no longer own
 * their value: `turns` and `progression` do, through TurnService and
 * ProgressionService, and the columns are mirrors kept alive until the
 * post-deployment drop. The same holds for psw/mail and `accounts`.
 *
 * No discriminator entry of its own — Doctrine allows abstract
 * intermediate classes in an STI hierarchy; querying Character returns
 * every row whose type maps into this subtree.
 */
#[ORM\Entity]
abstract class Character extends GameEntity implements TakesTurnsInterface, ProgressesInterface
{
    #[ORM\Column(type: "string", length: 255)]
    protected string $psw = '';

    #[ORM\Column(type: "string", length: 255)]
    protected string $mail = '';

    #[ORM\Column(type: "string", length: 255, name: "plain_mail")]
    protected string $plainMail = '';

    #[ORM\Column(type: "integer")]
    protected int $xp = 0;

    #[ORM\Column(type: "integer", name: "bonus_points")]
    protected int $bonusPoints = 0;

    #[ORM\Column(type: "integer")]
    protected int $pi = 0;

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

    #[ORM\Column(type: "integer")]
    protected int $rank = 1;

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
    protected int $nextTurnTime = 0;

    #[ORM\Column(type: "boolean")]
    protected bool $nextTurnRescheduled = false;

    #[ORM\Column(type: "integer")]
    protected int $lastActionTime = 0;

    #[ORM\Column(type: "integer")]
    protected int $lastLoginTime = 0;

    #[ORM\Column(type: "integer")]
    protected int $antiBerserkTime = 0;

    #[ORM\Column(type: "integer")]
    protected int $lastTravelTime = 0;

    #[ORM\Column(type: "boolean", name: "email_bonus", nullable: true)]
    protected ?bool $emailBonus = false;

    // Getters and Setters

    public function getPassword(): string
    {
        return $this->psw;
    }

    public function setPassword(string $psw): self
    {
        $this->psw = $psw;
        return $this;
    }

    public function getMail(): string
    {
        return $this->mail;
    }

    public function setMail(string $mail): self
    {
        $this->mail = $mail;
        return $this;
    }

    public function getPlainMail(): string
    {
        return $this->plainMail;
    }

    public function setPlainMail(string $plainMail): self
    {
        $this->plainMail = $plainMail;
        return $this;
    }

    public function getXp(): int
    {
        return $this->xp;
    }

    public function setXp(int $xp): self
    {
        $this->xp = $xp;
        return $this;
    }

    public function addXp(int $amount): self
    {
        $this->xp += $amount;
        return $this;
    }

    public function getBonusPoints(): int
    {
        return $this->bonusPoints;
    }

    public function setBonusPoints(int $bonusPoints): self
    {
        $this->bonusPoints = $bonusPoints;
        return $this;
    }

    public function getPi(): int
    {
        return $this->pi;
    }

    public function setPi(int $pi): self
    {
        $this->pi = $pi;
        return $this;
    }

    public function addPi(int $amount): self
    {
        $this->pi += $amount;
        return $this;
    }

    public function getPr(): int
    {
        return $this->pr;
    }

    public function setPr(int $pr): self
    {
        $this->pr = $pr;
        return $this;
    }

    public function getMalus(): int
    {
        return $this->malus;
    }

    public function setMalus(int $malus): self
    {
        $this->malus = $malus;
        return $this;
    }

    public function getEnergie(): int
    {
        return $this->energie;
    }

    public function setEnergie(int $energie): self
    {
        $this->energie = $energie;
        return $this;
    }

    public function getGodId(): int
    {
        return $this->godId;
    }

    public function setGodId(int $godId): self
    {
        $this->godId = $godId;
        return $this;
    }

    public function getPf(): int
    {
        return $this->pf;
    }

    public function setPf(int $pf): self
    {
        $this->pf = $pf;
        return $this;
    }

    public function getRank(): int
    {
        return $this->rank;
    }

    public function setRank(int $rank): self
    {
        $this->rank = $rank;
        return $this;
    }

    public function getStory(): string
    {
        return $this->story;
    }

    public function setStory(string $story): self
    {
        $this->story = $story;
        return $this;
    }

    public function getQuest(): ?string
    {
        return $this->quest;
    }

    public function setQuest(?string $quest): self
    {
        $this->quest = $quest;
        return $this;
    }

    public function getFactionRole(): int
    {
        return $this->factionRole;
    }

    public function setFactionRole(int $factionRole): self
    {
        $this->factionRole = $factionRole;
        return $this;
    }

    public function getSecretFaction(): string
    {
        return $this->secretFaction;
    }

    public function setSecretFaction(string $secretFaction): self
    {
        $this->secretFaction = $secretFaction;
        return $this;
    }

    public function getSecretFactionRole(): int
    {
        return $this->secretFactionRole;
    }

    public function setSecretFactionRole(int $secretFactionRole): self
    {
        $this->secretFactionRole = $secretFactionRole;
        return $this;
    }

    public function getNextTurnTime(): int
    {
        return $this->nextTurnTime;
    }

    public function setNextTurnTime(int $nextTurnTime): self
    {
        $this->nextTurnTime = $nextTurnTime;
        return $this;
    }

    public function isNextTurnRescheduled(): bool
    {
        return $this->nextTurnRescheduled;
    }

    public function setNextTurnRescheduled(bool $nextTurnRescheduled): self
    {
        $this->nextTurnRescheduled = $nextTurnRescheduled;
        return $this;
    }

    public function getLastActionTime(): int
    {
        return $this->lastActionTime;
    }

    public function setLastActionTime(int $lastActionTime): self
    {
        $this->lastActionTime = $lastActionTime;
        return $this;
    }

    public function getLastLoginTime(): int
    {
        return $this->lastLoginTime;
    }

    public function setLastLoginTime(int $lastLoginTime): self
    {
        $this->lastLoginTime = $lastLoginTime;
        return $this;
    }

    public function getAntiBerserkTime(): int
    {
        return $this->antiBerserkTime;
    }

    public function setAntiBerserkTime(int $antiBerserkTime): self
    {
        $this->antiBerserkTime = $antiBerserkTime;
        return $this;
    }

    public function getLastTravelTime(): int
    {
        return $this->lastTravelTime;
    }

    public function setLastTravelTime(int $lastTravelTime): self
    {
        $this->lastTravelTime = $lastTravelTime;
        return $this;
    }

    public function getEmailBonus(): ?bool
    {
        return $this->emailBonus;
    }

    public function setEmailBonus(?bool $emailBonus): self
    {
        $this->emailBonus = $emailBonus;
        return $this;
    }
}
