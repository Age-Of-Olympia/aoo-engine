<?php

namespace App\View\Tiled;

use App\Service\Map\Footprint;
use App\View\SceneryFigure;
use App\Service\Map\SceneryFootprintDeriver;

/**
 * Scenery palette grouped by OBJECT rather than by piece.
 *
 * Grouping comes from the FILE names, not from what the map carries: a
 * palette shows what can be placed, including families placed nowhere yet.
 * The shape comes from `EntityTypeFootprintService::catalogue()`, the same
 * source placement uses, so a tooltip announcing 2×2 places a 2×2.
 *
 * Without a known cut-out nothing is guessed: pieces are laid in a row to
 * show they belong together, and placement stays piece by piece.
 */
final class SceneryPaletteView
{
    /**
     * @param list<array{name: string, url: string}> $pieces raw thumbnails
     * @param array<string, Footprint> $footprints cut-out catalogue
     */
    public static function render(array $pieces, array $footprints): string
    {
        $families = [];
        $loners = [];

        foreach ($pieces as $piece) {
            [$family, $index] = SceneryFootprintDeriver::splitPiece($piece['name']);
            $families[$family][$index] = $piece;
        }

        ksort($families);

        $html = '<div class="scenery-palette">';

        foreach ($families as $family => $object) {
            /* One piece: not a cut-out object, just a decor tile. */
            if (count($object) < 2) {
                $loners[] = reset($object);
                continue;
            }

            $html .= self::objectTile((string) $family, $object, $footprints[$family] ?? null);
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
     * One object thumbnail: the figure recomposed from its pieces.
     *
     * @param array<int, array{name: string, url: string}> $object
     */
    private static function objectTile(string $family, array $object, ?Footprint $footprint): string
    {
        ksort($object);
        $anchorName = $object[array_key_first($object)]['name'];

        $title = $footprint !== null
            ? sprintf(
                '%s — %d×%d, %d case%s%s',
                $family,
                $footprint->width(),
                $footprint->height(),
                $footprint->cells(),
                $footprint->cells() > 1 ? 's' : '',
                $footprint->isHoled() ? ', figure trouée' : ''
            )
            : sprintf('%s — %d morceaux, découpe inconnue (pose morceau par morceau)', $family, count($object));

        $attrs = 'class="map foregrounds select-name scenery-object'
            . ($footprint === null ? ' scenery-object--unknown' : '') . '"'
            . ' data-type="foregrounds"'
            . ' data-name="' . htmlspecialchars($anchorName, ENT_QUOTES) . '"'
            . ' title="' . htmlspecialchars($title, ENT_QUOTES) . '"';

        /* No known cut-out: lay the pieces in a row rather than guess. */
        $offsets = $footprint?->offsets() ?? self::inARow($object);
        $w = $footprint?->width() ?? count($object);
        $h = $footprint?->height() ?? 1;

        return self::composed($attrs, $object, $offsets, $w, $h);
    }

    /**
     * @param array<int, array{name: string, url: string}> $object
     * @return array<int, array{0:int,1:int}>
     */
    private static function inARow(array $object): array
    {
        $offsets = [];
        $i = 0;

        foreach (array_keys($object) as $piece) {
            $offsets[$piece] = [$i++, 0];
        }

        return $offsets;
    }

    /**
     * @param array<int, array{name: string, url: string}> $object
     * @param array<int, array{0:int,1:int}> $offsets
     */
    private static function composed(string $attrs, array $object, array $offsets, int $w, int $h): string
    {
        $placed = [];

        foreach ($offsets as $piece => [$dx, $dy]) {
            if (isset($object[$piece])) {
                $placed[] = ['url' => $object[$piece]['url'], 'x' => $dx, 'y' => $dy];
            }
        }

        $grid = SceneryFigure::grid($placed);

        /* Bounded thumbnail: a 4×4 praetorium must not fill the palette. */
        $cell = $w > 3 || $h > 3 ? 16 : 25;

        /* The figure as data: the editor cursor rebuilds it at map scale
         * (50 px a cell) so the whole object is visible before placing. */
        $figure = ['w' => $w, 'h' => $h, 'cells' => []];

        $pieces = '';

        foreach ($grid['cells'] as $item) {
            $figure['cells'][] = ['u' => $item['url'], 'x' => $item['col'], 'y' => $item['row']];

            $pieces .= '<img src="' . htmlspecialchars($item['url'], ENT_QUOTES) . '"'
                . ' style="position:absolute;width:' . $cell . 'px;height:' . $cell . 'px;'
                . 'left:' . ($item['col'] * $cell) . 'px;top:' . ($item['row'] * $cell) . 'px;"'
                . ' loading="lazy" alt="" />';
        }

        $json = json_encode($figure, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return '<span ' . $attrs
            . ' data-figure="' . htmlspecialchars((string) $json, ENT_QUOTES) . '"'
            . ' style="display:inline-block;position:relative;vertical-align:top;'
            . 'width:' . ($w * $cell) . 'px;height:' . ($h * $cell) . 'px;margin:1px;cursor:pointer;">'
            . $pieces . '</span>';
    }
}
