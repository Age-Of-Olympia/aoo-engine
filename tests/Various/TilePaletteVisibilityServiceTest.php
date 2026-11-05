<?php

namespace Tests\Various;

use App\Service\TilePaletteVisibilityService;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyBootstrapTrait;

/**
 * Images hidden from the editor palettes (tiled and Tiled): the mark is set
 * and cleared from admin → Tuiles & images, and follows the image when it is
 * renamed, moved or deleted.
 */
class TilePaletteVisibilityServiceTest extends TestCase
{
    use LegacyBootstrapTrait;

    private TilePaletteVisibilityService $service;

    protected function setUp(): void
    {
        $this->bootstrapLegacyOrSkip('tile_palette_hidden');
        $this->cleanUp();
        $this->service = new TilePaletteVisibilityService();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
    }

    private function cleanUp(): void
    {
        $this->link->executeStatement("DELETE FROM tile_palette_hidden WHERE name LIKE 'palette_test_%'");
    }

    public function testHideThenShowAgain(): void
    {
        $this->service->setHidden('tiles', 'palette_test_ciel', true);
        $this->service->setHidden('tiles', 'palette_test_ciel', true); // idempotent

        $this->assertTrue($this->service->isHidden('tiles', 'palette_test_ciel'));
        $this->assertFalse($this->service->isHidden('foregrounds', 'palette_test_ciel'), 'scoped by layer');
        $this->assertArrayHasKey('palette_test_ciel', $this->service->hiddenByLayer()['tiles']);

        $this->service->setHidden('tiles', 'palette_test_ciel', false);

        $this->assertFalse($this->service->isHidden('tiles', 'palette_test_ciel'));
    }

    public function testFilterKeepsOrderAndDropsHiddenNames(): void
    {
        $this->service->setHidden('tiles', 'palette_test_b', true);

        $this->assertSame(
            ['palette_test_a', 'palette_test_c'],
            $this->service->filter('tiles', ['palette_test_a', 'palette_test_b', 'palette_test_c'])
        );
        $this->assertSame(['palette_test_b'], $this->service->filter('marks', ['palette_test_b']));
    }

    public function testTheMarkFollowsRenameAndMoveAndIsDroppedOnDelete(): void
    {
        $this->service->setHidden('tiles', 'palette_test_avant', true);

        $this->service->rename('tiles', 'palette_test_avant', 'palette_test_apres');
        $this->assertFalse($this->service->isHidden('tiles', 'palette_test_avant'));
        $this->assertTrue($this->service->isHidden('tiles', 'palette_test_apres'));

        $this->service->move('tiles', 'palette_test_apres', 'foregrounds');
        $this->assertFalse($this->service->isHidden('tiles', 'palette_test_apres'));
        $this->assertTrue($this->service->isHidden('foregrounds', 'palette_test_apres'));

        $this->service->forget('foregrounds', 'palette_test_apres');
        $this->assertFalse($this->service->isHidden('foregrounds', 'palette_test_apres'));
    }
}
