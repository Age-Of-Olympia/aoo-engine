<?php
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;

/**
 * Le tronc des types : ce que `races` décrit, quelle que soit la famille.
 *
 * Une seule table porte cinq populations — races jouables, bâtiments, décors,
 * récoltables — et rien ne les séparait qu'un couple de colonnes qui se lit
 * mal : seize races de personnages portent `structure_nature = 'edifice'`
 * parce que la colonne est NOT NULL, pas parce qu'elles sont des bâtiments.
 * Le côté OBJET a son tronc (`GameEntity` et ses déclinaisons) depuis
 * longtemps ; voici celui du côté TYPE.
 * Cadrage complet : docs/design-entity-types-inheritance.md.
 *
 * Le nom reste `Race`, faux mais écrit dans quatre-vingt-une références : le
 * renommer ICI mêlerait un renommage mécanique à un changement de mapping, et
 * rendrait le diff illisible. Il aura son propre lot.
 *
 * Les champs ne bougent pas encore : le tronc les garde tous, et l'étape
 * suivante les fait descendre là où ils veulent dire quelque chose — c'est
 * PHPStan qui dira alors quels appels devenaient impossibles.
 */
#[ORM\Entity]
#[ORM\Table(name: "races")]
#[ORM\InheritanceType("SINGLE_TABLE")]
#[ORM\DiscriminatorColumn(name: "type_kind", type: "string", length: 20)]
#[ORM\DiscriminatorMap([
    'character' => CharacterRace::class,
    'building'  => BuildingType::class,
    'scenery'   => SceneryType::class,
    'resource'  => ResourceType::class,
    'plant'     => PlantType::class,
])]
abstract class Race implements OwnsCaracsInterface
{
    /**
     * The 16 stat keys, one DB column each — alias de la source unique
     * {@see \App\Enum\Caracs::KEYS} (CARACS garde les libellés UI).
     */
    public const CARAC_KEYS = \App\Enum\Caracs::KEYS;

    public const FAMILY_CHARACTER = 'character';
    public const FAMILY_BUILDING = 'building';
    public const FAMILY_SCENERY = 'scenery';
    public const FAMILY_RESOURCE = 'resource';
    public const FAMILY_PLANT = 'plant';

    /** La famille de CE type — le discriminant, dit par la classe. */
    abstract public function familyKey(): string;

    /**
     * Le type vide de la famille que décrit ce couple de colonnes.
     *
     * SEULE dérivation en PHP : un formulaire ou un bundle ne parlent que de
     * `kind` et de `structure_nature`, il faut bien en tirer une classe. Une
     * fois l'objet construit, plus personne ne dérive quoi que ce soit — on
     * demande sa famille à la classe.
     *
     * La même règle vit aussi dans les déclencheurs SQL, où elle rattrape les
     * écrivains qui ignorent la colonne. Les deux ne peuvent pas diverger sans
     * qu'un test le dise : TypeFamilyColumnTest les compare ligne à ligne.
     */
    public static function ofFamily(string $kind, string $structureNature): self
    {
        return match (true) {
            $kind !== 'structure' => new CharacterRace(),
            $structureNature === 'decor' => new SceneryType(),
            $structureNature === 'ressource' => new ResourceType(),
            $structureNature === 'plante' => new PlantType(),
            default => new BuildingType(),
        };
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 50, unique: true)]
    private string $code;

    /**
     * Lowercase race code as stored in players.race ('nain', 'hs'…) — the
     * join key used across the game. NOT the display name; see $label.
     */
    #[ORM\Column(type: "string", length: 100, unique: true)]
    private string $name;

    /** Display name shown to players ("Nain", "Âme"…). */
    #[ORM\Column(type: "string", length: 100, options: ["default" => ""])]
    private string $label = '';

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: "boolean")]
    private bool $playable;

    #[ORM\Column(type: "boolean")]
    private bool $hidden;

    /**
     * Branch of the GameEntity tree this row provides base stats for:
     * 'character' (joueurs, PNJ) or 'structure' (bâtiments, objets
     * uniques). See App\Enum\EntityCategory.
     */
    #[ORM\Column(type: "string", length: 20, options: ["default" => "character"])]
    private string $kind = 'character';

    /**
     * Nature d'un type de STRUCTURE : 'edifice' (vrai bâtiment — a une
     * porte, toujours ouvrable/fermable) ou 'obstacle' (objet construit
     * type mur — pas de porte ; is_open y signifiera un jour la
     * passabilité, mutualisé avec les coffres). Ignoré pour les races
     * de personnages.
     */
    #[ORM\Column(type: "string", length: 20, name: "structure_nature", options: ["default" => "edifice"])]
    private string $structureNature = 'edifice';


    /**
     * Élément de carte versé au sol quand l'entité est blessée
     * ('sang' pour les personnages, '' = rien — un mur ne saigne pas).
     */
    #[ORM\Column(type: "string", length: 50, options: ["default" => "sang"])]
    private string $bleeds = 'sang';

    /**
     * Une STRUCTURE de cette race bloque-t-elle le passage ? Un mur
     * oui, une table non — on lui passe autour comme dessus. Ignoré
     * pour les personnages.
     */
    #[ORM\Column(type: "boolean", options: ["default" => true], name: "blocks_passage")]
    private bool $blocksPassage = true;

    /**
     * Une STRUCTURE de cette race arrête-t-elle les projectiles ? Un
     * mur oui, une table non — la flèche passe au-dessus. Ignoré pour
     * les personnages.
     */
    #[ORM\Column(type: "boolean", options: ["default" => true], name: "blocks_projectiles")]
    private bool $blocksProjectiles = true;

    /**
     * Teinte du voile de blessure (portrait, carte) : le rouge sang
     * historique par défaut, bronze pour une structure par exemple.
     */
    #[ORM\Column(type: "string", length: 20, name: "wound_color", options: ["default" => "#770001"])]
    private string $woundColor = '#770001';

    #[ORM\Column(type: "string", length: 20, options: ["default" => "#FFFFFF"])]
    private string $bgColor = '#FFFFFF';

    #[ORM\Column(type: "string", length: 20, options: ["default" => "black"])]
    private string $color = 'black';

    #[ORM\Column(type: "string", length: 50, options: ["default" => ""])]
    private string $faction = '';

    /** Home plan of the race (informative; not read by game logic yet). */
    #[ORM\Column(type: "string", length: 50, options: ["default" => ""])]
    private string $plan = '';

    /** Player id (possibly a negative PNJ id) of the race's animator. */
    #[ORM\Column(type: "integer", nullable: true)]
    private ?int $animateurId = null;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $a = 0;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $mvt = 0;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $p = 0;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $pv = 0;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $cc = 0;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $ct = 0;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $f = 0;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $e = 0;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $agi = 0;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $pm = 0;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $fm = 0;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $m = 0;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $r = 0;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $rm = 0;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $spd = 0;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $ae = 0;

    #[ORM\Column(type: "integer", options: array("default"=>1))]
    private int $portraitNextNumber = 1;

    #[ORM\Column(type: "integer", options: array("default"=>1))]
    private int $avatarNextNumber = 1;

    /**
     * Many Races have Many Actions (race-gating used by the action workbench
     * import/export). This is a bidirectional association with Action::$races.
     */
    #[ORM\ManyToMany(targetEntity: Action::class, inversedBy: "races")]
    #[ORM\JoinTable(name: "race_actions")]
    private Collection $actions;

    #[ORM\ManyToMany(targetEntity: Recipe::class, inversedBy: "races")]
    #[ORM\JoinTable(name: "race_recipes")]
    private Collection $recipes;

    /** Starter pack granted at player creation, ordered. */
    #[ORM\OneToMany(targetEntity: RaceStarterAction::class, mappedBy: "race", cascade: ["persist", "remove"], orphanRemoval: true)]
    #[ORM\OrderBy(["position" => "ASC"])]
    private Collection $starterActions;

    /** Spells learnable by the race, ordered. */
    #[ORM\OneToMany(targetEntity: RaceSpell::class, mappedBy: "race", cascade: ["persist", "remove"], orphanRemoval: true)]
    #[ORM\OrderBy(["position" => "ASC"])]
    private Collection $spells;

    public function __construct()
    {
        $this->actions = new ArrayCollection();
        $this->recipes = new ArrayCollection();
        $this->starterActions = new ArrayCollection();
        $this->spells = new ArrayCollection();
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
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getLabel(): string
    {
        return $this->label !== '' ? $this->label : ucfirst($this->name);
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

    public function getPlayable(): bool
    {
        return $this->playable;
    }

    public function setPlayable(bool $playable): void
    {
        $this->playable = $playable;
    }

    public function getHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): void
    {
        $this->hidden = $hidden;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function setKind(string $kind): void
    {
        $this->kind = $kind;
    }

    public function isStructureKind(): bool
    {
        return $this->kind === \App\Enum\EntityCategory::Structure->value;
    }

    public function getStructureNature(): string
    {
        return $this->structureNature;
    }

    public function setStructureNature(string $structureNature): void
    {
        $this->structureNature = $structureNature;
    }

    /** Vrai bâtiment (porte Ouvert/Fermé) — par opposition aux murs construits. */
    public function isEdifice(): bool
    {
        return $this->isStructureKind() && $this->structureNature === 'edifice';
    }

    public function getBleeds(): string
    {
        return $this->bleeds;
    }



    public function setBleeds(string $bleeds): void
    {
        $this->bleeds = $bleeds;
    }

    public function blocksPassage(): bool
    {
        return $this->blocksPassage;
    }

    public function setBlocksPassage(bool $blocksPassage): void
    {
        $this->blocksPassage = $blocksPassage;
    }

    public function blocksProjectiles(): bool
    {
        return $this->blocksProjectiles;
    }

    public function setBlocksProjectiles(bool $blocksProjectiles): void
    {
        $this->blocksProjectiles = $blocksProjectiles;
    }

    public function getWoundColor(): string
    {
        return $this->woundColor;
    }

    public function setWoundColor(string $woundColor): void
    {
        $this->woundColor = $woundColor;
    }

    public function getBgColor(): string
    {
        return $this->bgColor;
    }

    public function setBgColor(string $bgColor): void
    {
        $this->bgColor = $bgColor;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): void
    {
        $this->color = $color;
    }

    public function getFaction(): string
    {
        return $this->faction;
    }

    public function setFaction(string $faction): void
    {
        $this->faction = $faction;
    }

    public function getPlan(): string
    {
        return $this->plan;
    }

    public function setPlan(string $plan): void
    {
        $this->plan = $plan;
    }

    public function getAnimateurId(): ?int
    {
        return $this->animateurId;
    }

    public function setAnimateurId(?int $animateurId): void
    {
        $this->animateurId = $animateurId;
    }

    public function getCarac(string $key): int
    {
        $this->assertCaracKey($key);
        return $this->{$key};
    }

    public function setCarac(string $key, int $value): void
    {
        $this->assertCaracKey($key);
        $this->{$key} = $value;
    }

    /**
     * @return array<string, int> All 16 stats, keyed like the CARACS constant.
     */
    public function getCaracs(): array
    {
        $caracs = [];
        foreach (self::CARAC_KEYS as $key) {
            $caracs[$key] = $this->{$key};
        }
        return $caracs;
    }

    private function assertCaracKey(string $key): void
    {
        if (!in_array($key, self::CARAC_KEYS, true)) {
            throw new \InvalidArgumentException("Unknown carac key '{$key}'");
        }
    }

    /**
     * @return string[] Starter-pack action names, in pack order.
     */
    public function getStarterActionNames(): array
    {
        return $this->starterActions
            ->map(static fn (RaceStarterAction $entry): string => $entry->getName())
            ->getValues();
    }

    /**
     * @return string[] Learnable spell names, in list order.
     */
    public function getSpellNames(): array
    {
        return $this->spells
            ->map(static fn (RaceSpell $entry): string => $entry->getName())
            ->getValues();
    }

    /**
     * @return string[] Starter actions ∪ spells (formerly the JSON
     *                  `actionsPack`, which was always this union).
     */
    public function getActionsPackNames(): array
    {
        return array_values(array_unique(array_merge(
            $this->getStarterActionNames(),
            $this->getSpellNames()
        )));
    }

    public function getPortraitNextNumber(): int
    {
        return $this->portraitNextNumber;
    }

    public function incrementPortraitNextNumber(): self
    {
        $this->portraitNextNumber++;
        return $this;
    }

    public function getAvatarNextNumber(): int
    {
        return $this->avatarNextNumber;
    }

    public function incrementAvatarNextNumber(): self
    {
        $this->avatarNextNumber++;
        return $this;
    }

    /**
     * @return Collection<int, Action>
     */
    public function getActions(): Collection
    {
        return $this->actions;
    }

    public function addAction(Action $action): self
    {
        if (!$this->actions->contains($action)) {
            $this->actions->add($action);
            $action->addRace($this); // keep it bidirectional
        }
        return $this;
    }

    public function removeAction(Action $action): self
    {
        if ($this->actions->removeElement($action)) {
            $action->removeRace($this); // keep it bidirectional
        }
        return $this;
    }

    /**
     * @return Collection<int, Recipe>
     */
    public function getRecipes(): Collection
    {
        return $this->recipes;
    }
    public function addRecipe(Recipe $recipe): self
    {
        if (!$this->recipes->contains($recipe)) {
            $this->recipes->add($recipe);
            $recipe->addRace($this); //
        }
        return $this;
    }

    public function removeRecipe(Recipe $recipe): self
    {
        if ($this->recipes->removeElement($recipe)) {
            $recipe->removeRace($this); //
        }
        return $this;
    }

    /** A race owns all sixteen of its caracs; an item owns only its life. */
    public function ownCaracs(): array
    {
        $own = [];

        foreach (self::CARAC_KEYS as $key) {
            $own[$key] = (int) $this->$key;
        }

        return $own;
    }
}
