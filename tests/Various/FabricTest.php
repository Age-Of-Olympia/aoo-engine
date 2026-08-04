<?php

namespace Tests\Various;

use App\Factory\ActionFactory;
use App\Factory\PlayerFactory;
use App\Service\ActionExecutorService;
use App\Service\BuildingService;
use App\Service\FabricService;
use App\Service\PlacedExemplarService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * The materials rest in the walls: construire puts the recipe's
 * ingredients into the entity's fabric — invisible to every player
 * reader — and the fall spills them with the loot rules, per unit.
 * The admin can slip anything else in; a broken chest keeps no fabric.
 */
#[Group('items-baseline')]
class FabricTest extends LegacyPlayerFixtureTestCase
{
    /** Item whose stats are DB-served (stats_in_db): the lootChance we set
     * is the one chanceFor() reads — a JSON-served item (bois) would win
     * over the column through its data. */
    private const MATERIAL = 'palissade';

    /** false = untouched; otherwise the EXACT original, NULL included. */
    private mixed $materialLootChance = false;

    protected function tearDown(): void
    {
        unset($_POST['itemId']);
        if ($this->materialLootChance !== false) {
            $this->link->executeStatement(
                'UPDATE items SET lootChance = ? WHERE name = ?',
                [$this->materialLootChance, self::MATERIAL]
            );
            $this->materialLootChance = false;
        }
        parent::tearDown();
    }

    private function setMaterialLootChance(int $chance): void
    {
        if ($this->materialLootChance === false) {
            $this->materialLootChance = $this->link->fetchOne(
                'SELECT lootChance FROM items WHERE name = ?', [self::MATERIAL]
            );
        }
        $this->link->executeStatement('UPDATE items SET lootChance = ? WHERE name = ?', [$chance, self::MATERIAL]);
    }

    private function groundMaterial(int $coordsId): int
    {
        return (int) $this->link->fetchOne(
            'SELECT COALESCE(SUM(m.n), 0) FROM map_items m JOIN items i ON i.id = m.item_id
              WHERE m.coords_id = ? AND i.name = ?',
            [$coordsId, self::MATERIAL]
        );
    }

    public function testTheServiceRoundTrips(): void
    {
        $this->requireBuildingsOrSkip();
        $id = $this->placeStructure('palissade', 92, 92);

        $service = new FabricService();
        $service->storeByName($id, ['bois' => 10, 'objet_inconnu_xyz' => 3]);

        $contents = $service->contentsOf($id);
        $this->assertCount(1, $contents, 'an unknown name is skipped, never blocking');
        $this->assertSame('bois', $contents[0]['name']);
        $this->assertSame(10, $contents[0]['n']);

        $service->setUnits($id, $contents[0]['item_id'], 0);
        $this->assertSame([], $service->contentsOf($id), 'zero removes the line');
    }

    public function testConstruireFillsTheWalls(): void
    {
        $this->requireBuildingsOrSkip();
        $action = ActionFactory::getAction('construire');
        if ($action === null) {
            $this->markTestSkipped("actions catalog not seeded (no 'construire' row).");
        }

        $builder = $this->createRealPlayer('GmMacon');
        $this->movePlayerTo($builder->id, 94, 94);
        $builder->getCoords();
        $builder->get_caracs();

        $palissadeItem = $this->itemOrSkip('palissade');
        $palissadeItem->add_item($builder, 1);
        $_POST['itemId'] = (string) $palissadeItem->id;

        $results = (new ActionExecutorService($action, $builder, $builder))->executeAction();
        $this->assertTrue($results->isSuccess());

        $built = (int) $this->link->fetchOne(
            'SELECT b.player_id FROM buildings b JOIN players p ON p.id = b.player_id WHERE p.owner_id = ?',
            [$builder->id]
        );
        $this->assertGreaterThan(0, $built, 'the palissade stands');
        $this->trackEntityId($built);

        $contents = (new FabricService())->contentsOf($built);
        $this->assertSame([['bois', 10]], array_map(
            static fn (array $line): array => [$line['name'], $line['n']],
            $contents
        ), "the recipe's 10 bois rest in the walls");
    }

    public function testTheFallSpillsTheWallsWithTheLootRules(): void
    {
        $this->requireBuildingsOrSkip();
        $id = $this->placeStructure('palissade', 96, 96);
        $coordsId = (int) $this->link->fetchOne('SELECT coords_id FROM players WHERE id = ?', [$id]);

        (new FabricService())->storeByName($id, [self::MATERIAL => 10]);
        $this->setMaterialLootChance(100);

        (new BuildingService())->vanish($id);

        $this->assertSame(10, $this->groundMaterial($coordsId), 'at 100% every unit lands on the cell');
        $this->assertSame(
            0,
            (int) $this->link->fetchOne('SELECT COUNT(*) FROM players_items WHERE player_id = ? AND slot = "fabric"', [$id]),
            'the walls fell: no fabric row survives'
        );

        $this->link->executeStatement('DELETE FROM map_items WHERE coords_id = ?', [$coordsId]);
    }

    public function testABrokenChestKeepsNoFabric(): void
    {
        $this->requireBuildingsOrSkip();
        $owner = $this->createRealPlayer('GmBrisecoffre');
        $exemplarId = $this->installExemplar('coffre_bois', 100, 100, $owner->id);
        $coordsId = (int) $this->link->fetchOne('SELECT coords_id FROM players WHERE id = ?', [$exemplarId]);

        (new FabricService())->storeByName($exemplarId, [self::MATERIAL => 5]);
        $this->setMaterialLootChance(100);

        (new PlacedExemplarService())->destroyToGround($exemplarId);

        $this->assertSame(5, $this->groundMaterial($coordsId), 'the chest spills its walls before it falls');
        $this->assertSame(
            0,
            (int) $this->link->fetchOne('SELECT COUNT(*) FROM players_items WHERE player_id = ? AND slot = "fabric"', [$exemplarId]),
            'broken, it keeps no fabric'
        );

        $this->link->executeStatement('DELETE FROM map_items WHERE coords_id = ?', [$coordsId]);
    }
}
