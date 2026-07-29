<?php

namespace Tests\Various;

use App\Service\Map\Footprint;
use App\Service\TileCatalogService;
use PHPUnit\Framework\TestCase;

/**
 * Sorting the palette: whole objects on one side, pieces of a figure on the
 * other.
 *
 * An animator places a decor in one go, so the palette offers objects. The
 * pieces stay reachable in their own tileset — a truncated instance is
 * repaired piece by piece — but they no longer bury the objects: on the
 * foregrounds folder they outnumbered them fifty to one.
 *
 * The hard case is a lone trailing digit, which marks both a piece
 * (`fondateur00`) and a variant (`arbre1`, `arbre2` — two objects).
 */
class LoosePiecesTest extends TestCase
{
    private string $dir;
    private string $layer = 'test_loose_pieces';

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

        @rmdir($this->dir);
    }

    private function image(string $name): void
    {
        $image = imagecreatetruecolor(50, 50);
        imagepng($image, $this->dir . '/' . $name . '.png');
        imagedestroy($image);
    }

    /**
     * @param string[] $names
     * @param array<string, Footprint> $catalogue
     * @return list<string>
     */
    private function loose(array $names, array $catalogue = []): array
    {
        foreach ($names as $name) {
            $this->image($name);
        }

        return (new TileCatalogService())->loosePieces($this->layer, $catalogue);
    }

    /** A cut-out settles it: those are pieces, however few. */
    public function testAKnownCutOutMakesItsImagesPieces(): void
    {
        $loose = $this->loose(
            ['gm_loose_tour-00', 'gm_loose_tour-01'],
            ['gm_loose_tour' => Footprint::fromOffsets([0 => [0, 0], 1 => [0, -1]])]
        );

        $this->assertSame(['gm_loose_tour-00', 'gm_loose_tour-01'], $loose);
    }

    /** A separator plus a run of siblings: a figure, not a collection. */
    public function testASeparatedRunReadsAsAFigure(): void
    {
        $loose = $this->loose(['gm_loose_mur-00', 'gm_loose_mur-01', 'gm_loose_mur-02']);

        $this->assertCount(3, $loose);
    }

    /**
     * Two siblings and no cut-out: nothing tells a two-piece figure from two
     * variants, so they stay placeable rather than silently vanish.
     */
    public function testTwoSiblingsStayInThePalette(): void
    {
        $this->assertSame([], $this->loose(['gm_loose_arbre1', 'gm_loose_arbre2']));
    }

    /** Zero-padded runs without a separator are figures too (`fondateur00`). */
    public function testABareDigitRunReadsAsAFigure(): void
    {
        $loose = $this->loose(['gm_loose_geant00', 'gm_loose_geant01', 'gm_loose_geant02']);

        $this->assertCount(3, $loose);
    }

    /** An object whose name carries no index is never a piece. */
    public function testAPlainNameIsNeverAPiece(): void
    {
        $this->assertSame([], $this->loose(['gm_loose_tonneau', 'gm_loose_ombre']));
    }

    /** A single-cell type is an object of its own, not a figure to cut. */
    public function testASingleCellCutOutIsNotAFigure(): void
    {
        $loose = $this->loose(
            ['gm_loose_seul1', 'gm_loose_seul2'],
            ['gm_loose_seul' => Footprint::fromOffsets([0 => [0, 0]])]
        );

        $this->assertSame([], $loose);
    }
}
