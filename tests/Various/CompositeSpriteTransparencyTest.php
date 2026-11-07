<?php

namespace Tests\Various;

use App\Service\Map\CompositeSpriteService;
use App\Service\Map\Footprint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A palette image (PNG-8, GIF) with a transparent colour keeps its
 * transparency once cut into pieces (#314): imagecopy skips that colour, so
 * the piece showed its own background instead.
 */
class CompositeSpriteTransparencyTest extends TestCase
{
    private string $root;
    private ?string $documentRoot;

    protected function setUp(): void
    {
        $this->documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
        $this->root = sys_get_temp_dir() . '/aoo-cut-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/img/avatars', 0775, true);
        $_SERVER['DOCUMENT_ROOT'] = $this->root;
    }

    protected function tearDown(): void
    {
        $_SERVER['DOCUMENT_ROOT'] = $this->documentRoot;
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    /** @return array<string, array{0: callable(\GdImage, string): bool, 1: int}> */
    public static function palettes(): array
    {
        return [
            'png-8, one cell' => ['imagepng', 1],
            'png-8, 2×2 figure' => ['imagepng', 2],
            'gif, 2×2 figure' => ['imagegif', 2],
        ];
    }

    /** @param callable(\GdImage, string): bool $write */
    #[DataProvider('palettes')]
    public function testATransparentPaletteImageStaysTransparent(callable $write, int $side): void
    {
        // At the box size, as an uploaded sprite usually is: no resampling on the way
        $image = imagecreate(50 * $side, 50 * $side);
        imagecolortransparent($image, imagecolorallocate($image, 0, 0, 0));
        imagefilledrectangle($image, 10, 10, 30, 30, imagecolorallocate($image, 200, 50, 50));
        $source = $this->root . '/upload';
        $write($image, $source);

        $offsets = [];
        for ($y = 0; $y < $side; $y++) {
            for ($x = 0; $x < $side; $x++) {
                $offsets[] = [$x, $y];
            }
        }
        $written = (new CompositeSpriteService())->cutPieces('avatars', 'gm_cut', Footprint::fromOffsets($offsets), $source);

        $cut = imagecreatefrompng($this->root . '/' . reset($written));
        $this->assertSame(127, (imagecolorat($cut, 1, 1) >> 24) & 0x7F, 'the transparent corner came out opaque');
    }
}
