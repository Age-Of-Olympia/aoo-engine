<?php

namespace Tests\Action;

use App\Service\BuildingService;
use App\Service\RecipeService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * The two levels of crafting: a basic recipe (workshop NULL) crafts
 * anywhere; an advanced one names the building type an OPEN specimen of
 * which must stand within reach. The gate lives in TryCraftRecipe —
 * server-side truth, whatever posted the gesture.
 */
#[Group('items-baseline')]
class AdvancedRecipeNeedsWorkshopTest extends LegacyPlayerFixtureTestCase
{
    private ?int $recipeId = null;

    protected function tearDown(): void
    {
        if ($this->recipeId !== null) {
            foreach (['craft_recipes_ingredients', 'craft_recipes_results'] as $table) {
                $this->link->executeStatement("DELETE FROM {$table} WHERE recipe_id = ?", [$this->recipeId]);
            }
            $this->link->executeStatement('DELETE FROM craft_recipes WHERE id = ?', [$this->recipeId]);
            $this->recipeId = null;
        }
        parent::tearDown();
    }

    /** Seeds an advanced pierre recipe (5 bois, at a taverne) and a stocked crafter. */
    private function crafterWithAdvancedRecipe(int $x, int $y): array
    {
        $bois = $this->itemOrSkip('bois');
        $pierre = $this->itemOrSkip('pierre');

        $this->link->executeStatement(
            'INSERT INTO craft_recipes (name, workshop) VALUES (?, ?)',
            ['pierre_taillee_test', 'taverne']
        );
        $this->recipeId = (int) $this->link->lastInsertId();
        $this->link->executeStatement(
            'INSERT INTO craft_recipes_ingredients (recipe_id, item_id, count) VALUES (?, ?, 5)',
            [$this->recipeId, (int) $bois->id]
        );
        $this->link->executeStatement(
            'INSERT INTO craft_recipes_results (recipe_id, item_id, count) VALUES (?, ?, 1)',
            [$this->recipeId, (int) $pierre->id]
        );

        $crafter = $this->createRealPlayer('GmArtisan');
        $this->movePlayerTo($crafter->id, $x, $y);
        $crafter->getCoords();
        $crafter->get_caracs();
        $bois->add_item($crafter, 5);

        $recipe = (new RecipeService())->getRecipeById($this->recipeId);

        return [$crafter, $recipe, $bois, $pierre];
    }

    public function testFarFromAnyWorkshopTheRecipeRefusesAndNamesIt(): void
    {
        $this->requireBuildingsOrSkip();
        [$crafter, $recipe, $bois] = $this->crafterWithAdvancedRecipe(50, 50);

        $message = '';
        $ok = (new RecipeService())->TryCraftRecipe($recipe, $crafter, $message);

        $this->assertFalse($ok);
        $this->assertStringContainsString('Il faut être près de', $message);
        $this->assertSame(5, $bois->get_n($crafter), 'a refusal consumes nothing');
    }

    public function testBesideAnOpenWorkshopTheRecipeCrafts(): void
    {
        $this->requireBuildingsOrSkip();
        [$crafter, $recipe, $bois, $pierre] = $this->crafterWithAdvancedRecipe(50, 50);
        $this->placeStructure('taverne', 51, 51);

        $message = '';
        $ok = (new RecipeService())->TryCraftRecipe($recipe, $crafter, $message);

        $this->assertTrue($ok, $message);
        $this->assertSame(0, $bois->get_n($crafter));
        $this->assertSame(1, $pierre->get_n($crafter));
    }

    public function testAShutWorkshopRefusesWithItsReason(): void
    {
        $this->requireBuildingsOrSkip();
        [$crafter, $recipe, $bois] = $this->crafterWithAdvancedRecipe(50, 50);
        $id = $this->placeStructure('taverne', 51, 51);
        (new BuildingService())->markDestroyed($id);

        $message = '';
        $ok = (new RecipeService())->TryCraftRecipe($recipe, $crafter, $message);

        $this->assertFalse($ok);
        $this->assertStringContainsString('en ruine', $message);
        $this->assertSame(5, $bois->get_n($crafter), 'a refusal consumes nothing');
    }
}
