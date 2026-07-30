<?php

namespace Tests\Various;

use App\Service\Map\EntityPlacementService;
use Classes\View;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * The pose shared by every placed object: one row, one id, its cells.
 *
 * These cases pin what the three hand-written INSERTs left to their caller —
 * the id a whole batch hands out, and the cells that have to follow the row
 * without a second call. A batch that reads its ids from another connection
 * gives them all the same one, and nothing complains until the second entity
 * overwrites the first.
 */
#[Group('items-golden-master')]
class EntityPlacementServiceTest extends LegacyPlayerFixtureTestCase
{
    private const PLAN = 'plan_test_pose';

    protected function tearDown(): void
    {
        $link = $this->link;

        /* Cells cascade with their entities, but the coords cleanup below is
         * RESTRICT and runs after them. */
        $link->executeStatement(
            'DELETE ec FROM entity_cells ec JOIN coords c ON c.id = ec.coords_id WHERE c.plan = ?',
            [self::PLAN]
        );

        parent::tearDown();

        $link->executeStatement('DELETE FROM coords WHERE plan = ?', [self::PLAN]);
    }

    private function coordsId(int $x, int $y): int
    {
        return (int) View::get_coords_id(
            (object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => self::PLAN]
        );
    }

    /** A race name the catalog really carries, since `players.race` points at it. */
    private function someResourceType(): string
    {
        $name = $this->link->fetchOne(
            "SELECT name FROM races WHERE structure_nature = 'ressource' ORDER BY name LIMIT 1"
        );

        if ($name === false || $name === null) {
            $this->markTestSkipped('Aucun type de ressource au catalogue.');
        }

        return (string) $name;
    }

    /**
     * @param list<int> $ids
     */
    private function track(array $ids): void
    {
        foreach ($ids as $id) {
            $this->trackEntityId($id);
        }
    }

    /**
     * @return list<array{race: string, coordsId: int, name: string, avatar: string}>
     */
    private function threeObjects(string $type): array
    {
        $objects = [];

        foreach ([[1, 1], [2, 1], [3, 1]] as $index => [$x, $y]) {
            $objects[] = [
                'race'     => $type,
                'coordsId' => $this->coordsId($x, $y),
                'name'     => 'Pose ' . $index,
                'avatar'   => 'img/walls/' . $type . '.png',
            ];
        }

        return $objects;
    }

    /** A batch hands out one id per object, never the same one twice. */
    public function testABatchNeverHandsOutTheSameIdTwice(): void
    {
        $type = $this->someResourceType();

        $ids = (new EntityPlacementService($this->link))
            ->createMany('resource', $this->threeObjects($type));
        $this->track($ids);

        $this->assertCount(3, $ids);
        $this->assertSame($ids, array_values(array_unique($ids)), 'un id est servi deux fois');
        $this->assertSame([$ids[0], $ids[0] + 1, $ids[0] + 2], $ids, 'les ids d\'un lot se suivent');
    }

    /** Ids stay inside the range their type owns. */
    public function testIdsStayInTheTypeRange(): void
    {
        $type = $this->someResourceType();

        $ids = (new EntityPlacementService($this->link))
            ->createMany('resource', $this->threeObjects($type));
        $this->track($ids);

        foreach ($ids as $id) {
            $this->assertGreaterThanOrEqual(50000000, $id);
            $this->assertLessThanOrEqual(59999999, $id);
        }
    }

    /** Each posed entity holds the cell it stands on, blocking as a resource. */
    public function testEveryPosedEntityHoldsItsCell(): void
    {
        $type = $this->someResourceType();

        $ids = (new EntityPlacementService($this->link))
            ->createMany('resource', $this->threeObjects($type));
        $this->track($ids);

        foreach ($ids as $id) {
            $cells = $this->link->fetchAllAssociative(
                'SELECT role FROM entity_cells WHERE player_id = ?',
                [$id]
            );

            $this->assertCount(1, $cells, "l'entité #{$id} ne tient pas exactement une case");
            $this->assertSame('block', $cells[0]['role']);
        }
    }

    /** Display ids follow the type, and stay sequential across a batch. */
    public function testDisplayIdsStaySequentialWithinTheType(): void
    {
        $type = $this->someResourceType();

        $ids = (new EntityPlacementService($this->link))
            ->createMany('resource', $this->threeObjects($type));
        $this->track($ids);

        $displayIds = $this->link->fetchFirstColumn(
            'SELECT display_id FROM players WHERE id IN (' . implode(',', $ids) . ') ORDER BY id'
        );

        $displayIds = array_map('intval', $displayIds);
        $this->assertSame(
            [$displayIds[0], $displayIds[0] + 1, $displayIds[0] + 2],
            $displayIds
        );
    }

    /** An empty batch writes nothing and asks the base for no id. */
    public function testAnEmptyBatchPosesNothing(): void
    {
        $this->assertSame([], (new EntityPlacementService($this->link))->createMany('resource', []));
    }

    /** A type without a range is refused, rather than posed out of bounds. */
    public function testATypeWithoutARangeIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new EntityPlacementService($this->link))->createMany(
            'chimere',
            [['race' => 'arbre1', 'coordsId' => $this->coordsId(9, 9), 'name' => 'X', 'avatar' => '']]
        );
    }
}
