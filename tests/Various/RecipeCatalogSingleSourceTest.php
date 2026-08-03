<?php

namespace Tests\Various;

use Classes\Item;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * The craft_recipes catalog is the single recipe source: what an object
 * is made of (Item::get_recipe, is_crafted_with) comes from the tables
 * the admin edits, never from the legacy crafts.json.
 */
#[Group('items-baseline')]
class RecipeCatalogSingleSourceTest extends LegacyPlayerFixtureTestCase
{
    /** @var int[] */
    private array $createdRecipeIds = [];

    protected function tearDown(): void
    {
        foreach ($this->createdRecipeIds as $recipeId) {
            $this->link->executeStatement('DELETE FROM craft_recipes_ingredients WHERE recipe_id = ?', [$recipeId]);
            $this->link->executeStatement('DELETE FROM craft_recipes_results WHERE recipe_id = ?', [$recipeId]);
            $this->link->executeStatement('DELETE FROM craft_recipes WHERE id = ?', [$recipeId]);
        }
        $this->createdRecipeIds = [];
        parent::tearDown();
    }

    public function testAnObjectAnswersTheCatalogRecipe(): void
    {
        $bois = $this->itemOrSkip('bois');
        $pierre = $this->itemOrSkip('pierre');
        $this->seedRecipe('pierre', [(int) $bois->id => 5], (int) $pierre->id);

        $object = $this->itemOrSkip('pierre');
        $this->assertSame(['bois' => 5], $object->get_recipe(), 'the catalog recipe is the answer');
        $this->assertTrue($object->is_crafted_with('bois'));
        $this->assertFalse($object->is_crafted_with('fer'));
    }

    public function testAnObjectNothingCraftsAnswersAnEmptyRecipe(): void
    {
        $bois = $this->itemOrSkip('bois');
        $this->assertSame([], $bois->get_recipe());
        $this->assertFalse($bois->is_crafted_with('bois'));
    }

    public function testTheRecipeBearingTheItemNameBeatsAnOlderVariant(): void
    {
        $bois = $this->itemOrSkip('bois');
        $pierre = $this->itemOrSkip('pierre');

        $this->seedRecipe('taille_de_pierre', [(int) $bois->id => 2], (int) $pierre->id);
        $this->seedRecipe('pierre', [(int) $bois->id => 5], (int) $pierre->id);

        $object = $this->itemOrSkip('pierre');
        $this->assertSame(
            ['bois' => 5],
            $object->get_recipe(),
            'the recipe named after the item wins over an older variant'
        );
    }

    public function testTheMemoIsPerObjectNotPerCatalog(): void
    {
        $bois = $this->itemOrSkip('bois');
        $pierre = $this->itemOrSkip('pierre');

        $before = $this->itemOrSkip('pierre');
        $this->assertSame([], $before->get_recipe());

        $this->seedRecipe('pierre', [(int) $bois->id => 5], (int) $pierre->id);

        $this->assertSame([], $before->get_recipe(), 'an object keeps the recipe it resolved');
        $this->assertSame(['bois' => 5], $this->itemOrSkip('pierre')->get_recipe(), 'a fresh object reads the catalog');
    }

    /** @param array<int, int> $ingredientCounts item id => count */
    private function seedRecipe(string $name, array $ingredientCounts, int $resultItemId, int $resultCount = 1): int
    {
        $this->link->executeStatement('INSERT INTO craft_recipes (name) VALUES (?)', [$name]);
        $recipeId = (int) $this->link->lastInsertId();
        $this->createdRecipeIds[] = $recipeId;

        foreach ($ingredientCounts as $itemId => $count) {
            $this->link->executeStatement(
                'INSERT INTO craft_recipes_ingredients (recipe_id, item_id, count) VALUES (?, ?, ?)',
                [$recipeId, $itemId, $count]
            );
        }
        $this->link->executeStatement(
            'INSERT INTO craft_recipes_results (recipe_id, item_id, count) VALUES (?, ?, ?)',
            [$recipeId, $resultItemId, $resultCount]
        );

        return $recipeId;
    }
}
