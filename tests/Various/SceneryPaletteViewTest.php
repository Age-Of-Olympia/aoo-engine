<?php

namespace Tests\Various;

use App\Service\Map\Footprint;
use App\Service\Map\SceneryFootprintDeriver;
use App\View\Tiled\SceneryPaletteView;
use PHPUnit\Framework\TestCase;

/**
 * The scenery palette, and the two regressions it already went through:
 * grouping read from the MAP rather than the files, so nothing grouped on a
 * sparse database; and a tooltip announcing a shape the placement did not
 * produce, because the two read different sources.
 */
class SceneryPaletteViewTest extends TestCase
{
    /** @return list<array{name: string, url: string}> */
    private function pieces(string ...$names): array
    {
        return array_map(
            static fn (string $name): array => ['name' => $name, 'url' => 'img/foregrounds/' . $name . '.png'],
            $names
        );
    }

    /** Pieces are laid in a row rather than guessed into a shape. */
    public function testPiecesAreGroupedEvenWithoutAKnownFootprint(): void
    {
        $html = SceneryPaletteView::render(
            $this->pieces('gm_tour-00', 'gm_tour-01', 'gm_tour-02', 'gm_tour-03'),
            []
        );

        $this->assertSame(
            1,
            substr_count($html, 'data-type="foregrounds"'),
            'une seule vignette pour la famille'
        );
        $this->assertStringContainsString('scenery-object--unknown', $html, 'et elle annonce la découpe inconnue');
        $this->assertStringContainsString('découpe inconnue', $html);
        $this->assertSame(4, substr_count($html, '<img src='), 'les quatre morceaux y figurent');
    }

    /** A single-cell decor is not an object: it stays a plain thumbnail. */
    public function testASingleTileDecorStaysOnItsOwn(): void
    {
        $html = SceneryPaletteView::render($this->pieces('gm_rocher'), []);

        $this->assertStringNotContainsString('scenery-object', $html);
        $this->assertStringContainsString('data-name="gm_rocher"', $html);
    }

    /** The tooltip and the placement must read the same source. */
    public function testAKnownFootprintIsAnnouncedAndDescribed(): void
    {
        $html = SceneryPaletteView::render(
            $this->pieces('gm_arche-00', 'gm_arche-01'),
            ['gm_arche' => Footprint::boxed(2, 1, [0 => [0, 0], 1 => [1, 0]])]
        );

        $this->assertStringContainsString('gm_arche — 2×1, 2 cases', $html);
        $this->assertStringNotContainsString('scenery-object--unknown', $html);
        $this->assertStringContainsString('data-figure=', $html);
        $this->assertStringContainsString('&quot;w&quot;:2', $html, 'la figure voyage en données');
    }

    /** A holed figure is reported: it is a shape, not a defect. */
    public function testAHoledFigureSaysSo(): void
    {
        $html = SceneryPaletteView::render(
            $this->pieces('gm_geant-00', 'gm_geant-01'),
            ['gm_geant' => Footprint::boxed(3, 3, [0 => [0, 0], 1 => [2, 2]])]
        );

        $this->assertStringContainsString('figure trouée', $html);
    }

    /** `geant_petrifie`'s whole image shows only the body: four pieces, a 1×2 box. */
    public function testAnImageTooSmallForItsPiecesIsRejected(): void
    {
        $root = dirname(__DIR__, 2) . '/img/foregrounds/';

        if (!is_file($root . 'geant_petrifie/geant_petrifie.png')
            || !is_file($root . 'arbre_sacre/arbre_sacre.png')
        ) {
            $this->markTestSkipped('images de décor absentes de ce déploiement');
        }

        $footprints = (new SceneryFootprintDeriver())->imageFootprints();

        $this->assertArrayNotHasKey(
            'geant_petrifie',
            $footprints,
            'son image d\'ensemble ne montre que le corps : elle est écartée'
        );

        $this->assertArrayHasKey('arbre_sacre', $footprints, 'celle-ci est cohérente');
        $this->assertSame(2, $footprints['arbre_sacre']->width());
        $this->assertSame(2, $footprints['arbre_sacre']->height());
    }
}
