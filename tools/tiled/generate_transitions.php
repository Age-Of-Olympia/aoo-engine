<?php
/**
 * Génère les tuiles de transition entre deux biomes pour l'autotiling Tiled
 * (pinceau Terrain), et déclare leurs wangId dans tools/tiled/aoo/terrains.json.
 *
 * Usage (dans le devcontainer) :
 *   php tools/tiled/generate_transitions.php tiles carreaux desert_de_l_egeon
 *
 * Produit img/<couche>/trans_<A>_<B>_<code>.png pour les 14 combinaisons de
 * coins (code = 4 lettres a/b dans l'ordre TL,TR,BR,BL), par fondu bilinéaire
 * entre les deux images de base. Le jeu les rend comme n'importe quelle tuile
 * (img/<table>/<name>.png, Classes/View.php) — img/ n'étant pas versionné,
 * penser à reporter les PNG générés dans la source d'assets déployée.
 *
 * Relançable : écrase les PNG et resynchronise les entrées de terrains.json.
 */

const TILE_SIZE = 50;
const IMAGE_EXTENSIONS = ['png', 'webp', 'gif'];

if ($argc !== 4) {
    fwrite(STDERR, "Usage: php tools/tiled/generate_transitions.php <couche> <tuileA> <tuileB>\n");
    exit(1);
}

[, $layer, $tileA, $tileB] = $argv;

$root = dirname(__DIR__, 2);
$imgDir = $root . '/img/' . $layer;
$terrainsPath = $root . '/tools/tiled/aoo/terrains.json';

foreach ([$tileA, $tileB] as $name) {
    if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $name)) {
        fwrite(STDERR, "Nom de tuile invalide : $name\n");
        exit(1);
    }
}

/** Charge une image de tuile (png/webp/gif) redimensionnée en 50x50. */
function loadTile(string $dir, string $name): \GdImage
{
    foreach (IMAGE_EXTENSIONS as $ext) {
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
 * Masque de fondu 50x50 : interpolation bilinéaire analytique des 4 coins
 * (0 = tuile A, 255 = tuile B) sur toute la tuile — les diagonales restent
 * douces jusque dans les coins, contrairement à un agrandissement 2x2.
 */
function buildMask(int $tl, int $tr, int $br, int $bl): \GdImage
{
    $mask = imagecreatetruecolor(TILE_SIZE, TILE_SIZE);

    for ($y = 0; $y < TILE_SIZE; $y++) {
        $v = $y / (TILE_SIZE - 1);
        for ($x = 0; $x < TILE_SIZE; $x++) {
            $u = $x / (TILE_SIZE - 1);
            $value = (int) round(
                (1 - $u) * (1 - $v) * $tl
                + $u * (1 - $v) * $tr
                + $u * $v * $br
                + (1 - $u) * $v * $bl
            );
            imagesetpixel($mask, $x, $y, imagecolorallocate($mask, $value, $value, $value));
        }
    }

    return $mask;
}

/** Fondu pixel à pixel de B sur A selon le masque. */
function blend(\GdImage $a, \GdImage $b, \GdImage $mask): \GdImage
{
    $out = imagecreatetruecolor(TILE_SIZE, TILE_SIZE);
    imagealphablending($out, false);
    imagesavealpha($out, true);

    for ($y = 0; $y < TILE_SIZE; $y++) {
        for ($x = 0; $x < TILE_SIZE; $x++) {
            $m = (imagecolorat($mask, $x, $y) & 0xFF) / 255;
            $ca = imagecolorsforindex($a, imagecolorat($a, $x, $y));
            $cb = imagecolorsforindex($b, imagecolorat($b, $x, $y));

            $color = imagecolorallocatealpha(
                $out,
                (int) round($ca['red'] + ($cb['red'] - $ca['red']) * $m),
                (int) round($ca['green'] + ($cb['green'] - $ca['green']) * $m),
                (int) round($ca['blue'] + ($cb['blue'] - $ca['blue']) * $m),
                (int) round($ca['alpha'] + ($cb['alpha'] - $ca['alpha']) * $m)
            );
            imagesetpixel($out, $x, $y, $color);
        }
    }

    return $out;
}

$imageA = loadTile($imgDir, $tileA);
$imageB = loadTile($imgDir, $tileB);

// terrains.json : garantir la section de couche, les couleurs et les tuiles pleines
$terrains = json_decode(file_get_contents($terrainsPath), true);
if (!isset($terrains[$layer])) {
    $terrains[$layer] = ['name' => 'Terrains', 'type' => 'corner', 'colors' => [], 'tiles' => []];
}
$cfg = &$terrains[$layer];

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

$generated = 0;

// Tous les mélanges de coins sauf les deux tuiles pleines (0000 et 1111)
for ($combo = 1; $combo <= 14; $combo++) {
    $tl = ($combo >> 3) & 1;
    $tr = ($combo >> 2) & 1;
    $br = ($combo >> 1) & 1;
    $bl = $combo & 1;

    $code = implode('', array_map(fn($c) => $c ? 'b' : 'a', [$tl, $tr, $br, $bl]));
    $name = 'trans_' . $tileA . '_' . $tileB . '_' . $code;

    $image = blend($imageA, $imageB, buildMask($tl * 255, $tr * 255, $br * 255, $bl * 255));
    imagepng($image, $imgDir . '/' . $name . '.png');

    // wangId Tiled : [haut, TR, droite, BR, bas, BL, gauche, TL], coins seuls
    $of = fn(int $corner) => $corner ? $indexB : $indexA;
    $cfg['tiles'][$name] = [0, $of($tr), 0, $of($br), 0, $of($bl), 0, $of($tl)];

    $generated++;
}

file_put_contents(
    $terrainsPath,
    json_encode($terrains, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
);

echo "$generated tuiles de transition générées dans img/$layer/ (trans_{$tileA}_{$tileB}_*.png)\n";
echo "terrains.json mis à jour — re-puller un plan pour recharger les tilesets.\n";
