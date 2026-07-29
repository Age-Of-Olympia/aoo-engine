<?php

namespace Tests\Various;

use App\Service\Map\CompositeSpriteService;
use App\Service\Map\Footprint;
use PHPUnit\Framework\TestCase;

/**
 * The one image a multi-piece object shows in the Tiled palette.
 *
 * The palette used to scan for a whole-object image beside the pieces. Most
 * families ship none, so they reached the palette as loose pieces — an anvil
 * could not be placed as one thing. The image is composed from the pieces
 * the cut-out names when the artist provides none.
 */
class CompositePaletteTest extends TestCase
{
    private string $dir;
    private string $layer = 'test_composites';

    protected function setUp(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD absent.');
        }

        if (empty($_SERVER['DOCUMENT_ROOT'])) {
            $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
        }
        $this->dir = $_SERVER['DOCUMENT_ROOT'] . '/img/' . $this->layer;

        if (!is_dir($this->dir) && !@mkdir($this->dir, 0775, true)) {
            $this->markTestSkipped('img/ non inscriptible.');
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*.png') ?: [] as $file) {
            @unlink($file);
        }

        foreach (glob($this->dir . '/_composed/*.png') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->dir . '/_composed');
        @rmdir($this->dir);
    }

    /** A 50×50 piece, so the composition has something real to stitch. */
    private function piece(string $name): string
    {
        $image = imagecreatetruecolor(50, 50);
        imagesavealpha($image, true);
        imagepng($image, $this->dir . '/' . $name . '.png');
        imagedestroy($image);

        return 'img/' . $this->layer . '/' . $name . '.png';
    }

    public function testAFigureWithoutAnAuthoredImageGetsAComposedOne(): void
    {
        $pieces = [
            0 => $this->piece('gm_pal_tour-00'),
            1 => $this->piece('gm_pal_tour-01'),
        ];

        $sprite = (new CompositeSpriteService())->spriteFor(
            $this->layer,
            'gm_pal_tour',
            Footprint::fromOffsets([0 => [0, 0], 1 => [0, -1]]),
            $pieces
        );

        $this->assertNotNull($sprite);
        $this->assertStringContainsString('_composed', $sprite);

        $size = getimagesize($_SERVER['DOCUMENT_ROOT'] . '/' . $sprite);
        $this->assertSame([50, 100], [$size[0], $size[1]], 'one cell wide, two tall');
    }

    /**
     * A holed figure keeps its box: the empty cell stays empty rather than
     * shrinking the image and shifting every piece.
     */
    public function testAHoledFigureKeepsItsBox(): void
    {
        $pieces = [
            0 => $this->piece('gm_pal_troue-00'),
            3 => $this->piece('gm_pal_troue-03'),
        ];

        $sprite = (new CompositeSpriteService())->spriteFor(
            $this->layer,
            'gm_pal_troue',
            Footprint::boxed(2, 2, [0 => [0, 0], 3 => [1, -1]]),
            $pieces
        );

        $this->assertNotNull($sprite);
        $size = getimagesize($_SERVER['DOCUMENT_ROOT'] . '/' . $sprite);
        $this->assertSame([100, 100], [$size[0], $size[1]]);
    }

    /** The artist's own image wins: it is the object as drawn. */
    public function testAnAuthoredImageIsPreferred(): void
    {
        $family = 'gm_pal_dessine';
        @mkdir($this->dir . '/' . $family, 0775, true);

        $authored = imagecreatetruecolor(100, 50);
        imagepng($authored, $this->dir . '/' . $family . '/' . $family . '.png');
        imagedestroy($authored);

        $sprite = (new CompositeSpriteService())->spriteFor(
            $this->layer,
            $family,
            Footprint::fromOffsets([0 => [0, 0], 1 => [1, 0]]),
            [0 => $this->piece($family . '-00'), 1 => $this->piece($family . '-01')]
        );

        $this->assertSame('img/' . $this->layer . '/' . $family . '/' . $family . '.png', $sprite);

        @unlink($this->dir . '/' . $family . '/' . $family . '.png');
        @rmdir($this->dir . '/' . $family);
    }

    /** Nothing to stitch, nothing claimed. */
    public function testNoPiecesMeansNoSprite(): void
    {
        $this->assertNull((new CompositeSpriteService())->spriteFor(
            $this->layer,
            'gm_pal_vide',
            Footprint::fromOffsets([0 => [0, 0], 1 => [1, 0]]),
            []
        ));
    }
}
