<?php
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * One entry of the effect catalog (formerly the EFFECTS_RA_FONT,
 * EFFECTS_HIDDEN, EFFECTS_TXT, ELE_DEBUFFS/ELE_BUFFS, ELE_CONTROLS and
 * ITEM_CORRUPTIONS/ITEM_CORRUPT_BREAKCHANCES constants).
 *
 * This is the DEFINITION of an effect; the per-player state stays in
 * players_effects ({@see PlayerEffect}), whose `name` references
 * effects.name.
 */
#[ORM\Entity]
#[ORM\Table(name: "effects")]
class Effect
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    /**
     * Lowercase key stored in players_effects.name and in action
     * parameters ('feu', 'parade'…) — the join key. NOT the display
     * name; see $label.
     */
    #[ORM\Column(type: "string", length: 100, unique: true)]
    private string $name;

    /** Display name ("Clé de bras", "Corruption du Bois"…). */
    #[ORM\Column(type: "string", length: 100, options: ["default" => ""])]
    private string $label = '';

    /** Rule text shown in the wiki / tooltips. */
    #[ORM\Column(type: "text", nullable: true)]
    private ?string $description = null;

    /** RPG-Awesome icon class ('ra-small-fire'…). */
    #[ORM\Column(type: "string", length: 50, options: ["default" => "ra-fairy-wand"])]
    private string $icon = 'ra-fairy-wand';

    /**
     * Ephemeral combat stance (parade, leurre…) : purged at new turn or
     * on use, never listed on the character sheets.
     */
    #[ORM\Column(type: "boolean", options: ["default" => false])]
    private bool $hidden = false;

    /** Carac raised by 1 while the effect lasts (null = none). */
    #[ORM\Column(type: "string", length: 10, nullable: true, name: "buff_carac")]
    private ?string $buffCarac = null;

    /** Carac lowered by 1 while the effect lasts (null = none). */
    #[ORM\Column(type: "string", length: 10, nullable: true, name: "debuff_carac")]
    private ?string $debuffCarac = null;

    /**
     * Cancellation list: applying THIS effect removes each controlled
     * effect from the target, and a target already carrying one of this
     * effect's controllers cancels both (eau éteint feu…). The inverse
     * map the old ELE_IS_CONTROLED constant hand-maintained is computed
     * ({@see \App\Service\EffectService::getControllersOf}).
     *
     * @var Collection<int, EffectControl>
     */
    #[ORM\OneToMany(targetEntity: EffectControl::class, mappedBy: "effect", cascade: ["persist", "remove"], orphanRemoval: true)]
    #[ORM\OrderBy(["position" => "ASC"])]
    private Collection $controls;

    /**
     * Extra break chance (percent) a corruption adds to worn items made
     * of its materials. Null = not a corruption; the material list lives
     * in effect_corruption_materials.
     */
    #[ORM\Column(type: "integer", nullable: true, name: "corruption_break_chance")]
    private ?int $corruptionBreakChance = null;

    /**
     * Map marker (trace_pas…) : transits through players_effects but is
     * no gameplay effect — excluded from admin dropdowns and sheets.
     */
    #[ORM\Column(type: "boolean", options: ["default" => false], name: "is_map_marker")]
    private bool $mapMarker = false;

    /** @var Collection<int, EffectCorruptionMaterial> */
    #[ORM\OneToMany(targetEntity: EffectCorruptionMaterial::class, mappedBy: "effect", cascade: ["persist", "remove"], orphanRemoval: true)]
    #[ORM\OrderBy(["position" => "ASC"])]
    private Collection $corruptionMaterials;

    public function __construct(string $name)
    {
        $this->name = strtolower($name);
        $this->corruptionMaterials = new ArrayCollection();
        $this->controls = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = strtolower($name);
    }

    public function getLabel(): string
    {
        return $this->label !== '' ? $this->label : ucfirst(strtr($this->name, '_', ' '));
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getDescription(): string
    {
        return $this->description ?? '';
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function setIcon(string $icon): void
    {
        $this->icon = $icon;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): void
    {
        $this->hidden = $hidden;
    }

    public function getBuffCarac(): ?string
    {
        return $this->buffCarac;
    }

    public function setBuffCarac(?string $carac): void
    {
        $this->buffCarac = $carac !== '' ? $carac : null;
    }

    public function getDebuffCarac(): ?string
    {
        return $this->debuffCarac;
    }

    public function setDebuffCarac(?string $carac): void
    {
        $this->debuffCarac = $carac !== '' ? $carac : null;
    }

    /** @return string[] Names of the effects this one cancels. */
    public function getControlNames(): array
    {
        return array_map(
            static fn (EffectControl $control): string => $control->getName(),
            $this->controls->toArray()
        );
    }

    public function getCorruptionBreakChance(): ?int
    {
        return $this->corruptionBreakChance;
    }

    public function setCorruptionBreakChance(?int $chance): void
    {
        $this->corruptionBreakChance = $chance;
    }

    public function isMapMarker(): bool
    {
        return $this->mapMarker;
    }

    public function setMapMarker(bool $mapMarker): void
    {
        $this->mapMarker = $mapMarker;
    }

    /** @return string[] Material names this corruption can break. */
    public function getCorruptionMaterialNames(): array
    {
        return array_map(
            static fn (EffectCorruptionMaterial $material): string => $material->getName(),
            $this->corruptionMaterials->toArray()
        );
    }

    /** @return Collection<int, EffectCorruptionMaterial> */
    public function getCorruptionMaterials(): Collection
    {
        return $this->corruptionMaterials;
    }
}
