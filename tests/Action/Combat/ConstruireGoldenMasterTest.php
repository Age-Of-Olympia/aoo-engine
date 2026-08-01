<?php

namespace Tests\Action\Combat;

use App\Factory\ActionFactory;
use App\Factory\PlayerFactory;
use App\Service\ActionExecutorService;
use Classes\Item;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * G1 + G2 end to end (docs/design-items-instances.md §4, généralisé par
 * docs/design-generic-item-actions.md) : l'action GÉNÉRIQUE 'construire'
 * du catalogue, l'objet fourni à l'exécution (POST itemId → ItemPick),
 * through the untouched executor —
 *
 *   - without wood: blocked by RequiresItem, nothing built, nothing
 *     spent;
 *   - with 10 bois: succeeds deterministically (no dice), consumes the
 *     10 bois and 1 A, and a REAL palissade Building appears on a free
 *     tile adjacent to the builder — owner = builder, faction reprise,
 *     100 PV via the pseudo-race, attackable like any structure.
 *
 * This is the data-driven replacement of build.php's dumb walls.
 */
#[Group('entities-golden-master')]
#[Group('items-golden-master')]
class ConstruireGoldenMasterTest extends LegacyPlayerFixtureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBuildingsOrSkip();
    }

    private function actionOrSkip(): \App\Interface\ActionInterface
    {
        $action = ActionFactory::getAction('construire');
        if ($action === null) {
            $this->markTestSkipped("actions catalog not seeded (no generic 'construire' row — run migrations).");
        }

        return $action;
    }

    protected function tearDown(): void
    {
        unset($_POST['itemId']);
        parent::tearDown();
    }

    public function testWithoutThePalissadeObjectTheActionBlocksAndBuildsNothing(): void
    {
        $builder = $this->createRealPlayer('GmCarpenter');
        $builder->getCoords();
        $builder->get_caracs();
        $maxA = (int) $builder->caracs->a;
        $palissadeItem = $this->itemOrSkip('palissade');
        $_POST['itemId'] = (string) $palissadeItem->id;

        $results = (new ActionExecutorService($this->actionOrSkip(), $builder, $builder))->executeAction();

        $this->assertTrue($results->isBlocked(), 'no palissade object must block the action (ItemPick possession)');
        $this->assertSame(
            0,
            (int) $this->link->fetchOne('SELECT COUNT(*) FROM buildings b JOIN players p ON p.id = b.player_id WHERE p.owner_id = ?', [$builder->id]),
            'nothing may be built'
        );
        $this->assertSame(
            $maxA,
            PlayerFactory::legacy($builder->id)->getRemaining('a'),
            'a blocked action must not cost the A'
        );
    }

    /**
     * Bâtir un CONTENEUR pose l'objet lui-même, pas un bâtiment homonyme.
     *
     * La même boucle que la palissade, mais le coffre a quitté `races` : plus
     * aucune race ne le décrit, et c'est ce qui fait basculer la pose. Ce qui
     * se dresse est un exemplaire — il a une instance, donc une identité qui
     * survivra à l'usure, au nom propre et, demain, à un contenu.
     */
    public function testBuildingAContainerPlacesTheObjectItself(): void
    {
        $builder = $this->createRealPlayer('GmCoffrier');
        $builder->getCoords();
        $builder->get_caracs();

        $bois = $this->itemOrSkip('bois');
        $chestItem = $this->itemOrSkip('coffre_bois');
        $bois->add_item($builder, 20);

        $recipes = (new \App\Service\RecipeService())->getRecipes($builder, forItemId: (int) $chestItem->id);
        if ($recipes === []) {
            $this->markTestSkipped('recette coffre_bois absente.');
        }
        $message = '';
        $this->assertTrue(
            (new \App\Service\RecipeService())->TryCraftRecipe($recipes[0], $builder, $message),
            'fabriquer le coffre avec 20 bois doit réussir : ' . $message
        );
        $this->assertSame(1, $chestItem->get_n(PlayerFactory::legacy($builder->id)), 'le coffre est au sac');

        $_POST['itemId'] = (string) $chestItem->id;
        $results = (new ActionExecutorService($this->actionOrSkip(), $builder, $builder))->executeAction();

        $this->assertFalse($results->isBlocked(), 'avec le coffre en main, l\'action passe');
        $this->assertTrue($results->isSuccess());

        $placed = $this->link->fetchAssociative(
            "SELECT p.id, p.player_type, p.race, p.slot, ii.id AS instance, c.x, c.y, c.plan
               FROM players p
               JOIN coords c ON c.id = p.coords_id
               LEFT JOIN item_instances ii ON ii.entity_id = p.id
              WHERE p.owner_id = ? AND p.race = 'coffre_bois'",
            [$builder->id]
        );
        $this->assertNotFalse($placed, 'un coffre posé par le bâtisseur doit exister');
        $this->trackEntityId((int) $placed['id']);

        $this->assertSame('item', $placed['player_type'], 'ce qui se dresse est un OBJET');
        $this->assertSame('installed', $placed['slot'], 'posé, donc il tient sa case');
        $this->assertNotNull($placed['instance'], 'et il a une instance : une identité, pas un décor');

        $this->assertFalse(
            (bool) $this->link->fetchOne('SELECT 1 FROM buildings WHERE player_id = ?', [$placed['id']]),
            'aucun satellite de bâtiment : ce n\'est plus un bâtiment'
        );

        $distance = abs((int) $placed['x'] - (int) $builder->coords->x)
            + abs((int) $placed['y'] - (int) $builder->coords->y);
        $this->assertSame($builder->coords->plan, $placed['plan']);
        $this->assertGreaterThan(0, $distance, 'pas sur la case du bâtisseur');
        $this->assertLessThanOrEqual(2, $distance, 'sur une case libre adjacente');

        $this->assertSame(
            0,
            $chestItem->get_n(PlayerFactory::legacy($builder->id)),
            'le coffre a quitté le sac : ce qui est posé est ce qu\'on a dépensé'
        );
    }

    public function testCraftThenBuildTheFullBuildableObjectLoop(): void
    {
        // Décision de revue : la palissade est un OBJET CONSTRUCTIBLE —
        // la boucle complète : 10 bois → craft → objet palissade (portable,
        // empilable) → construire la CONSOMME → l'entité bâtie apparaît.
        $builder = $this->createRealPlayer('GmCarpenter');
        $builder->getCoords();
        $builder->get_caracs();
        $maxA = (int) $builder->caracs->a;

        $bois = $this->itemOrSkip('bois');
        $palissadeItem = $this->itemOrSkip('palissade');
        $bois->add_item($builder, 10);

        // Craft par le vrai service de recettes.
        $recipes = (new \App\Service\RecipeService())->getRecipes($builder, forItemId: (int) $palissadeItem->id);
        if ($recipes === []) {
            $this->markTestSkipped('recette palissade absente.');
        }
        $message = '';
        $this->assertTrue(
            (new \App\Service\RecipeService())->TryCraftRecipe($recipes[0], $builder, $message),
            'crafting the palissade from 10 bois must succeed: ' . $message
        );
        $this->assertSame(0, $bois->get_n(PlayerFactory::legacy($builder->id)), 'the 10 bois are consumed by the craft');
        $this->assertSame(1, $palissadeItem->get_n(PlayerFactory::legacy($builder->id)), 'the palissade object is in the inventory');

        $_POST['itemId'] = (string) $palissadeItem->id;
        $results = (new ActionExecutorService($this->actionOrSkip(), $builder, $builder))->executeAction();

        $this->assertFalse($results->isBlocked(), 'with the palissade object the action must pass');
        $this->assertTrue($results->isSuccess(), 'construire has no dice: passing conditions means success');

        $building = $this->link->fetchAssociative(
            "SELECT p.id, p.race, p.faction, c.x, c.y, c.plan
             FROM buildings b
             JOIN players p ON p.id = b.player_id
             JOIN coords c ON c.id = p.coords_id
             WHERE p.owner_id = ?",
            [$builder->id]
        );
        $this->assertNotFalse($building, 'a building owned by the builder must exist');
        $this->trackEntityId((int) $building['id']);

        $this->assertSame('palissade', $building['race']);
        $this->assertSame((string) $builder->data->faction, $building['faction'], 'the builder faction is reprise');

        $distance = abs((int) $building['x'] - (int) $builder->coords->x)
            + abs((int) $building['y'] - (int) $builder->coords->y);
        $this->assertSame($builder->coords->plan, $building['plan']);
        $this->assertGreaterThan(0, $distance, 'not on the builder tile');
        $this->assertLessThanOrEqual(2, $distance, 'on a free tile adjacent to the builder (diagonal = manhattan 2)');

        $this->assertSame(0, $palissadeItem->get_n(PlayerFactory::legacy($builder->id)), 'the palissade object is consumed by construire');
        $this->assertSame(
            $maxA - 1,
            PlayerFactory::legacy($builder->id)->getRemaining('a'),
            'the action costs exactly 1 A'
        );

        $this->assertSame(
            100,
            PlayerFactory::legacy((int) $building['id'])->getRemaining('pv'),
            'the built palissade carries its pseudo-race PV — attackable like any structure'
        );
    }
}
