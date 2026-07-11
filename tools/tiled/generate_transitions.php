<?php
/**
 * Génère les tuiles de transition entre deux biomes pour l'autotiling Tiled
 * (pinceau Terrain), et déclare leurs wangId dans tools/tiled/aoo/terrains.json.
 *
 * Usage (dans le devcontainer) :
 *   php tools/tiled/generate_transitions.php tiles carreaux desert_de_l_egeon
 *   php tools/tiled/generate_transitions.php --all tiles   # toutes les paires
 *
 * Produit img/<couche>/trans_<A>_<B>_<code>.png pour les 14 combinaisons de
 * coins (code = 4 lettres a/b dans l'ordre TL,TR,BR,BL), par fondu bilinéaire
 * entre les deux images de base. Le jeu les rend comme n'importe quelle tuile
 * (img/<table>/<name>.png, Classes/View.php) et la carte du jeu mélange leurs
 * couleurs (ColorService::colorFor) — img/ n'étant pas versionné, penser à
 * reporter les PNG générés dans la source d'assets déployée.
 *
 * --all génère chaque paire (non ordonnée) de tuiles pleines déclarées dans
 * terrains.json pour la couche. Relançable : écrase les PNG et resynchronise
 * les entrées de terrains.json.
 */

use App\Service\ColorService;
use App\Service\TileCatalogService;
use App\Service\TiledMapService;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

const TILE_SIZE = TiledMapService::TILE_SIZE;

$all = ($argv[1] ?? '') === '--all';

// --all prend 1 argument (la couche), sinon couche + deux tuiles
if ($argc !== ($all ? 3 : 4)) {
    fwrite(STDERR, "Usage: php tools/tiled/generate_transitions.php <couche> <tuileA> <tuileB>\n");
    fwrite(STDERR, "       php tools/tiled/generate_transitions.php --all <couche>\n");
    exit(1);
}

$layer = $all ? $argv[2] : $argv[1];

$root = dirname(__DIR__, 2);
$imgDir = $root . '/img/' . $layer;
$terrainsPath = $root . '/tools/tiled/aoo/terrains.json';

/** Charge une image de tuile (png/webp/gif) redimensionnée en 50x50. */
function loadTile(string $dir, string $name): \GdImage
{
    foreach (TileCatalogService::IMAGE_EXTENSIONS as $ext) {
        $path = $dir . '/' . $name . '.' . $ext;
        if (!file_exists($path)) {
            continue;
        }
        $image = match ($ext) {
            'png' => imagecreatefrompng($path),
            'webp' => imagecreatefromwebp($path),
            'gif' => imagecreatefromgif($path),
        };
        if (!$image) {
            break;
        }
        $scaled = imagescale($image, TILE_SIZE, TILE_SIZE);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        return $scaled;
    }

    fwrite(STDERR, "Image introuvable : $dir/$name.{png,webp,gif}\n");
    exit(1);
}

/**
 * Les 14 masques de fondu (combinaisons de coins sauf les deux pleines),
 * calculés une seule fois : interpolation bilinéaire analytique des 4 coins
 * (0.0 = tuile A, 1.0 = tuile B) sur toute la tuile.
 *
 * @return array<int, array{code: string, corners: array{int,int,int,int}, mask: list<list<float>>}>
 */
function buildMasks(): array
{
    $masks = [];

    for ($combo = 1; $combo <= 14; $combo++) {
        $tl = ($combo >> 3) & 1;
        $tr = ($combo >> 2) & 1;
        $br = ($combo >> 1) & 1;
        $bl = $combo & 1;

        $mask = [];
        for ($y = 0; $y < TILE_SIZE; $y++) {
            $v = $y / (TILE_SIZE - 1);
            $row = [];
            for ($x = 0; $x < TILE_SIZE; $x++) {
                $u = $x / (TILE_SIZE - 1);
                $row[] = (1 - $u) * (1 - $v) * $tl
                    + $u * (1 - $v) * $tr
                    + $u * $v * $br
                    + (1 - $u) * $v * $bl;
            }
            $mask[] = $row;
        }

        $masks[] = [
            'code'    => implode('', array_map(fn($c) => $c ? 'b' : 'a', [$tl, $tr, $br, $bl])),
            'corners' => [$tl, $tr, $br, $bl],
            'mask'    => $mask,
        ];
    }

    return $masks;
}

/** Fondu pixel à pixel de B sur A selon le masque (manipulation d'entiers ARGB directe). */
function blend(\GdImage $a, \GdImage $b, array $mask): \GdImage
{
    $out = imagecreatetruecolor(TILE_SIZE, TILE_SIZE);
    imagealphablending($out, false);
    imagesavealpha($out, true);

    for ($y = 0; $y < TILE_SIZE; $y++) {
        for ($x = 0; $x < TILE_SIZE; $x++) {
            $m = $mask[$y][$x];
            $ca = imagecolorat($a, $x, $y);
            $cb = imagecolorat($b, $x, $y);

            $alpha = (int) round((($ca >> 24) & 0x7F) + ((($cb >> 24) & 0x7F) - (($ca >> 24) & 0x7F)) * $m);
            $red   = (int) round((($ca >> 16) & 0xFF) + ((($cb >> 16) & 0xFF) - (($ca >> 16) & 0xFF)) * $m);
            $green = (int) round((($ca >> 8) & 0xFF) + ((($cb >> 8) & 0xFF) - (($ca >> 8) & 0xFF)) * $m);
            $blue  = (int) round(($ca & 0xFF) + (($cb & 0xFF) - ($ca & 0xFF)) * $m);

            imagesetpixel($out, $x, $y, ($alpha << 24) | ($red << 16) | ($green << 8) | $blue);
        }
    }

    return $out;
}

/** Génère les 14 tuiles d'une paire et déclare leurs wangId dans $cfg. */
function generatePair(array &$cfg, string $imgDir, string $tileA, string $tileB, array $masks): int
{
    foreach ([$tileA, $tileB] as $name) {
        if (!preg_match(TileCatalogService::ASSET_NAME_PATTERN, $name)) {
            fwrite(STDERR, "Nom de tuile invalide : $name\n");
            exit(1);
        }
    }

    $imageA = loadTile($imgDir, $tileA);
    $imageB = loadTile($imgDir, $tileB);

    $colorOf = function (string $tile) use (&$cfg): string {
        $color = $cfg['tiles'][$tile] ?? null;
        if (!is_string($color)) {
            $color = $tile;
            if (!in_array($color, $cfg['colors'], true)) {
                $cfg['colors'][] = $color;
            }
            $cfg['tiles'][$tile] = $color;
        }
        return $color;
    };

    $indexA = array_search($colorOf($tileA), $cfg['colors'], true) + 1;
    $indexB = array_search($colorOf($tileB), $cfg['colors'], true) + 1;

    foreach ($masks as $entry) {
        [$tl, $tr, $br, $bl] = $entry['corners'];

        $name = ColorService::transitionTileName($tileA, $tileB, $entry['code']);

        imagepng(blend($imageA, $imageB, $entry['mask']), $imgDir . '/' . $name . '.png');

        // wangId Tiled : [haut, TR, droite, BR, bas, BL, gauche, TL], coins seuls
        $of = fn(int $corner) => $corner ? $indexB : $indexA;
        $cfg['tiles'][$name] = [0, $of($tr), 0, $of($br), 0, $of($bl), 0, $of($tl)];
    }

    return count($masks);
}

// terrains.json : garantir la section de couche, les couleurs et les tuiles pleines
$terrains = json_decode(file_get_contents($terrainsPath), true);
if (!isset($terrains[$layer])) {
    $terrains[$layer] = ['name' => 'Terrains', 'type' => 'corner', 'colors' => [], 'tiles' => []];
}
$cfg = &$terrains[$layer];

$masks = buildMasks();

if ($all) {
    // Toutes les paires (non ordonnées) de tuiles pleines déclarées
    $fullTiles = array_keys(array_filter($cfg['tiles'], 'is_string'));
    $generated = 0;
    $pairs = 0;

    foreach ($fullTiles as $i => $tileA) {
        foreach (array_slice($fullTiles, $i + 1) as $tileB) {
            $generated += generatePair($cfg, $imgDir, $tileA, $tileB, $masks);
            $pairs++;
        }
    }

    echo "$generated tuiles générées pour $pairs paires dans img/$layer/\n";
} else {
    [, , $tileA, $tileB] = $argv;
    $generated = generatePair($cfg, $imgDir, $tileA, $tileB, $masks);
    echo "$generated tuiles de transition générées dans img/$layer/ (trans_{$tileA}_{$tileB}_*.png)\n";
}

file_put_contents(
    $terrainsPath,
    json_encode($terrains, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
);

echo "terrains.json mis à jour — re-puller un plan pour recharger les tilesets.\n";
