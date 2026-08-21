<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * One z level of a plan: display name, "no map" flag and visible bounds
 * (formerly the z_levels entries of the plan JSON).
 */
#[ORM\Entity]
#[ORM\Table(name: "plan_z_levels")]
#[ORM\UniqueConstraint(name: "uniq_plan_z", columns: ["plan_id", "z"])]
class PlanZLevel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    // @phpstan-ignore property.unusedType (Doctrine assigne l'id par réflexion)
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Plan::class, inversedBy: "zLevels")]
    #[ORM\JoinColumn(name: "plan_id", nullable: false, onDelete: "CASCADE")]
    private Plan $plan;

    #[ORM\Column(type: "integer")]
    private int $z;

    /** Display name of the level ("Arène", "Niveau -1"…). */
    #[ORM\Column(type: "string", length: 255, options: ["default" => ""])]
    private string $name = '';

    /** True = the level has no local map (bounds are meaningless). */
    #[ORM\Column(name: "map_unavailable", type: "boolean", options: ["default" => false])]
    private bool $mapUnavailable = false;

    /** May chests be placed on this floor? (ChestSiteCondition; default yes) */
    #[ORM\Column(name: "chests_allowed", type: "boolean", options: ["default" => true])]
    private bool $chestsAllowed = true;

    #[ORM\Column(name: "visible_bounds_min_x", type: "integer", nullable: true)]
    private ?int $visibleBoundsMinX = null;

    #[ORM\Column(name: "visible_bounds_max_x", type: "integer", nullable: true)]
    private ?int $visibleBoundsMaxX = null;

    #[ORM\Column(name: "visible_bounds_min_y", type: "integer", nullable: true)]
    private ?int $visibleBoundsMinY = null;

    #[ORM\Column(name: "visible_bounds_max_y", type: "integer", nullable: true)]
    private ?int $visibleBoundsMaxY = null;

    public function __construct(Plan $plan, int $z)
    {
        $this->plan = $plan;
        $this->z = $z;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlan(): Plan
    {
        return $this->plan;
    }

    public function setPlan(Plan $plan): void
    {
        $this->plan = $plan;
    }

    public function getZ(): int
    {
        return $this->z;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function isMapUnavailable(): bool
    {
        return $this->mapUnavailable;
    }

    public function setMapUnavailable(bool $mapUnavailable): void
    {
        $this->mapUnavailable = $mapUnavailable;
    }

    public function allowsChests(): bool
    {
        return $this->chestsAllowed;
    }

    public function setAllowsChests(bool $chestsAllowed): void
    {
        $this->chestsAllowed = $chestsAllowed;
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

    public function hasVisibleBounds(): bool
    {
        return $this->visibleBoundsMinX !== null
            && $this->visibleBoundsMaxX !== null
            && $this->visibleBoundsMinY !== null
            && $this->visibleBoundsMaxY !== null;
    }

    public function setVisibleBounds(?int $minX, ?int $maxX, ?int $minY, ?int $maxY): void
    {
        $this->visibleBoundsMinX = $minX;
        $this->visibleBoundsMaxX = $maxX;
        $this->visibleBoundsMinY = $minY;
        $this->visibleBoundsMaxY = $maxY;
    }
}
