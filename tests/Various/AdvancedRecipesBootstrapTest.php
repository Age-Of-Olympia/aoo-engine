<?php

namespace Tests\Various;

use App\Service\RecipeService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * The atelier bootstrap: the building advanced recipes require must
 * itself be reachable from nothing — its own recipe stays BASIC
 * (workshop NULL), or no atelier could ever exist. And once one
 * stands, the advanced level opens beside it: the full loop.
 */
#[Group('items-baseline')]
class AdvancedRecipesBootstrapTest extends LegacyPlayerFixtureTestCase
{
    private ?int $advancedRecipeId = null;

    protected function tearDown(): void
    {
        if ($this->advancedRecipeId !== null) {
            foreach (['craft_recipes_ingredients', 'craft_recipes_results'] as $table) {
                $this->link->executeStatement("DELETE FROM {$table} WHERE recipe_id = ?", [$this->advancedRecipeId]);
            }
            $this->link->executeStatement('DELETE FROM craft_recipes WHERE id = ?', [$this->advancedRecipeId]);
            $this->advancedRecipeId = null;
        }
        parent::tearDown();
    }

    public function testTheAtelierTypeItsObjectAndItsRecipeAreSeeded(): void
    {
        $type = $this->link->fetchAssociative(
            "SELECT type_kind, lockable, hidden, playable FROM races WHERE name = 'atelier'"
        );
        if ($type === false) {
            $this->markTestSkipped("type 'atelier' not seeded (run migrations).");
        }
        $this->assertSame('building', (string) $type['type_kind']);
        $this->assertSame(1, (int) $type['lockable'], 'a closed atelier serves nobody: it needs a door');
        $this->assertSame(1, (int) $type['hidden']);
        $this->assertSame(0, (int) $type['playable']);

        $this->assertSame(
            'constructible',
            (string) $this->link->fetchOne("SELECT type FROM items WHERE name = 'atelier'"),
            'the atelier is crafted then built, the palissade pattern'
        );
    }

    public function testTheAtelierRecipeStaysBasic(): void
    {
        $workshop = $this->link->fetchAssociative("SELECT workshop FROM craft_recipes WHERE name = 'atelier'");
        if ($workshop === false) {
            $this->markTestSkipped("recette 'atelier' not seeded (run migrations).");
        }

        $this->assertNull(
            $workshop['workshop'],
            "the atelier's own recipe must stay BASIC: the first one is crafted without an atelier"
        );
    }

    public function testTheFullLoopFromNothingToAdvancedCrafting(): void
    {
        $this->requireBuildingsOrSkip();
        $bois = $this->itemOrSkip('bois');
        $pierre = $this->itemOrSkip('pierre');
        $atelierItem = $this->itemOrSkip('atelier');

        $recipeService = new RecipeService();
        $atelierRecipe = null;
        foreach ($recipeService->getRecipes($this->createRealPlayer('GmScout'), forItemId: (int) $atelierItem->id) as $candidate) {
            $atelierRecipe = $candidate;
        }
        if ($atelierRecipe === null) {
            $this->markTestSkipped("recette 'atelier' not seeded (run migrations).");
        }

        // From nothing: craft the atelier object in the open field.
        $crafter = $this->createRealPlayer('GmPioneer');
        $this->movePlayerTo($crafter->id, 60, 60);
        $crafter->getCoords();
        $crafter->get_caracs();
        $bois->add_item($crafter, 35);
        $pierre->add_item($crafter, 10);

        $message = '';
        $this->assertTrue($recipeService->TryCraftRecipe($atelierRecipe, $crafter, $message), $message);
        $this->assertSame(1, $atelierItem->get_n($crafter));

        // The built form stands; the advanced level opens beside it.
        $this->placeStructure('atelier', 61, 61);

        $this->link->executeStatement(
            "INSERT INTO craft_recipes (name, workshop) VALUES ('taille_fine_test', 'atelier')"
        );
        $this->advancedRecipeId = (int) $this->link->lastInsertId();
        $this->link->executeStatement(
            'INSERT INTO craft_recipes_ingredients (recipe_id, item_id, count) VALUES (?, ?, 5)',
            [$this->advancedRecipeId, (int) $bois->id]
        );
        $this->link->executeStatement(
            'INSERT INTO craft_recipes_results (recipe_id, item_id, count) VALUES (?, ?, 1)',
            [$this->advancedRecipeId, (int) $pierre->id]
        );

        $advanced = $recipeService->getRecipeById($this->advancedRecipeId);
        $message = '';
        $this->assertTrue($recipeService->TryCraftRecipe($advanced, $crafter, $message), $message);
        $this->assertSame(1, $pierre->get_n($crafter), 'the atelier ate the 10 pierre; this one was crafted at it');
    }
}
