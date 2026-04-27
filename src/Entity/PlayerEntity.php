<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * PlayerEntity - Base class for all player types (Single Table Inheritance)
 *
 * This follows the same pattern as the Action system:
 * - Single table (players) stores all player types
 * - Discriminator column (player_type) determines concrete class
 * - Concrete classes: RealPlayer, TutorialPlayer, NPC
 *
 * Architecture rationale:
 * - Reuses all existing player mechanisms (actions, inventory, movement)
 * - No schema duplication
 * - Type-safe filtering (get only real players, only tutorial players, etc.)
 * - Tutorial players isolated from real player lists
 */
#[ORM\Entity]
#[ORM\Table(name: "players")]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'player_type', type: 'string')]
#[ORM\DiscriminatorMap([
    'real' => RealPlayer::class,
    'tutorial' => TutorialPlayer::class,
    'npc' => NonPlayerCharacter::class
])]
abstract class PlayerEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    protected ?int $id = null;

    #[ORM\Column(type: "integer", name: "display_id", nullable: true)]
    private ?int $displayId = null;

    #[ORM\Column(type: "string", length: 255)]
    protected string $name = '';

    #[ORM\Column(type: "string", length: 255)]
    protected string $psw = '';

    #[ORM\Column(type: "string", length: 255)]
    protected string $mail = '';

    #[ORM\Column(type: "string", length: 255, name: "plain_mail")]
    protected string $plainMail = '';

    #[ORM\Column(type: "integer", name: "coords_id")]
    protected int $coordsId = 0;

    #[ORM\Column(type: "string", length: 255)]
    protected string $race = '';

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

    #[ORM\Column(type: "string", length: 255)]
    protected string $avatar = '';

    #[ORM\Column(type: "string", length: 255)]
    protected string $portrait = '';

    #[ORM\Column(type: "text")]
    protected string $text = 'Je suis nouveau, frappez-moi!';

    #[ORM\Column(type: "text")]
    protected string $story = 'Je préfère garder cela pour moi.';

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    protected ?string $quest = 'gaia';

    #[ORM\Column(type: "string", length: 255)]
    protected string $faction = '';

    #[ORM\Column(type: "integer")]
    protected int $factionRole = 0;

    #[ORM\Column(type: "string", length: 255)]
    protected string $secretFaction = '';

    #[ORM\Column(type: "integer")]
    protected int $secretFactionRole = 0;

    #[ORM\Column(type: "integer")]
    protected int $nextTurnTime = 0;

    #[ORM\Column(type: "integer")]
    protected int $registerTime = 0;

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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDisplayId(): int
    {
        return $this->displayId !== null && $this->displayId > 0
            ? $this->displayId
            : (int) $this->id;
    }

    public function setDisplayId(?int $displayId): self
    {
        $this->displayId = $displayId;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

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

    public function getCoordsId(): int
    {
        return $this->coordsId;
    }

    public function setCoordsId(int $coordsId): self
    {
        $this->coordsId = $coordsId;
        return $this;
    }

    public function getRace(): string
    {
        return $this->race;
    }

    public function setRace(string $race): self
    {
        $this->race = $race;
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

    public function getAvatar(): string
    {
        return $this->avatar;
    }

    public function setAvatar(string $avatar): self
    {
        $this->avatar = $avatar;
        return $this;
    }

    public function getPortrait(): string
    {
        return $this->portrait;
    }

    public function setPortrait(string $portrait): self
    {
        $this->portrait = $portrait;
        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): self
    {
        $this->text = $text;
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

    public function getFaction(): string
    {
        return $this->faction;
    }

    public function setFaction(string $faction): self
    {
        $this->faction = $faction;
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

    public function getRegisterTime(): int
    {
        return $this->registerTime;
    }

    public function setRegisterTime(int $registerTime): self
    {
        $this->registerTime = $registerTime;
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

    /**
     * Check if this is a real player (not tutorial, not NPC)
     */
    abstract public function isRealPlayer(): bool;

    /**
     * Check if this is a tutorial player
     */
    abstract public function isTutorialPlayer(): bool;

    /**
     * Check if this is an NPC
     */
    abstract public function isNPC(): bool;

    /**
     * Does this player have the given row in players_options?
     *
     * Phase 3.2 domain method — replaces legacy
     * `$legacyPlayer->have_option('X')` for entity callers. Backed by
     * the same PlayerOptionsService the legacy shim delegates to
     * (post-Phase 2), so the result matches byte-for-byte.
     *
     * Returns true when hasOption() reports a positive count; the
     * legacy method returns int (duplicate rows push the count above 1)
     * but every caller today treats the value as boolean.
     */
    public function hasOption(\App\Service\PlayerOptionsService $options, string $name): bool
    {
        return $options->hasOption((int) $this->id, $name) > 0;
    }

    /**
     * Plan (map layer) name for this player's current coordinates.
     *
     * One SELECT against `coords` by `coords_id`. Kept as a method
     * rather than a Doctrine relationship so Phase 3 doesn't have to
     * ship a Coords entity — that's a separate mini-phase's concern.
     * Returns null if coords_id points at a non-existent row, which
     * shouldn't happen for real players but is possible for orphaned
     * tutorial rows.
     */
    public function getCoordsPlan(\Doctrine\DBAL\Connection $conn): ?string
    {
        $plan = $conn->fetchOne(
            'SELECT plan FROM coords WHERE id = ? LIMIT 1',
            [$this->coordsId]
        );

        return $plan === false ? null : (string) $plan;
    }

    /**
     * Full coords snapshot (x, y, z, plan) as a value object.
     *
     * Matches the shape of legacy `$player->coords`: a stdClass with
     * int `x`, `y`, `z` and string `plan`. Phase 3.4 deliberately
     * avoids introducing a Coords Doctrine entity — that's a larger
     * design decision, and every Phase 3 caller that touches
     * `->coords->X` is read-only. Returns null when `coords_id` points
     * at a non-existent row.
     */
    public function getCoords(\Doctrine\DBAL\Connection $conn): ?object
    {
        $row = $conn->fetchAssociative(
            'SELECT x, y, z, plan FROM coords WHERE id = ? LIMIT 1',
            [$this->coordsId]
        );

        if ($row === false) {
            return null;
        }

        return (object) [
            'x'    => (int) $row['x'],
            'y'    => (int) $row['y'],
            'z'    => (int) $row['z'],
            'plan' => (string) $row['plan'],
        ];
    }

    /**
     * Ascending-sorted list of option names for this player.
     *
     * Delegates to PlayerOptionsService (the Phase 2 extraction).
     * Replaces legacy `$player->get_options()` on the entity side.
     *
     * @return array<int, string>
     */
    public function getOptions(\App\Service\PlayerOptionsService $options): array
    {
        return $options->getOptions((int) $this->id);
    }

    /**
     * Return a stdClass with every CARACS key populated as race base
     * stat + upgrade count. Matches the shape of `$player->caracs`
     * after legacy `$player->get_caracs(nude: true)`.
     *
     * Phase 3.4b unblocks BourrinsView (which only ever uses the nude
     * path). Items / effects / turn bonuses stay on the legacy pipeline.
     */
    public function getNudeCaracs(\App\Service\PlayerCaracsService $caracs): object
    {
        return $caracs->computeNudeCaracs((int) $this->id, $this->race);
    }
}
