<?php

namespace Tests\Various;

use App\Service\Map\ResourceObjectService;
use App\Service\Map\ResourceStateService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * The editor's gestures on a resource: pose it, cycle it, erase it.
 *
 * Each of these went through `map_resources` until the conversion emptied it,
 * and each then did nothing at all without saying so — the brush answered, the
 * eraser passed, and the map never changed. These cases pin that the gesture
 * now reaches the entity.
 */
#[Group('items-baseline')]
class ResourceObjectServiceTest extends LegacyPlayerFixtureTestCase
{
    private const PLAN = 'plan_test_pose_tiled';

    protected function tearDown(): void
    {
        $link = $this->link;

        $link->executeStatement(
            'DELETE r FROM resources r
               JOIN players p ON p.id = r.player_id
               JOIN coords c ON c.id = p.coords_id
              WHERE c.plan = ?',
            [self::PLAN]
        );
        $link->executeStatement(
            'DELETE p FROM players p JOIN coords c ON c.id = p.coords_id
              WHERE p.player_type = "resource" AND c.plan = ?',
            [self::PLAN]
        );
        $link->executeStatement(
            'DELETE ec FROM entity_cells ec JOIN coords c ON c.id = ec.coords_id WHERE c.plan = ?',
            [self::PLAN]
        );

        parent::tearDown();

        $link->executeStatement('DELETE FROM coords WHERE plan = ?', [self::PLAN]);
    }

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

    /** A posed resource is an entity holding its cell, standing. */
    public function testPosingPutsAnEntityOnTheCell(): void
    {
        $coordsId = $this->coordsIdOn(self::PLAN, 4, 4);

        $id = (new ResourceObjectService($this->link))->placeAt($this->someResourceType(), $coordsId);
        $this->trackEntityId($id);

        $row = $this->link->fetchAssociative(
            'SELECT player_type, coords_id FROM players WHERE id = ?',
            [$id]
        );
        $this->assertSame('resource', $row['player_type']);
        $this->assertSame($coordsId, (int) $row['coords_id']);

        $this->assertSame(
            'block',
            $this->link->fetchOne('SELECT role FROM entity_cells WHERE player_id = ?', [$id])
        );
        $this->assertFalse((new ResourceStateService($this->link))->isExhausted($id), 'une pose neuve est debout');
    }

    /** The palette's `damages = -2` poses a resource already dry. */
    public function testPosingExhaustedIsHonoured(): void
    {
        $id = (new ResourceObjectService($this->link))->placeAt($this->someResourceType(), $this->coordsIdOn(self::PLAN, 5, 4), true);
        $this->trackEntityId($id);

        $this->assertTrue((new ResourceStateService($this->link))->isExhausted($id));
    }

    /** The harvest button walks standing → exhausted → standing. */
    public function testCyclingTogglesTheStateBothWays(): void
    {
        $coordsId = $this->coordsIdOn(self::PLAN, 6, 4);
        $id = (new ResourceObjectService($this->link))->placeAt($this->someResourceType(), $coordsId);
        $this->trackEntityId($id);

        $this->assertTrue((new ResourceObjectService($this->link))->cycleState($coordsId), 'premier clic : épuisée');
        $this->assertTrue((new ResourceStateService($this->link))->isExhausted($id));

        $this->assertFalse((new ResourceObjectService($this->link))->cycleState($coordsId), 'second clic : debout');
        $this->assertFalse((new ResourceStateService($this->link))->isExhausted($id));
    }

    /** Cycling an empty cell says so instead of pretending. */
    public function testCyclingAnEmptyCellReportsNothingThere(): void
    {
        $this->assertNull((new ResourceObjectService($this->link))->cycleState($this->coordsIdOn(self::PLAN, 7, 4)));
    }

    /** Erasing takes the entity, its cells and its state away together. */
    public function testErasingTakesTheEntityAndItsState(): void
    {
        $coordsId = $this->coordsIdOn(self::PLAN, 8, 4);
        $service = new ResourceObjectService($this->link);

        $id = $service->placeAt($this->someResourceType(), $coordsId, true);
        $this->trackEntityId($id);

        $this->assertSame([$id], $service->idsOn($coordsId));

        $service->removeEntities($service->idsOn($coordsId));

        $this->assertSame([], $service->idsOn($coordsId));
        $this->assertFalse(
            $this->link->fetchOne('SELECT 1 FROM entity_cells WHERE player_id = ?', [$id]),
            'les cases partent avec l\'entité'
        );
        $this->assertFalse(
            $this->link->fetchOne('SELECT 1 FROM resources WHERE player_id = ?', [$id]),
            'le satellite d\'état n\'a pas de FK : il doit être retiré explicitement'
        );
    }
}
