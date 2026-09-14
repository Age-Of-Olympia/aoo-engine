<?php

namespace Tests\Player;

use Classes\Element;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * A cell holds one element, and only where there is ground.
 *
 * Water and fire on the same cell were possible — the key is (name,
 * coords_id) — and blood landed under a flier crossing the sky. The rule
 * lives in Element::put, which every runtime placement goes through.
 */
#[Group('entities-baseline')]
class OneElementPerCellTest extends LegacyPlayerFixtureTestCase
{
    /** @var list<int> */
    private array $cells = [];

    protected function tearDown(): void
    {
        foreach ($this->cells as $coordsId) {
            $this->link->executeStatement('DELETE FROM map_elements WHERE coords_id = ?', [$coordsId]);
        }
        parent::tearDown();
    }

    private function element(int $coordsId): array
    {
        return $this->link->fetchAllKeyValue(
            'SELECT name, endTime FROM map_elements WHERE coords_id = ?',
            [$coordsId]
        );
    }

    public function testTheFirstElementKeepsTheCell(): void
    {
        [$x, $y] = $this->farTile();
        $coordsId = $this->cells[] = $this->coordsIdOn('gaia', $x, $y);

        $this->assertTrue(Element::put('eau', $coordsId, Element::DURATION_INFINITE));
        $this->assertFalse(Element::put('feu', $coordsId, 2), 'the cell already holds water');
        $this->assertSame(['eau' => 0], $this->element($coordsId));

        $this->assertTrue(Element::put('eau', $coordsId, 3), 'the same element again only refreshes its clock');
        $this->assertGreaterThan(time(), $this->element($coordsId)['eau']);
    }

    public function testNothingIsLaidInTheSky(): void
    {
        [$x, $y] = $this->farTile();
        $sky = $this->cells[] = $this->coordsIdOn('gaia', $x, $y, 1);

        $this->assertFalse(Element::put('sang', $sky), 'above ground level with no tile: no floor');
        $this->assertSame([], $this->element($sky));
    }
}
