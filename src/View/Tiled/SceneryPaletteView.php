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
 * # Ce qui commande le regroupement : les FICHIERS
 *
 * Une palette montre ce qu'on PEUT poser, pas ce qui se trouve déjà posé.
 * Un premier jet groupait d'après les découpes dérivées de la carte : sur une
 * base de développement qui ne porte que trente-sept décors, cinq familles se
 * regroupaient et les sept cent dix autres vignettes restaient éparses. Le
 * géant n'étant posé nulle part, ses quatre morceaux restaient séparés — la
 * palette n'avait pas changé pour qui la regardait.
 *
 * Le regroupement vient donc des noms de fichiers, qui disent ce qui existe.
 *
 * # Et la FORME vient du catalogue, la même que pour la pose
 *
 * `SceneryFootprintDeriver::catalogue()` la résout une fois pour toutes — la
 * carte d'abord, les images d'ensemble ensuite. La palette et la pose lisent
 * la MÊME source : une infobulle qui annonce 2×2 doit poser un 2×2, sinon
 * elle ment.
 *
 * Sans découpe connue, on ne devine pas : la vignette aligne les morceaux
 * pour montrer qu'ils vont ensemble, et la pose reste morceau par morceau.
 */
final class SceneryPaletteView
{
    /**
     * @param list<array{name: string, url: string}> $pieces les vignettes brutes
     * @param array<string, array{w:int,h:int,offsets:array<int,array{0:int,1:int}>,cells:int,holed:bool}> $footprints le catalogue unifié (carte + images)
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
            /* Un seul morceau : ce n'est pas un objet découpé, c'est un décor. */
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
     * Une vignette d'objet : la figure recomposée depuis ses morceaux.
     *
     * @param array<int, array{name: string, url: string}> $object
     * @param array{w:int,h:int,offsets:array<int,array{0:int,1:int}>,cells:int,holed:bool}|null $footprint
     */
    private static function objectTile(string $family, array $object, ?array $footprint): string
    {
        ksort($object);
        $anchorName = $object[array_key_first($object)]['name'];

        $title = $footprint !== null
            ? sprintf(
                '%s — %d×%d, %d case%s%s',
                $family,
                $footprint['w'],
                $footprint['h'],
                $footprint['cells'],
                $footprint['cells'] > 1 ? 's' : '',
                $footprint['holed'] ? ', figure trouée' : ''
            )
            : sprintf('%s — %d morceaux, découpe inconnue (pose morceau par morceau)', $family, count($object));

        $attrs = 'class="map foregrounds select-name scenery-object'
            . ($footprint === null ? ' scenery-object--unknown' : '') . '"'
            . ' data-type="foregrounds"'
            . ' data-name="' . htmlspecialchars($anchorName, ENT_QUOTES) . '"'
            . ' title="' . htmlspecialchars($title, ENT_QUOTES) . '"';

        /* Sans découpe connue, la vignette aligne les morceaux : on voit
         * qu'ils vont ensemble, sans prétendre savoir comment. */
        $offsets = $footprint['offsets'] ?? self::inARow($object);
        $w = $footprint['w'] ?? count($object);
        $h = $footprint['h'] ?? 1;

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
        $xs = array_column($offsets, 0);
        $ys = array_column($offsets, 1);
        $minX = $xs === [] ? 0 : min($xs);
        $maxY = $ys === [] ? 0 : max($ys);

        /* Vignette bornée : un praetorium de 4×4 ne doit pas occuper la
         * palette à lui seul. */
        $cell = $w > 3 || $h > 3 ? 16 : 25;

        /* La figure, en données : le curseur de l'éditeur la reconstruit à
         * l'échelle de la carte (50 px la case) pour qu'on VOIE ce qu'on
         * s'apprête à poser. Une vignette de palette est trop petite, et le
         * curseur ne montrait qu'un morceau — on posait à l'aveugle. */
        $figure = ['w' => $w, 'h' => $h, 'cells' => []];

        $pieces = '';

        foreach ($offsets as $piece => [$dx, $dy]) {
            if (!isset($object[$piece])) {
                continue;
            }

            $gx = $dx - $minX;
            $gy = $maxY - $dy;

            $figure['cells'][] = ['u' => $object[$piece]['url'], 'x' => $gx, 'y' => $gy];

            $pieces .= '<img src="' . htmlspecialchars($object[$piece]['url'], ENT_QUOTES) . '"'
                . ' style="position:absolute;width:' . $cell . 'px;height:' . $cell . 'px;'
                . 'left:' . ($gx * $cell) . 'px;top:' . ($gy * $cell) . 'px;" loading="lazy" alt="" />';
        }

        $json = json_encode($figure, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return '<span ' . $attrs
            . ' data-figure="' . htmlspecialchars((string) $json, ENT_QUOTES) . '"'
            . ' style="display:inline-block;position:relative;vertical-align:top;'
            . 'width:' . ($w * $cell) . 'px;height:' . ($h * $cell) . 'px;margin:1px;cursor:pointer;">'
            . $pieces . '</span>';
    }
}
