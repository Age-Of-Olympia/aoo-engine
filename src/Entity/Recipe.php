<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity]
#[ORM\Table(name: "craft_recipes")]
class Recipe
{
    public function __construct()
    {
        $this->recipeIngredients = new ArrayCollection();
        $this->recipeResults = new ArrayCollection();
    }
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $name = null;


    #[ORM\ManyToOne(targetEntity: Race::class,)]
    #[ORM\JoinColumn(nullable: true)]
    protected ?Race $race = null;

    #[ORM\OneToMany(
        mappedBy: "recipe",
        targetEntity: RecipeIngredient::class,
        cascade: ["persist", "remove"],
        orphanRemoval: true,
    )]
    protected Collection $recipeIngredients;

    #[ORM\OneToMany(
        mappedBy: "recipe",
        targetEntity: RecipeResult::class,
        cascade: ["persist", "remove"],
        orphanRemoval: true,
    )]
    protected Collection $recipeResults;

    //getters and setters
    public function getId(): ?int
    {
        return $this->id;
    }
    public function setId(int $id): void
    {
        $this->id = $id;
    }
    public function getName(): ?string
    {
        return $this->name;
    }
    public function setName(string $name): void
    {
        $this->name = $name;
    }
    public function getRace(): ?Race
    {
        return $this->race;
    }

    public function setRace(?Race $race): void
    {
        $this->race = $race;
    }


    public function addRecipeIngredient(RecipeIngredient $recipeIngredient): void
    {
        if (!$this->recipeIngredients->contains($recipeIngredient)) {
            $this->recipeIngredients[] = $recipeIngredient;
            $recipeIngredient->setRecipe($this);
        }
    }


    public function getRecipeIngredients(): Collection
    {
        return $this->recipeIngredients;
    }
    public function getRecipeResults(): Collection
    {
        return $this->recipeResults;
    }

    public function addRecipeResult(RecipeResult $recipeResult): void
    {
        if (!$this->recipeResults->contains($recipeResult)) {
            $this->recipeResults[] = $recipeResult;
            $recipeResult->setRecipe($this);
        }
    }

}

#[ORM\Entity]
#[ORM\Table(name: "craft_recipes_ingredients")]
class RecipeIngredient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    protected ?int $id = null;

    #[ORM\Column(type: "integer", options: array("default"=>1))]
    private int $count = 1;

    #[ORM\ManyToOne(targetEntity: Recipe::class, inversedBy: "recipeIngredients")]
    #[ORM\JoinColumn(name: "recipe_id", referencedColumnName: "id", nullable: false)]
    private Recipe $recipe;

    #[ORM\Column(type: "integer")]
    private int $item_id;

    //getters and setters
    public function getCount(): int
    {
        return $this->count;
    }
    public function setCount(int $count): void
    {
        $this->count = $count;
    }
    public function getItemId(): int
    {
        return $this->item_id;
    }
    public function setItemId(int $item_id): void
    {
        $this->item_id = $item_id;
    }
    public function getRecipe(): Recipe
    {
        return $this->recipe;
    }
    public function setRecipe(Recipe $recipe): void
    {
        $this->recipe = $recipe;
    }
}

#[ORM\Entity]
#[ORM\Table(name: "craft_recipes_results")]
class RecipeResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    protected ?int $id = null;

    #[ORM\Column(type: "integer", options: array("default"=>1))]
    private int $count = 1;

    #[ORM\ManyToOne(targetEntity: Recipe::class, inversedBy: "recipeResults")]
    #[ORM\JoinColumn(name: "recipe_id", referencedColumnName: "id", nullable: false)]
    private Recipe $recipe;

    #[ORM\Column(type: "integer")]
    private int $item_id;

    //getters and setters
    public function getCount(): int
    {
        return $this->count;
    }
    public function setCount(int $count): void
    {
        $this->count = $count;
    }
    public function getItemId(): int
    {
        return $this->item_id;
    }
    public function setItemId(int $item_id): void
    {
        $this->item_id = $item_id;
    }
    public function getrecipe(): recipe
    {
        return $this->recipe;
    }
    public function setRecipe(Recipe $recipe): void
    {
        $this->recipe = $recipe;
    }

}