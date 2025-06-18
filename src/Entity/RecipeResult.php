<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

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

    #[ORM\ManyToOne(targetEntity: Item::class,fetch:'EAGER')]
    #[ORM\JoinColumn(name: "item_id", referencedColumnName: "id",nullable: false)]
    protected Item $item;

    //getters and setters
    public function getCount(): int
    {
        return $this->count;
    }
    public function setCount(int $count): void
    {
        $this->count = $count;
    }
    public function getItem(): Item
    {
        return $this->item;
    }
    public function setItem(Item $item): void
    {
        $this->item = $item;
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