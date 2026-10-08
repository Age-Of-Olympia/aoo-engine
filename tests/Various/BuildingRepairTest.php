<?php

namespace Tests\Various;

use App\Factory\ActionFactory;
use App\Factory\PlayerFactory;
use App\Service\Action\ActionTargeting;
use App\Service\ActionExecutorService;
use App\Service\ItemInstanceService;
use App\Service\Map\EntityPlacementService;
use App\Service\TypeRepairService;
use Classes\Item;
use Classes\View;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * One repair gesture is one dose of the type's repair recipe in the
 * button's mode (reparer: materials, reparer_or: gold): every cost paid
 * together, a share of the max PV restored, capped at the damage; a dose
 * the bag cannot pay whole is refused before anything is spent.
 */
#[Group('entities-baseline')]
class BuildingRepairTest extends LegacyPlayerFixtureTestCase
{
    /** @var array{int, int} where the mason stands, the wall one tile south */
    private array $site;

    public function testAMaterialsDoseIsPaidWholeAndRestoresItsShare(): void
    {
        $wallId = $this->damagedWall(damage: 60);
        $mason = $this->mason('MaconMatieres');
        Item::get_item_by_name('pierre_harnais')->add_item($mason, 2);
        $this->itemOrSkip('or')->add_item($mason, 60);

        $this->assertTrue($this->repair('reparer', $mason, $wallId)->isSuccess());

        $this->assertSame(90 + 23, PlayerFactory::legacy($wallId)->getRemaining('pv'), '15 % of 150, rounded up');
        $this->assertSame(1, Item::get_item_by_name('pierre_harnais')->get_n($mason, includeInstances: false), 'one pierre a dose');
        $this->assertSame(60, (int) PlayerFactory::legacy($mason->id)->get_gold(), 'no gold in a materials dose');
    }

    public function testAGoldDoseIsPaidInGoldAlone(): void
    {
        $wallId = $this->damagedWall(damage: 60);
        $mason = $this->mason('MaconOr');
        Item::get_item_by_name('pierre_harnais')->add_item($mason, 2);
        $this->itemOrSkip('or')->add_item($mason, 60);

        $this->assertTrue($this->repair('reparer_or', $mason, $wallId)->isSuccess());

        $this->assertSame(90 + 30, PlayerFactory::legacy($wallId)->getRemaining('pv'), '20 % of 150');
        $this->assertSame(10, (int) PlayerFactory::legacy($mason->id)->get_gold(), '50 gold a dose');
        $this->assertSame(2, Item::get_item_by_name('pierre_harnais')->get_n($mason, includeInstances: false), 'no material taken');
    }

    public function testTheLastDoseStopsAtTheDamage(): void
    {
        $wallId = $this->damagedWall(damage: 10);
        $mason = $this->mason('MaconFin');
        Item::get_item_by_name('pierre_harnais')->add_item($mason, 1);

        $this->repair('reparer', $mason, $wallId);

        $this->assertSame(150, PlayerFactory::legacy($wallId)->getRemaining('pv'), 'capped at the max');
    }

    public function testAShortPurseIsRefusedBeforeAnythingIsSpent(): void
    {
        $wallId = $this->damagedWall(damage: 60);
        $mason = $this->mason('MaconFauche');
        $this->itemOrSkip('or')->add_item($mason, 20);
        $a = PlayerFactory::legacy($mason->id)->getRemaining('a');

        $results = $this->repair('reparer_or', $mason, $wallId);

        $this->assertTrue($results->isBlocked());
        $this->assertStringContainsString('il vous manque 30 × ', $this->refusalOf($results));
        $this->assertSame(90, PlayerFactory::legacy($wallId)->getRemaining('pv'));
        $this->assertSame(20, (int) PlayerFactory::legacy($mason->id)->get_gold(), 'nothing taken');
        $this->assertSame($a, PlayerFactory::legacy($mason->id)->getRemaining('a'), 'no A spent');
    }

    /**
     * Two buttons: each shows only where the type has a recipe in its mode,
     * keyed by players.race, so a placed chest answers through its item's name.
     */
    public function testEachButtonShowsOnlyWithItsRecipe(): void
    {
        $wallId = $this->damagedWall(damage: 60);
        $mason = $this->mason('MaconBoutons');
        $wall = PlayerFactory::legacy($wallId);
        $wall->get_data();
        $targeting = new ActionTargeting();
        $shows = fn (string $action): bool => $targeting->matchesDisplayContext($this->action($action), $mason, $wall);

        $this->assertTrue($shows('reparer'));
        $this->assertTrue($shows('reparer_or'));

        (new TypeRepairService($this->link))->declare('mur_repare_test', TypeRepairService::GOLD, 0, []);

        $this->assertTrue($shows('reparer'));
        $this->assertFalse($shows('reparer_or'), 'no gold recipe, no gold button');

        $wall->data->player_type = ItemInstanceService::ENTITY_TYPE;
        $this->assertTrue($shows('reparer'), 'a placed chest mends through its item name');

        (new TypeRepairService($this->link))->declare('mur_repare_test', TypeRepairService::MATERIALS, 0, []);

        $this->assertFalse($shows('reparer'), 'no recipe, no repair');
    }

    private function repair(string $action, \Classes\Player $mason, int $wallId): \App\Action\ActionResults
    {
        return (new ActionExecutorService($this->action($action), $mason, PlayerFactory::legacy($wallId)))->executeAction();
    }

    private function action(string $name): \App\Entity\Action
    {
        $action = ActionFactory::getAction($name);
        if (!$action instanceof \App\Entity\Action) {
            $this->markTestSkipped("actions catalog not migrated (no '{$name}' row).");
        }

        return $action;
    }

    private function mason(string $name): \Classes\Player
    {
        $mason = $this->createRealPlayer($name);
        $this->movePlayerTo((int) $mason->id, ...$this->site);
        $mason->getCoords();
        $mason->get_caracs();

        return $mason;
    }

    /** A 150 PV wall — materials: 1 pierre for 15 %, gold: 50 for 20 % — with $damage PV missing. */
    private function damagedWall(int $damage): int
    {
        $this->sowStructureType('mur_repare_test');
        $this->sowCatalogItem('pierre_harnais', ['type' => 'matiere', 'price' => 10]);
        $this->itemOrSkip('or');
        $this->sowRepairRecipe('mur_repare_test', TypeRepairService::MATERIALS, 15, ['pierre_harnais' => 1]);
        $this->sowRepairRecipe('mur_repare_test', TypeRepairService::GOLD, 20, ['or' => 50]);

        $this->site = $this->farTile();
        $coordsId = (int) View::get_coords_id($this->tile($this->site[0], $this->site[1] + 1));
        $wallId = (new EntityPlacementService($this->link))->create('building', 'mur_repare_test', $coordsId, 'Mur', '');
        $this->trackEntityId($wallId);

        $wall = PlayerFactory::legacy($wallId);
        $wall->get_caracs();
        $wall->putBonus(['pv' => -$damage]);

        return $wallId;
    }
}
