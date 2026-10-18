<?php

namespace Tests\Various;

use App\Service\Map\EntityPlacementService;
use App\Service\Map\ResourceObjectService;
use App\Service\PlantsService;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * A seed grows what its type is; a tree filed as a building is re-placed as a resource.
 */
class SeedSproutTest extends LegacyPlayerFixtureTestCase
{
    private const PLAN = 'plan_test_sprout';

    protected function tearDown(): void
    {
        $link = $this->link;

        $link->executeStatement(
            'DELETE p FROM players p JOIN coords c ON c.id = p.coords_id WHERE c.plan = ?',
            [self::PLAN]
        );

        parent::tearDown();

        $link->executeStatement('DELETE FROM coords WHERE plan = ?', [self::PLAN]);
    }

    private function resourceType(): string
    {
        $type = $this->link->fetchOne("SELECT name FROM races WHERE type_kind = 'resource' ORDER BY name LIMIT 1");
        if (!$type) {
            $this->markTestSkipped('Aucun type de ressource au catalogue.');
        }

        return (string) $type;
    }

    private function kindOn(int $coordsId): string|false
    {
        return $this->link->fetchOne('SELECT player_type FROM players WHERE coords_id = ?', [$coordsId]);
    }

    public function testTheTypeDecidesWhatASeedGrows(): void
    {
        $type = $this->resourceType();
        $coordsId = $this->coordsIdOn(self::PLAN, 1, 1);

        PlantsService::sprout($type, 'walls', $coordsId);

        $this->assertSame('resource', $this->kindOn($coordsId));
    }

    public function testAnUnknownTypeGrowsNothing(): void
    {
        $coordsId = $this->coordsIdOn(self::PLAN, 3, 1);

        PlantsService::sprout('zz_type_inconnu', 'walls', $coordsId);

        $this->assertFalse($this->kindOn($coordsId));
    }

    public function testATreeFiledAsABuildingBecomesAResource(): void
    {
        $type = $this->resourceType();
        $coordsId = $this->coordsIdOn(self::PLAN, 2, 1);
        (new EntityPlacementService($this->link))->create('building', $type, $coordsId, $type, 'img/walls/' . $type . '.png');

        (new ResourceObjectService($this->link))->refileBuildings();

        $this->assertSame('resource', $this->kindOn($coordsId));
        $this->assertSame(
            'block',
            $this->link->fetchOne('SELECT role FROM entity_cells WHERE coords_id = ?', [$coordsId])
        );
    }
}
