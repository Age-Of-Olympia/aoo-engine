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
     * Modificateurs de combat, multipliés par la VALEUR portée
     * (players_effects.value) : dexterite(+2) ajoute 2 au jet d'attaque.
     * -1, 0 ou +1.
     */
    #[ORM\Column(type: "smallint", options: ["default" => 0], name: "roll_attack_mod")]
    private int $rollAttackMod = 0;

    #[ORM\Column(type: "smallint", options: ["default" => 0], name: "roll_defense_mod")]
    private int $rollDefenseMod = 0;

    #[ORM\Column(type: "smallint", options: ["default" => 0], name: "damage_dealt_mod")]
    private int $damageDealtMod = 0;

    #[ORM\Column(type: "smallint", options: ["default" => 0], name: "damage_taken_mod")]
    private int $damageTakenMod = 0;

    #[ORM\Column(type: "smallint", options: ["default" => 0], name: "push_attack_mod")]
    private int $pushAttackMod = 0;

    #[ORM\Column(type: "smallint", options: ["default" => 0], name: "push_defense_mod")]
    private int $pushDefenseMod = 0;

    /** Facteur sur les dégâts subis (encaisse : 0.75) — 1 = neutre. */
    #[ORM\Column(type: "float", options: ["default" => 1], name: "damage_taken_factor")]
    private float $damageTakenFactor = 1.0;

    /**
     * Carac dont la récupération est bloquée au nouveau tour ('pv' pour
     * le poison, 'pm' pour le poison magique) — l'effet expire en
     * bloquant. '' = aucun blocage.
     */
    #[ORM\Column(type: "string", length: 4, options: ["default" => ""], name: "block_recovery")]
    private string $blockRecovery = '';

    /** Régénération : la récup PV du tour gagne +RM, l'effet expire. */
    #[ORM\Column(type: "boolean", options: ["default" => false], name: "turn_regen")]
    private bool $turnRegen = false;

    /** Au nouveau tour, retire sa valeur en mouvements (ralentissement). */
    #[ORM\Column(type: "boolean", options: ["default" => false], name: "turn_mvt_malus")]
    private bool $turnMvtMalus = false;

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

    public function getRollAttackMod(): int
    {
        return $this->rollAttackMod;
    }

    public function setRollAttackMod(int $mod): void
    {
        $this->rollAttackMod = $mod;
    }

    public function getRollDefenseMod(): int
    {
        return $this->rollDefenseMod;
    }

    public function setRollDefenseMod(int $mod): void
    {
        $this->rollDefenseMod = $mod;
    }

    public function getDamageDealtMod(): int
    {
        return $this->damageDealtMod;
    }

    public function setDamageDealtMod(int $mod): void
    {
        $this->damageDealtMod = $mod;
    }

    public function getDamageTakenMod(): int
    {
        return $this->damageTakenMod;
    }

    public function setDamageTakenMod(int $mod): void
    {
        $this->damageTakenMod = $mod;
    }

    public function getPushAttackMod(): int
    {
        return $this->pushAttackMod;
    }

    public function setPushAttackMod(int $mod): void
    {
        $this->pushAttackMod = $mod;
    }

    public function getPushDefenseMod(): int
    {
        return $this->pushDefenseMod;
    }

    public function setPushDefenseMod(int $mod): void
    {
        $this->pushDefenseMod = $mod;
    }

    public function getDamageTakenFactor(): float
    {
        return $this->damageTakenFactor;
    }

    public function setDamageTakenFactor(float $factor): void
    {
        $this->damageTakenFactor = $factor;
    }

    public function getBlockRecovery(): string
    {
        return $this->blockRecovery;
    }

    public function setBlockRecovery(string $carac): void
    {
        $this->blockRecovery = in_array($carac, ['pv', 'pm'], true) ? $carac : '';
    }

    public function isTurnRegen(): bool
    {
        return $this->turnRegen;
    }

    public function setTurnRegen(bool $turnRegen): void
    {
        $this->turnRegen = $turnRegen;
    }

    public function isTurnMvtMalus(): bool
    {
        return $this->turnMvtMalus;
    }

    public function setTurnMvtMalus(bool $turnMvtMalus): void
    {
        $this->turnMvtMalus = $turnMvtMalus;
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
