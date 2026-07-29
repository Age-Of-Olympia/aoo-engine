<?php

namespace Tests\Various;

use App\Service\Map\Footprint;
use App\Service\Map\SceneryArtAudit;
use PHPUnit\Framework\TestCase;

/**
 * Does a family's whole-object drawing agree with the pieces on the board?
 *
 * The question stops being academic the day the board draws objects whole:
 * the drawing becomes what a player sees, and what hides them. It has
 * drifted at least once — `triton_statue`'s art is the horizontal mirror of
 * its pieces — and a dimension check calls that conformant, 100×150 for a
 * 2×3 box being a perfect match on paper. Hence pixels.
 */
class SceneryArtAuditTest extends TestCase
{
    private string $dir;
    private string $layer = 'test_art_audit';

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
        foreach (glob($this->dir . '/*/*.png') ?: [] as $file) {
            @unlink($file);
        }

        foreach (glob($this->dir . '/*', GLOB_ONLYDIR) ?: [] as $sub) {
            @rmdir($sub);
        }

        foreach (glob($this->dir . '/*.png') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->dir);
    }

    /** A 50×50 cell, deliberately asymmetric so a mirror cannot pass unseen. */
    private function cell(int $seed): \GdImage
    {
        $image = imagecreatetruecolor(50, 50);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefilledrectangle($image, 0, 0, 49, 49, imagecolorallocatealpha($image, 0, 0, 0, 127));

        /* A bar hugging the left edge: flip it and it lands on the right. */
        imagefilledrectangle($image, 0, 0, 12, 49, imagecolorallocate($image, 20 * $seed + 10, 60, 200));

        return $image;
    }

    /**
     * Writes two pieces side by side and the whole-object image they add up
     * to, optionally mirrored.
     *
     * @return array<int, string> piece index => web path
     */
    private function family(string $name, bool $mirrorArt = false, ?int $artWidth = null): array
    {
        @mkdir($this->dir . '/' . $name, 0775, true);

        $whole = imagecreatetruecolor($artWidth ?? 100, 50);
        imagealphablending($whole, false);
        imagesavealpha($whole, true);
        imagefilledrectangle($whole, 0, 0, imagesx($whole) - 1, 49, imagecolorallocatealpha($whole, 0, 0, 0, 127));

        $pieces = [];

        foreach ([0, 1] as $index) {
            $cell = $this->cell($index);

            imagepng($cell, $this->dir . '/' . $name . '-0' . $index . '.png');
            $pieces[$index] = 'img/' . $this->layer . '/' . $name . '-0' . $index . '.png';

            imagecopy($whole, $cell, $index * 50, 0, 0, 0, 50, 50);
            imagedestroy($cell);
        }

        if ($mirrorArt) {
            imageflip($whole, IMG_FLIP_HORIZONTAL);
        }

        imagepng($whole, $this->dir . '/' . $name . '/' . $name . '.png');
        imagedestroy($whole);

        return $pieces;
    }

    private function twoWide(): Footprint
    {
        return Footprint::fromOffsets([0 => [0, 0], 1 => [1, 0]]);
    }

    public function testArtThatRedrawsItsPiecesPasses(): void
    {
        $pieces = $this->family('gm_audit_fidele');

        $result = (new SceneryArtAudit())->audit($this->layer, 'gm_audit_fidele', $this->twoWide(), $pieces);

        $this->assertSame(SceneryArtAudit::FAITHFUL, $result['verdict']);
        $this->assertSame(0.0, $result['worst']);
    }

    /** The defect that started this: same size, wrong picture. */
    public function testMirroredArtIsCaught(): void
    {
        $pieces = $this->family('gm_audit_miroir', mirrorArt: true);

        $result = (new SceneryArtAudit())->audit($this->layer, 'gm_audit_miroir', $this->twoWide(), $pieces);

        $this->assertSame(SceneryArtAudit::MIRRORED, $result['verdict']);
        $this->assertGreaterThan(0.0, $result['worst'], 'un miroir doit se voir en pixels');
    }

    /** Art wider than the figure would be letterboxed into its slot. */
    public function testArtOfTheWrongSizeIsCaught(): void
    {
        $pieces = $this->family('gm_audit_taille', artWidth: 150);

        $result = (new SceneryArtAudit())->audit($this->layer, 'gm_audit_taille', $this->twoWide(), $pieces);

        $this->assertSame(SceneryArtAudit::WRONG_SIZE, $result['verdict']);
        $this->assertStringContainsString('150×50', $result['detail']);
    }

    /** No drawing is not a fault: the figure composes from its pieces. */
    public function testAFamilyWithoutArtIsNotAFault(): void
    {
        $result = (new SceneryArtAudit())->audit($this->layer, 'gm_audit_absente', $this->twoWide(), []);

        $this->assertSame(SceneryArtAudit::NO_ART, $result['verdict']);
    }

    /**
     * Half the scenery art is palette-indexed, where `imagecolorat` hands
     * back an index rather than a colour. Comparing an index to a colour
     * makes every pixel differ — seven families read as 100 % wrong before
     * this was normalised.
     */
    public function testPaletteArtIsComparedOnColoursNotIndexes(): void
    {
        $name = 'gm_audit_palette';
        $pieces = $this->family($name);

        /* Palette-index the pieces, then rebuild the drawing FROM them, so the
         * two hold the same colours and only the storage differs — which is
         * exactly the real case, and the only way the index bug shows alone
         * rather than mixed with quantisation loss. */
        $whole = imagecreatetruecolor(100, 50);
        imagealphablending($whole, false);
        imagesavealpha($whole, true);

        foreach ($pieces as $index => $path) {
            $file = $_SERVER['DOCUMENT_ROOT'] . '/' . $path;

            $image = imagecreatefrompng($file);
            imagetruecolortopalette($image, false, 255);
            imagepng($image, $file);
            imagedestroy($image);

            $stored = imagecreatefrompng($file);
            $this->assertFalse(imageistruecolor($stored), 'le morceau doit bien être indexé');

            imagecopy($whole, $stored, $index * 50, 0, 0, 0, 50, 50);
            imagedestroy($stored);
        }

        imagepng($whole, $this->dir . '/' . $name . '/' . $name . '.png');
        imagedestroy($whole);

        $result = (new SceneryArtAudit())->audit($this->layer, $name, $this->twoWide(), $pieces);

        $this->assertSame(SceneryArtAudit::FAITHFUL, $result['verdict']);
    }
}
