<?php
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "factions")]
class Faction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    /**
     * Lowercase faction code as stored in players.faction /
     * players.secretFaction ('eryn_dolen'…) — the join key used across the
     * game (formerly the JSON file basename). NOT the display name; see $name.
     */
    #[ORM\Column(type: "string", length: 100, unique: true)]
    private string $code;

    /** Display name shown to players ("Eryn Dolen", "La Forge Sacrée"…). */
    #[ORM\Column(type: "string", length: 100, options: ["default" => ""])]
    private string $name = '';

    /** Lore shown on the faction page (formerly JSON `text`). */
    #[ORM\Column(type: "text", nullable: true)]
    private ?string $text = null;

    /** RPG-Awesome icon css class ('ra-moon-sun'…). */
    #[ORM\Column(type: "string", length: 50, options: ["default" => ""])]
    private string $raFont = '';

    /** Plan members respawn to when leaving the underworld. */
    #[ORM\Column(type: "string", length: 50, options: ["default" => "olympia"])]
    private string $respawnPlan = 'olympia';

    /** Faction page only visible to admins. */
    #[ORM\Column(type: "boolean", options: ["default" => false])]
    private bool $hidden = false;

    /**
     * Secret faction: membership lives in players.secretFaction and the
     * roster is hidden from non-members ("grand mystère").
     */
    #[ORM\Column(type: "boolean", options: ["default" => false])]
    private bool $secret = false;

    /** Ordered roles; players.factionRole indexes into this by position. */
    #[ORM\OneToMany(targetEntity: FactionRole::class, mappedBy: "faction", cascade: ["persist", "remove"], orphanRemoval: true)]
    #[ORM\OrderBy(["position" => "ASC"])]
    private Collection $roles;

    public function __construct()
    {
        $this->roles = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getName(): string
    {
        return $this->name !== '' ? $this->name : ucwords(str_replace('_', ' ', $this->code));
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getText(): string
    {
        return $this->text ?? '';
    }

    public function setText(string $text): void
    {
        $this->text = $text;
    }

    public function getRaFont(): string
    {
        return $this->raFont;
    }

    public function setRaFont(string $raFont): void
    {
        $this->raFont = $raFont;
    }

    public function getRespawnPlan(): string
    {
        return $this->respawnPlan;
    }

    public function setRespawnPlan(string $respawnPlan): void
    {
        $this->respawnPlan = $respawnPlan;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): void
    {
        $this->hidden = $hidden;
    }

    public function isSecret(): bool
    {
        return $this->secret;
    }

    public function setSecret(bool $secret): void
    {
        $this->secret = $secret;
    }

    /**
     * @return Collection<int, FactionRole> Ordered by position.
     */
    public function getRoles(): Collection
    {
        return $this->roles;
    }

    /**
     * @return string[] Role names, in position order.
     */
    public function getRoleNames(): array
    {
        return $this->roles
            ->map(static fn (FactionRole $role): string => $role->getName())
            ->getValues();
    }
}
