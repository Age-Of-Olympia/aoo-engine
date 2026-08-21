<?php
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Per-plan configuration, formerly datas/private/plans/<plan>.json.
 *
 * Identified by $slug — the value coords.plan, factions.respawnPlan and
 * race_harvest.plan already store (formerly the JSON file basename).
 */
#[ORM\Entity]
#[ORM\Table(name: "plans")]
class Plan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    // @phpstan-ignore property.unusedType (Doctrine assigne l'id par réflexion)
    private ?int $id = null;

    /** Join key ('olympia', 'tutorial', 'tut_<uuid>'…), NOT the display name. */
    #[ORM\Column(type: "string", length: 100, unique: true)]
    private string $slug;

    /** Display name shown to players ("Olympia", "Tutoriel"…). */
    #[ORM\Column(type: "string", length: 255)]
    private string $name;

    /** Compact name for tight HUD spots (minimap header). */
    #[ORM\Column(name: "short_name", type: "string", length: 255, nullable: true)]
    private ?string $shortName = null;

    /** Position of the territory on the olympia world map; null = off-grid dungeon. */
    #[ORM\Column(type: "integer", nullable: true)]
    private ?int $x = null;

    #[ORM\Column(type: "integer", nullable: true)]
    private ?int $y = null;

    /** False hides other player characters on the board, feed and observe list. */
    #[ORM\Column(name: "player_visibility", type: "boolean", options: ["default" => true])]
    private bool $playerVisibility = true;

    /** True lists the plan as a location on the world map without discovery. */
    #[ORM\Column(name: "visible_by_default", type: "boolean", options: ["default" => false])]
    private bool $visibleByDefault = false;

    /** NPC (negative player id) presented by the local map page. */
    #[ORM\Column(type: "integer", nullable: true)]
    private ?int $pnj = null;

    /** Tile render size override. */
    #[ORM\Column(type: "integer", nullable: true)]
    private ?int $size = null;

    /** Board background image path ('img/tiles/<name>.png'); null = per-plan default file. */
    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $bg = null;

    /** Scrolling overlay image path (fog, clouds). */
    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $mask = null;

    /** Overlay scroll period in seconds; fractional values are real data. */
    #[ORM\Column(name: "scrolling_mask", type: "float", nullable: true)]
    private ?float $scrollingMask = null;

    /** Overlay scrolls vertically instead of horizontally. */
    #[ORM\Column(name: "vertical_scrolling", type: "boolean", options: ["default" => false])]
    private bool $verticalScrolling = false;

    /** Cell shade overrides; null = global admin defaults (CellShadeService). */
    #[ORM\Column(name: "shade_step", type: "float", nullable: true)]
    private ?float $shadeStep = null;

    #[ORM\Column(name: "shade_max", type: "integer", nullable: true)]
    private ?int $shadeMax = null;

    #[ORM\Column(name: "shade_color", type: "string", length: 7, nullable: true)]
    private ?string $shadeColor = null;

    /**
     * Top-level visible bounds: the world-plan form (olympia) and the legacy
     * fallback for plans without z levels. Levels carry their own bounds.
     */
    #[ORM\Column(name: "visible_bounds_min_x", type: "integer", nullable: true)]
    private ?int $visibleBoundsMinX = null;

    #[ORM\Column(name: "visible_bounds_max_x", type: "integer", nullable: true)]
    private ?int $visibleBoundsMaxX = null;

    #[ORM\Column(name: "visible_bounds_min_y", type: "integer", nullable: true)]
    private ?int $visibleBoundsMinY = null;

    #[ORM\Column(name: "visible_bounds_max_y", type: "integer", nullable: true)]
    private ?int $visibleBoundsMaxY = null;

    /**
     * Raw biome list (wall/ressource/exhaust/regrow), JSON-encoded. Seed
     * source for race_harvest only — never read at play time.
     */
    #[ORM\Column(type: "text", nullable: true)]
    private ?string $biomes = null;

    #[ORM\OneToMany(targetEntity: PlanZLevel::class, mappedBy: "plan", cascade: ["persist", "remove"], orphanRemoval: true)]
    #[ORM\OrderBy(["z" => "ASC"])]
    private Collection $zLevels;

    public function __construct(string $slug, string $name)
    {
        $this->slug = $slug;
        $this->name = $name;
        $this->zLevels = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    public function setShortName(?string $shortName): void
    {
        $this->shortName = $shortName;
    }

    public function getX(): ?int
    {
        return $this->x;
    }

    public function setX(?int $x): void
    {
        $this->x = $x;
    }

    public function getY(): ?int
    {
        return $this->y;
    }

    public function setY(?int $y): void
    {
        $this->y = $y;
    }

    public function isPlayerVisibility(): bool
    {
        return $this->playerVisibility;
    }

    public function setPlayerVisibility(bool $playerVisibility): void
    {
        $this->playerVisibility = $playerVisibility;
    }

    public function isVisibleByDefault(): bool
    {
        return $this->visibleByDefault;
    }

    public function setVisibleByDefault(bool $visibleByDefault): void
    {
        $this->visibleByDefault = $visibleByDefault;
    }

    public function getPnj(): ?int
    {
        return $this->pnj;
    }

    public function setPnj(?int $pnj): void
    {
        $this->pnj = $pnj;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(?int $size): void
    {
        $this->size = $size;
    }

    public function getBg(): ?string
    {
        return $this->bg;
    }

    public function setBg(?string $bg): void
    {
        $this->bg = $bg;
    }

    public function getMask(): ?string
    {
        return $this->mask;
    }

    public function setMask(?string $mask): void
    {
        $this->mask = $mask;
    }

    public function getScrollingMask(): ?float
    {
        return $this->scrollingMask;
    }

    public function setScrollingMask(?float $scrollingMask): void
    {
        $this->scrollingMask = $scrollingMask;
    }

    public function isVerticalScrolling(): bool
    {
        return $this->verticalScrolling;
    }

    public function setVerticalScrolling(bool $verticalScrolling): void
    {
        $this->verticalScrolling = $verticalScrolling;
    }

    public function getShadeStep(): ?float
    {
        return $this->shadeStep;
    }

    public function setShadeStep(?float $shadeStep): void
    {
        $this->shadeStep = $shadeStep;
    }

    public function getShadeMax(): ?int
    {
        return $this->shadeMax;
    }

    public function setShadeMax(?int $shadeMax): void
    {
        $this->shadeMax = $shadeMax;
    }

    public function getShadeColor(): ?string
    {
        return $this->shadeColor;
    }

    public function setShadeColor(?string $shadeColor): void
    {
        $this->shadeColor = $shadeColor;
    }

    public function getVisibleBoundsMinX(): ?int
    {
        return $this->visibleBoundsMinX;
    }

    public function getVisibleBoundsMaxX(): ?int
    {
        return $this->visibleBoundsMaxX;
    }

    public function getVisibleBoundsMinY(): ?int
    {
        return $this->visibleBoundsMinY;
    }

    public function getVisibleBoundsMaxY(): ?int
    {
        return $this->visibleBoundsMaxY;
    }

    public function setVisibleBounds(?int $minX, ?int $maxX, ?int $minY, ?int $maxY): void
    {
        $this->visibleBoundsMinX = $minX;
        $this->visibleBoundsMaxX = $maxX;
        $this->visibleBoundsMinY = $minY;
        $this->visibleBoundsMaxY = $maxY;
    }

    /** @return list<array{wall: string, ressource: string, exhaust: ?int, regrow: ?int}>|null */
    public function getBiomes(): ?array
    {
        if ($this->biomes === null) {
            return null;
        }

        $decoded = json_decode($this->biomes, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function setBiomes(?array $biomes): void
    {
        $this->biomes = $biomes === null
            ? null
            : json_encode($biomes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @return Collection<int, PlanZLevel> */
    public function getZLevels(): Collection
    {
        return $this->zLevels;
    }

    public function getZLevel(int $z): ?PlanZLevel
    {
        foreach ($this->zLevels as $level) {
            if ($level->getZ() === $z) {
                return $level;
            }
        }

        return null;
    }

    public function addZLevel(PlanZLevel $level): void
    {
        if (!$this->zLevels->contains($level)) {
            $this->zLevels->add($level);
            $level->setPlan($this);
        }
    }

    public function removeZLevel(PlanZLevel $level): void
    {
        $this->zLevels->removeElement($level);
    }
}
