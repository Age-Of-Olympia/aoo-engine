<?php

namespace Tests\Various;

use App\Service\RecipeService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * The buildable door: the lockable TYPE existed admin-placed only; the
 * seed gives it the palissade chain — craft the object, construire
 * raises it, build_work makes that a chantier.
 */
#[Group('items-baseline')]
class BuildableDoorTest extends LegacyPlayerFixtureTestCase
{
    public function testTheDoorTypeItsObjectAndItsRecipeAreSeeded(): void
    {
        $type = $this->link->fetchAssociative(
            "SELECT type_kind, lockable, build_work FROM races WHERE name = 'porte_bois'"
        );
        if ($type === false) {
            $this->markTestSkipped("type 'porte_bois' not seeded (run migrations).");
        }
        $this->assertSame('building', (string) $type['type_kind']);
        $this->assertSame(1, (int) $type['lockable'], 'a door that cannot shut is an arch');
        $this->assertGreaterThan(0, (int) $type['build_work'], 'raising a door is a chantier');

        $this->assertSame(
            'constructible',
            (string) $this->link->fetchOne("SELECT type FROM items WHERE name = 'porte_bois'"),
            'the door is crafted then built, the palissade pattern'
        );

        $recipe = $this->link->fetchAssociative("SELECT id, workshop FROM craft_recipes WHERE name = 'porte_bois'");
        if ($recipe === false) {
            $this->markTestSkipped("recette 'porte_bois' not seeded (run migrations).");
        }
        $this->assertNull($recipe['workshop'], 'the door recipe stays basic, like the palissade');
    }

    public function testTheDoorIsCraftedFromWood(): void
    {
        $bois = $this->itemOrSkip('bois');
        $porte = $this->itemOrSkip('porte_bois');

        $recipeService = new RecipeService();
        $crafter = $this->createRealPlayer('GmMenuisier');
        $crafter->get_data();

        $recipe = null;
        foreach ($recipeService->getRecipes($crafter, forItemId: (int) $porte->id) as $candidate) {
            $recipe = $candidate;
        }
        if ($recipe === null) {
            $this->markTestSkipped("recette 'porte_bois' not seeded (run migrations).");
        }

        $bois->add_item($crafter, 15);

        $message = '';
        $this->assertTrue($recipeService->TryCraftRecipe($recipe, $crafter, $message), $message);
        $this->assertSame(1, $porte->get_n($crafter));
        $this->assertSame(0, $bois->get_n($crafter), 'the recipe ate the wood');
    }
}
