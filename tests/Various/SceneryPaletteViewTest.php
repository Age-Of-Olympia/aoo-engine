<?php

namespace Tests\Various;

use App\Service\Map\SceneryFootprintDeriver;
use App\View\Tiled\SceneryPaletteView;
use PHPUnit\Framework\TestCase;

/**
 * La palette de décor — les deux régressions qu'elle a déjà connues.
 *
 * Toutes deux ont été trouvées par un testeur devant son écran, pas par la
 * suite ; ces cas existent pour que cela n'arrive pas une troisième fois.
 *
 * La PREMIÈRE : la palette groupait d'après les découpes dérivées de la
 * CARTE. Sur une base qui ne porte presque pas de décor, presque rien ne se
 * groupait — le géant n'étant posé nulle part, ses quatre morceaux restaient
 * séparés. Une palette montre ce qu'on peut poser, pas ce qui est posé.
 *
 * La SECONDE : la vignette annonçait « 2×2, 4 cases » et la pose ne mettait
 * qu'un morceau, parce que les deux lisaient des sources différentes. Une
 * infobulle qui ment est pire que pas d'infobulle.
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

    /**
     * Les morceaux d'une famille se groupent, MÊME sans découpe connue.
     *
     * C'est le premier défaut : le regroupement vient des fichiers, pas de
     * la carte. Sans découpe, la vignette le dit et la pose reste morceau par
     * morceau — mais on voit au moins que ces quatre images vont ensemble.
     */
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

    /** Un décor d'une seule case n'est pas un objet : il reste une vignette simple. */
    public function testASingleTileDecorStaysOnItsOwn(): void
    {
        $html = SceneryPaletteView::render($this->pieces('gm_rocher'), []);

        $this->assertStringNotContainsString('scenery-object', $html);
        $this->assertStringContainsString('data-name="gm_rocher"', $html);
    }

    /**
     * Avec une découpe connue, la vignette l'annonce ET la décrit en données.
     *
     * C'est le second défaut : `data-figure` est ce que le curseur de
     * l'éditeur reconstruit à l'échelle de la carte. Si la vignette annonce
     * une forme, la pose et le curseur doivent montrer la même.
     */
    public function testAKnownFootprintIsAnnouncedAndDescribed(): void
    {
        $html = SceneryPaletteView::render(
            $this->pieces('gm_arche-00', 'gm_arche-01'),
            ['gm_arche' => [
                'w' => 2, 'h' => 1, 'cells' => 2, 'holed' => false,
                'offsets' => [0 => [0, 0], 1 => [1, 0]],
            ]]
        );

        $this->assertStringContainsString('gm_arche — 2×1, 2 cases', $html);
        $this->assertStringNotContainsString('scenery-object--unknown', $html);
        $this->assertStringContainsString('data-figure=', $html);
        $this->assertStringContainsString('&quot;w&quot;:2', $html, 'la figure voyage en données');
    }

    /** Une figure trouée est signalée : c'est une forme, pas un défaut. */
    public function testAHoledFigureSaysSo(): void
    {
        $html = SceneryPaletteView::render(
            $this->pieces('gm_geant-00', 'gm_geant-01'),
            ['gm_geant' => [
                'w' => 3, 'h' => 3, 'cells' => 2, 'holed' => true,
                'offsets' => [0 => [0, 0], 1 => [2, 2]],
            ]]
        );

        $this->assertStringContainsString('figure trouée', $html);
    }

    /**
     * Le catalogue lu sur les IMAGES écarte celles qui mentent.
     *
     * L'image d'ensemble de `geant_petrifie` annonce 1×2 cases quand quatre
     * morceaux existent : elle ne montre que le corps. La croire ferait poser
     * des géants tronqués — on préfère ne pas connaître la figure.
     *
     * Le cas s'appuie sur les images du dépôt : il saute si elles manquent
     * (déploiement sans `img/`).
     */
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
        $this->assertSame(2, $footprints['arbre_sacre']['w']);
        $this->assertSame(2, $footprints['arbre_sacre']['h']);
    }
}
