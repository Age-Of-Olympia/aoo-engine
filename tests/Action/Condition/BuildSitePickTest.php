<?php

namespace Tests\Action\Condition;

use App\Action\Condition\BuildSiteCondition;
use App\Action\Condition\BuildSitePick;
use App\Action\Condition\ConditionObject;
use App\Entity\ActionCondition;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * The picked ORIGIN follows the emprise: some cell of the built form
 * must touch the builder. A 2×2 édifice may thus put its origin two
 * cells away — but only on the sides its body extends toward. Freeness
 * of every cell is checked by the blocking condition, before payment.
 */
#[Group('action-condition')]
class BuildSitePickTest extends LegacyPlayerFixtureTestCase
{
    protected function tearDown(): void
    {
        unset($_POST['buildX'], $_POST['buildY']);
        parent::tearDown();
    }

    private function atelierFootprintOrSkip(): void
    {
        $row = $this->link->fetchOne("SELECT w FROM entity_type_footprints WHERE type_name = 'atelier'");
        if ((int) $row !== 2) {
            $this->markTestSkipped("atelier 2×2 footprint not seeded (run migrations).");
        }
    }

    private function resolveAt(int $x, int $y, ?string $type): ?object
    {
        $builder = $this->createRealPlayer('GmGeometre');
        $this->movePlayerTo($builder->id, 90, 90);
        $builder->getCoords();

        $_POST['buildX'] = (string) $x;
        $_POST['buildY'] = (string) $y;

        return BuildSitePick::resolve($builder->coords, $type);
    }

    public function testASingleCellFormKeepsTheAdjacentRule(): void
    {
        $this->assertNotNull($this->resolveAt(91, 90, 'palissade'));
        $this->assertNull($this->resolveAt(92, 90, 'palissade'), 'two cells away, a 1×1 touches nothing');
    }

    public function testATwoByTwoOriginReachesTwoCellsOnItsBodySides(): void
    {
        $this->atelierFootprintOrSkip();

        // Offsets [[0,0],[1,0],[0,-1],[1,-1]]: the body extends toward +x
        // and -y, so the origin reaches two cells on those sides…
        $this->assertNotNull($this->resolveAt(90, 92, 'atelier'), 'origin two north: its y-1 cell touches');
        $this->assertNotNull($this->resolveAt(88, 90, 'atelier'), 'origin two west: its x+1 cell touches');

        // …and nowhere else: on the other sides the body walks away.
        $this->assertNull($this->resolveAt(90, 88, 'atelier'));
        $this->assertNull($this->resolveAt(92, 90, 'atelier'));
    }

    /** A refusal found by place() came after the costs: materials, PA and XP were spent. */
    public function testABlockedFootprintCellRefusesBeforePayment(): void
    {
        $this->atelierFootprintOrSkip();

        [$x, $y] = $this->farTile();
        $builder = $this->createRealPlayer('GmMaconne');
        $this->movePlayerTo($builder->id, $x, $y);
        $builder->getCoords();

        // The origin, east of the builder, is free; its body cell north-east of it is not.
        $this->placeStructure('mur_pierre', $x + 2, $y - 1);
        $_POST['buildX'] = (string) ($x + 1);
        $_POST['buildY'] = (string) $y;

        $item = $this->createMock(\Classes\Item::class);
        $item->row = (object) ['name' => 'atelier'];

        $result = (new BuildSiteCondition())->check(
            $builder,
            null,
            new ActionCondition(),
            (new ConditionObject())->setPickedItem($item)
        );

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isBlocking(), 'blocking: the executor pays nothing');
        $this->assertStringStartsWith('Case (' . ($x + 2) . ', ' . ($y - 1) . ', gaia)', (string) ($result->getConditionFailureMessages()[0] ?? ''));
    }
}
