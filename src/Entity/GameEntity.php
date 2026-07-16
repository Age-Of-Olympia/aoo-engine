<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * GameEntity — Single Table Inheritance root for everything that lives on
 * the map as a `players` row (docs/design-buildings-entities.md §4.3).
 *
 * This is the old PlayerEntity split in two:
 *   - GameEntity (this class): what EVERY map entity shares — identity,
 *     position, presentation, and the PV surface. `race` lives here on
 *     purpose: it points into the `races` catalog, which is the max-PV
 *     source for characters AND for structures (pseudo-races, §4.6).
 *   - Character: what only played/playing characters have — account,
 *     progression, faction, turn timing.
 *
 * Hard rule from the plan: the hierarchy never grows a third level.
 * When a new type doesn't fit Character/Structure, add a component
 * (satellite table), don't fork the tree.
 */
#[ORM\Entity]
#[ORM\Table(name: "players")]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'player_type', type: 'string')]
#[ORM\DiscriminatorMap([
    'real' => RealPlayer::class,
    'tutorial' => TutorialPlayer::class,
    'npc' => NonPlayerCharacter::class,
    'building' => Building::class,
    'unique' => UniqueObject::class
])]
abstract class GameEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    protected ?int $id = null;

    #[ORM\Column(type: "integer", name: "display_id", nullable: true)]
    private ?int $displayId = null;

    #[ORM\Column(type: "string", length: 255)]
    protected string $name = '';

    #[ORM\Column(type: "integer", name: "coords_id")]
    protected int $coordsId = 0;

    /**
     * Pointer into the `races` catalog — the base-stats row of this entity.
     * Characters use playable races; structures will use non-playable
     * pseudo-races (races.playable = false) as their PV archetype.
     */
    #[ORM\Column(type: "string", length: 255)]
    protected string $race = '';

    #[ORM\Column(type: "string", length: 255)]
    protected string $avatar = '';

    #[ORM\Column(type: "string", length: 255)]
    protected string $portrait = '';

    #[ORM\Column(type: "text")]
    protected string $text = 'Débutant, chaud devant !';

    #[ORM\Column(type: "integer")]
    protected int $registerTime = 0;

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

    public function getRegisterTime(): int
    {
        return $this->registerTime;
    }

    public function setRegisterTime(int $registerTime): self
    {
        $this->registerTime = $registerTime;
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
     * Does this entity have the given row in players_options?
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
     * Plan (map layer) name for this entity's current coordinates.
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
     * Ascending-sorted list of option names for this entity.
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
     * This is the shared PV surface: a structure's max PV comes out of
     * exactly this computation, through its pseudo-race row.
     */
    public function getNudeCaracs(\App\Service\PlayerCaracsService $caracs): object
    {
        return $caracs->computeNudeCaracs((int) $this->id, $this->race);
    }
}
