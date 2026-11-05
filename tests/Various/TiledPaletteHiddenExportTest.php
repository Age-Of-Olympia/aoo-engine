<?php

namespace Tests\Various;

use App\Service\TiledMapService;
use App\Service\TilePaletteVisibilityService;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyBootstrapTrait;
use Tests\Support\PlanFixtureTrait;

/**
 * A tile hidden in admin → Tuiles & images leaves the Tiled palette (the
 * catalog) but keeps its image: a pulled plan where it is placed still
 * renders it.
 */
class TiledPaletteHiddenExportTest extends TestCase
{
    use LegacyBootstrapTrait;
    use PlanFixtureTrait;

    private const PLAN = 'plan_test_tiled_hidden';

    private ?string $hiddenName = null;

    protected function setUp(): void
    {
        $this->bootstrapLegacyOrSkip('tile_palette_hidden');
        $this->purgePlan($this->link, self::PLAN);
        // The plan must exist to be exportable
        $this->coordsIdOn(self::PLAN, 0, 0);
    }

    protected function tearDown(): void
    {
        if ($this->hiddenName !== null) {
            (new TilePaletteVisibilityService())->setHidden('tiles', $this->hiddenName, false);
        }
        $this->purgePlan($this->link, self::PLAN);
    }

    public function testAHiddenTileLeavesTheCatalogButKeepsItsImage(): void
    {
        $service = new TiledMapService();
        $palette = new TilePaletteVisibilityService();

        $catalog = $service->exportPlan(self::PLAN, 0)['catalog']['tiles'] ?? [];
        $visible = array_values(array_filter(
            $catalog,
            static fn(string $name): bool => !$palette->isHidden('tiles', $name)
        ));
        if ($visible === []) {
            $this->markTestSkipped('Aucune tuile de sol visible dans img/tiles/.');
        }
        $this->hiddenName = $visible[0];

        $palette->setHidden('tiles', $this->hiddenName, true);
        $export = $service->exportPlan(self::PLAN, 0);

        $this->assertNotContains($this->hiddenName, $export['catalog']['tiles']);
        $this->assertArrayHasKey('tiles/' . $this->hiddenName, $export['images'], 'placed copies still render');
    }
}
