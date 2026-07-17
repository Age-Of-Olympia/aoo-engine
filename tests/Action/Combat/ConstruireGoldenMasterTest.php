<?php

namespace Tests\Action\Combat;

use App\Action\ActionFactory;
use App\Factory\PlayerFactory;
use App\Service\ActionExecutorService;
use Classes\Item;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * G1 + G2 end to end (docs/design-items-instances.md §4): the
 * 'construire_palissade' action, straight from the catalog, through the
 * untouched executor —
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

        try {
            $this->link->executeQuery('SELECT 1 FROM buildings LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('buildings table unavailable (run migrations): ' . $e->getMessage());
        }
    }

    private function boisOrSkip(): Item
    {
        $item = Item::get_item_by_name('bois');
        if ($item === false || $item === null) {
            $this->markTestSkipped("items catalog not seeded (no 'bois' row).");
        }
        $item->get_data();

        return $item;
    }

    private function actionOrSkip(): \App\Interface\ActionInterface
    {
        $action = ActionFactory::getAction('construire_palissade');
        if ($action === null) {
            $this->markTestSkipped("actions catalog not seeded (no 'construire_palissade' row).");
        }

        return $action;
    }

    public function testWithoutWoodTheActionBlocksAndBuildsNothing(): void
    {
        $builder = $this->createRealPlayer('GmCarpenter');
        $builder->getCoords();
        $builder->get_caracs();
        $maxA = (int) $builder->caracs->a;

        $results = (new ActionExecutorService($this->actionOrSkip(), $builder, $builder))->executeAction();

        $this->assertTrue($results->isBlocked(), 'no wood must block the action');
        $this->assertSame(
            0,
            (int) $this->link->fetchOne('SELECT COUNT(*) FROM buildings b JOIN players p ON p.id = b.player_id WHERE b.owner_id = ?', [$builder->id]),
            'nothing may be built'
        );
        $this->assertSame(
            $maxA,
            PlayerFactory::legacy($builder->id)->getRemaining('a'),
            'a blocked action must not cost the A'
        );
    }

    public function testTenBoisAndOneABuildARealOwnedPalissadeNextToTheBuilder(): void
    {
        $builder = $this->createRealPlayer('GmCarpenter');
        $builder->getCoords();
        $builder->get_caracs();
        $maxA = (int) $builder->caracs->a;

        $bois = $this->boisOrSkip();
        $bois->add_item($builder, 10);

        $results = (new ActionExecutorService($this->actionOrSkip(), $builder, $builder))->executeAction();

        $this->assertFalse($results->isBlocked(), 'with 10 bois the action must pass');
        $this->assertTrue($results->isSuccess(), 'construire has no dice: passing conditions means success');

        $building = $this->link->fetchAssociative(
            "SELECT p.id, p.race, b.faction, c.x, c.y, c.plan
             FROM buildings b
             JOIN players p ON p.id = b.player_id
             JOIN coords c ON c.id = p.coords_id
             WHERE b.owner_id = ?",
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

        $this->assertSame(0, $bois->get_n(PlayerFactory::legacy($builder->id)), 'the 10 bois are consumed');
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
