<?php

namespace Tests\Various;

use App\Entity\Race;
use App\Service\RaceService;
use PHPUnit\Framework\TestCase;

/** Structure types flagged tiled_hidden are reported per family, so each palette skips them. */
class RaceTiledHiddenTest extends TestCase
{
    public function testTheFlagDefaultsToVisible(): void
    {
        $this->assertFalse(Race::ofFamily('structure', Race::NATURE_DECOR)->isTiledHidden());
    }

    public function testTheFlagRoundTrips(): void
    {
        $race = Race::ofFamily('structure', Race::NATURE_DECOR);
        $race->setTiledHidden(true);

        $this->assertTrue($race->isTiledHidden());
        $this->assertSame(Race::FAMILY_SCENERY, $race->familyKey());
    }

    public function testHiddenNamesAreGroupedByFamily(): void
    {
        $decor = Race::ofFamily('structure', Race::NATURE_DECOR);
        $decor->setName('arbre_test');
        $decor->setTiledHidden(true);
        $shown = Race::ofFamily('structure', Race::NATURE_DECOR);
        $shown->setName('rocher_test');
        $service = $this->getMockBuilder(RaceService::class)->onlyMethods(['getRacesByKind'])->getMock();
        $service->method('getRacesByKind')->willReturn([$decor, $shown]);

        $this->assertSame(['arbre_test' => true], $service->tiledHiddenNames(Race::FAMILY_SCENERY));
        $this->assertSame([], $service->tiledHiddenNames(Race::FAMILY_BUILDING));
    }
}
