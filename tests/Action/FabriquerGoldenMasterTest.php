<?php

namespace Tests\Action;

use App\Factory\ActionFactory;
use App\Factory\PlayerFactory;
use App\Service\ActionExecutorService;
use App\Service\RecipeService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * L'action 'fabriquer' de bout en bout (cadrage du 2026-07-19) : la
 * recette arrive au geste (POST recipeId), les règles et la
 * consommation restent à RecipeService (source unique) — même boucle
 * 10 bois → palissade que le golden master construire, jouée par le
 * MOTEUR. Type 'craft' sans règle d'XP : aucune XP (parité craft
 * historique, gratuit).
 */
#[Group('items-golden-master')]
class FabriquerGoldenMasterTest extends LegacyPlayerFixtureTestCase
{
    private function actionOrSkip(): \App\Interface\ActionInterface
    {
        $action = ActionFactory::getAction('fabriquer');
        if ($action === null) {
            $this->markTestSkipped("actions catalog not seeded (no 'fabriquer' row — run migrations).");
        }

        return $action;
    }

    protected function tearDown(): void
    {
        unset($_POST['recipeId']);
        parent::tearDown();
    }

    public function testCraftingThePalissadeRecipeThroughTheEngine(): void
    {
        $crafter = $this->createRealPlayer('GmSmith');
        $crafter->getCoords();
        $crafter->get_caracs();
        $xpBefore = (int) $this->link->fetchOne('SELECT xp FROM players WHERE id = ?', [$crafter->id]);

        $bois = $this->itemOrSkip('bois');
        $palissadeItem = $this->itemOrSkip('palissade');
        $bois->add_item($crafter, 10);

        $recipes = (new RecipeService())->getRecipes($crafter, forItemId: (int) $palissadeItem->id);
        if ($recipes === []) {
            $this->markTestSkipped('recette palissade absente.');
        }
        $_POST['recipeId'] = (string) $recipes[0]->getId();

        $results = (new ActionExecutorService($this->actionOrSkip(), $crafter, $crafter))->executeAction();

        $this->assertFalse($results->isBlocked());
        $this->assertTrue($results->isSuccess(), 'with the ingredients, crafting must succeed');

        $fresh = PlayerFactory::legacy($crafter->id);
        $this->assertSame(0, $bois->get_n($fresh), 'the 10 bois are consumed by the recipe');
        $this->assertSame(1, $palissadeItem->get_n($fresh), 'the palissade object is crafted');
        $this->assertSame(
            $xpBefore,
            (int) $this->link->fetchOne('SELECT xp FROM players WHERE id = ?', [$crafter->id]),
            "type 'craft' has no XP rule: crafting grants no XP (parité historique)"
        );
    }

    public function testWithoutIngredientsTheRecipeRefusesAndConsumesNothing(): void
    {
        $crafter = $this->createRealPlayer('GmEmptyHands');
        $crafter->getCoords();
        $crafter->get_caracs();

        $palissadeItem = $this->itemOrSkip('palissade');
        $recipes = (new RecipeService())->getRecipes($crafter, forItemId: (int) $palissadeItem->id);
        if ($recipes === []) {
            $this->markTestSkipped('recette palissade absente.');
        }
        $_POST['recipeId'] = (string) $recipes[0]->getId();

        $bois = $this->itemOrSkip('bois');
        $results = (new ActionExecutorService($this->actionOrSkip(), $crafter, $crafter))->executeAction();

        // Les conditions passent (fabriquer n'en a pas d'autre) : le refus
        // est un ÉCHEC D'OUTCOME (TryCraftRecipe) — l'observable est
        // l'inventaire, rien ne se fabrique et rien ne part en négatif.
        $this->assertSame(0, $palissadeItem->get_n(PlayerFactory::legacy($crafter->id)), 'nothing may be crafted');
        $this->assertSame(0, $bois->get_n(PlayerFactory::legacy($crafter->id)), 'no ingredient may go negative');
    }
}
