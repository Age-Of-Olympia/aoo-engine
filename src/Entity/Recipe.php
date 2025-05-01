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
        $this->reciepeIngredients = new ArrayCollection();
        $this->reciepeResults = new ArrayCollection();
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
        mappedBy: "reciepe",
        targetEntity: ReciepeIngredient::class,
        cascade: ["persist", "remove"],
        orphanRemoval: true,
    )]
    protected Collection $reciepeIngredients;

    #[ORM\OneToMany(
        mappedBy: "reciepe",
        targetEntity: ReciepeIngredient::class,
        cascade: ["persist", "remove"],
        orphanRemoval: true,
    )]
    protected Collection $reciepeResults;

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

    public function addReciepeIngredient(ReciepeIngredient $reciepeIngredient): void
    {
        if (!$this->reciepeIngredients->contains($reciepeIngredient)) {
            $this->reciepeIngredients[] = $reciepeIngredient;
            $reciepeIngredient->setRecipe($this);
        }
    }

    public function addReciepeResult(ReciepeResult $reciepeResult): void
    {
        if (!$this->reciepeResults->contains($reciepeResult)) {
            $this->reciepeResults[] = $reciepeResult;
            $reciepeResult->setRecipe($this);
        }
    }

}

#[ORM\Entity]
#[ORM\Table(name: "craft_recipes_ingredients")]
class ReciepeIngredient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    protected ?int $id = null;

    #[ORM\Column(type: "integer", options: array("default"=>1))]
    private int $count = 1;

    #[ORM\ManyToOne(targetEntity: Recipe::class, inversedBy: "reciepeIngredients")]
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
class ReciepeResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    protected ?int $id = null;

    #[ORM\Column(type: "integer", options: array("default"=>1))]
    private int $count = 1;

    #[ORM\ManyToOne(targetEntity: Recipe::class, inversedBy: "reciepeResults")]
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