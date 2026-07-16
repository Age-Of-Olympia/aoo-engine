<?php
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "races")]
class Race
{
    /**
     * The 16 stat keys, one DB column each (same keys as the CARACS constant,
     * which config/constants.php defines with UI labels).
     */
    public const CARAC_KEYS = [
        'a', 'mvt', 'p', 'pv', 'cc', 'ct', 'f', 'e',
        'agi', 'pm', 'fm', 'm', 'r', 'rm', 'spd', 'ae',
    ];

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


}
