<?php

namespace App\View\Tiled;

use App\Service\Map\SceneryFootprintDeriver;

/**
 * La palette de décor, par OBJET et non par morceau.
 *
 * Elle listait 715 vignettes — un morceau par vignette. On y voyait
 * `geant_petrifie-00`, `-01`, `-02`, `-03` sans savoir qu'ils font UN géant,
 * et il fallait connaître la découpe pour reconstituer la figure à la main.
 *
 * Chaque famille découpée devient une vignette unique qui montre l'objet
 * entier :
 *
 * - l'image d'ensemble quand elle existe (`img/foregrounds/<base>/<base>.png`,
 *   la convention que `TileCatalogService::buildComposites` lit déjà) — 25
 *   familles l'ont ;
 * - sinon une grille composée des morceaux à leurs décalages dérivés, ce qui
 *   couvre les 41 autres sans rien demander aux graphistes.
 *
 * Les décors d'une seule case restent listés tels quels : il n'y a rien à
 * regrouper.
 */
final class SceneryPaletteView
{
    /**
     * @param list<array{name: string, url: string}> $pieces les vignettes brutes
     * @param array<string, array{w:int,h:int,offsets:array<int,array{0:int,1:int}>,cells:int,holed:bool}> $catalogue
     */
    public static function render(array $pieces, array $catalogue): string
    {
        [$objects, $loners] = self::group($pieces, $catalogue);

        $html = '<div class="scenery-palette">';

        foreach ($objects as $family => $object) {
            $html .= self::objectTile($family, $object, $catalogue[$family]);
        }

        foreach ($loners as $piece) {
            $html .= '<img class="map foregrounds select-name" data-type="foregrounds"'
                . ' data-name="' . htmlspecialchars($piece['name'], ENT_QUOTES) . '"'
                . ' src="' . htmlspecialchars($piece['url'], ENT_QUOTES) . '"'
                . ' width="50" loading="lazy" />';
        }

        return $html . '</div>';
    }

    /**
     * Sépare ce qui compose un objet de ce qui se pose seul.
     *
     * @param list<array{name: string, url: string}> $pieces
     * @param array<string, mixed> $catalogue
     * @return array{0: array<string, array<int, array{name: string, url: string}>>, 1: list<array{name: string, url: string}>}
     */
    private static function group(array $pieces, array $catalogue): array
    {
        $objects = [];
        $loners = [];

        foreach ($pieces as $piece) {
            [$family, $index] = SceneryFootprintDeriver::splitPiece($piece['name']);

            if (isset($catalogue[$family])) {
                $objects[$family][$index] = $piece;
                continue;
            }

            $loners[] = $piece;
        }

        ksort($objects);

        return [$objects, $loners];
    }

    /**
     * Une vignette d'objet : l'image d'ensemble, ou la figure recomposée.
     *
     * @param array<int, array{name: string, url: string}> $object
     * @param array{w:int,h:int,offsets:array<int,array{0:int,1:int}>,cells:int,holed:bool} $footprint
     */
    private static function objectTile(string $family, array $object, array $footprint): string
    {
        ksort($object);
        $anchorPiece = array_key_first($object);
        $anchorName = $object[$anchorPiece]['name'];

        $title = sprintf(
            '%s — %d×%d, %d case%s%s',
            $family,
            $footprint['w'],
            $footprint['h'],
            $footprint['cells'],
            $footprint['cells'] > 1 ? 's' : '',
            $footprint['holed'] ? ', figure trouée' : ''
        );

        /* Le clic porte le morceau d'ANCRE : la pose alignera la figure pour
         * qu'il tombe sur la case visée (SceneryObjectService::cellsToPlace). */
        $attrs = 'class="map foregrounds select-name scenery-object"'
            . ' data-type="foregrounds"'
            . ' data-name="' . htmlspecialchars($anchorName, ENT_QUOTES) . '"'
            . ' title="' . htmlspecialchars($title, ENT_QUOTES) . '"';

        $whole = 'img/foregrounds/' . $family . '/' . $family . '.png';

        if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $whole)) {
            return '<img ' . $attrs . ' src="' . htmlspecialchars($whole, ENT_QUOTES) . '"'
                . ' width="' . (25 * $footprint['w']) . '" loading="lazy" />';
        }

        return self::composed($attrs, $object, $footprint);
    }

    /**
     * La figure recomposée depuis ses morceaux, à leurs décalages dérivés.
     *
     * Les décalages sont relatifs au premier morceau et peuvent être négatifs ;
     * on les ramène à zéro pour poser la grille. Un morceau absent laisse un
     * trou — c'est la forme de l'objet, pas un défaut.
     *
     * @param array<int, array{name: string, url: string}> $object
     * @param array{w:int,h:int,offsets:array<int,array{0:int,1:int}>} $footprint
     */
    private static function composed(string $attrs, array $object, array $footprint): string
    {
        $xs = array_column($footprint['offsets'], 0);
        $ys = array_column($footprint['offsets'], 1);
        $minX = min($xs);
        $maxY = max($ys);

        $cell = 25;
        $html = '<span ' . $attrs . ' style="display:inline-block;position:relative;vertical-align:top;'
            . 'width:' . ($footprint['w'] * $cell) . 'px;height:' . ($footprint['h'] * $cell) . 'px;'
            . 'margin:1px;cursor:pointer;">';

        foreach ($footprint['offsets'] as $piece => [$dx, $dy]) {
            if (!isset($object[$piece])) {
                continue; /* morceau sans vignette : la figure a un trou */
            }

            $html .= '<img src="' . htmlspecialchars($object[$piece]['url'], ENT_QUOTES) . '"'
                . ' style="position:absolute;width:' . $cell . 'px;height:' . $cell . 'px;'
                . 'left:' . (($dx - $minX) * $cell) . 'px;'
                . 'top:' . (($maxY - $dy) * $cell) . 'px;" loading="lazy" alt="" />';
        }

        return $html . '</span>';
    }
}
