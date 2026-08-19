<?php

declare(strict_types=1);

/**
 * "Peinture" finishing pass for composed building sprites.
 *
 * Pushes a composed image toward the hand-painted AoO style: palette grade
 * matched on reference paintings, vertical light falloff, edge-based ambient
 * occlusion, brush grain, base shadow and a jittered outline. Deterministic
 * (seeded), alpha-preserving, scale-aware: compose.php requires this file and
 * runs paintPass() on the working canvas (scale S) before the downscale, so
 * the operations come out brush-like and the 50px tiles inherit them.
 *
 * Standalone usage on a final-scale PNG:
 *   php postpass.php run <in.png> <out.png> [--refs DIR] [--seed N]
 *   php postpass.php compare            # demo sheet in out/compare.png
 */

const OPAQUE_MAX = 8;      // GD alpha below this counts as opaque (0..127)
const GRADE_BLEND = 0.55;  // weight of the matched chroma spread
const AO_STRENGTH = 0.28;  // max darkening along inner edges
const GRAIN_AMP = 0.05;    // grain amplitude, fraction of the channel value

// ---------------------------------------------------------------- pixel helpers

function loadPng(string $path): GdImage
{
    $im = imagecreatefrompng($path);
    if ($im === false) {
        fwrite(STDERR, "cannot read $path\n");
        exit(1);
    }
    imagepalettetotruecolor($im);
    imagesavealpha($im, true);
    return $im;
}

/** Deterministic hash of coordinates, in [0,1). */
function hash01(int $x, int $y, int $seed): float
{
    $n = ($x * 374761393 + $y * 668265263 + $seed * 2147483647) & 0x7FFFFFFF;
    $n = (($n ^ ($n >> 13)) * 1274126177) & 0x7FFFFFFFFFFFFFFF;
    return (($n ^ ($n >> 16)) & 0xFFFFFF) / 0x1000000;
}

/** @return array{int,int,int,int} r,g,b,alpha */
function px(GdImage $im, int $x, int $y): array
{
    $c = imagecolorat($im, $x, $y);
    return [($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF, ($c >> 24) & 0x7F];
}

function setPx(GdImage $im, int $x, int $y, int $r, int $g, int $b, int $a): void
{
    imagesetpixel($im, $x, $y, ($a << 24) | ($r << 16) | ($g << 8) | $b);
}

function isOpaque(GdImage $im, int $x, int $y): bool
{
    return ((imagecolorat($im, $x, $y) >> 24) & 0x7F) < OPAQUE_MAX;
}

/** Bounding box of opaque pixels: [x0, y0, x1, y1]. */
function opaqueBbox(GdImage $im): array
{
    [$w, $h] = [imagesx($im), imagesy($im)];
    [$x0, $y0, $x1, $y1] = [$w, $h, -1, -1];
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            if (isOpaque($im, $x, $y)) {
                $x0 = min($x0, $x); $y0 = min($y0, $y);
                $x1 = max($x1, $x); $y1 = max($y1, $y);
            }
        }
    }
    return [$x0, $y0, $x1, $y1];
}

function mulPx(GdImage $im, int $x, int $y, float $f): void
{
    [$r, $g, $b, $a] = px($im, $x, $y);
    setPx(
        $im, $x, $y,
        min(255, (int) round($r * $f)),
        min(255, (int) round($g * $f)),
        min(255, (int) round($b * $f)),
        $a
    );
}

// ---------------------------------------------------------------- palette grade

/** @return array{float,float,float} luma, blue chroma, red chroma */
function toYcc(int $r, int $g, int $b): array
{
    return [
        0.299 * $r + 0.587 * $g + 0.114 * $b,
        128 - 0.168736 * $r - 0.331264 * $g + 0.5 * $b,
        128 + 0.5 * $r - 0.418688 * $g - 0.081312 * $b,
    ];
}

/** @return array{int,int,int} clamped r,g,b */
function toRgb(float $y, float $cb, float $cr): array
{
    $clamp = fn (float $v): int => (int) max(0, min(255, round($v)));
    return [
        $clamp($y + 1.402 * ($cr - 128)),
        $clamp($y - 0.344136 * ($cb - 128) - 0.714136 * ($cr - 128)),
        $clamp($y + 1.772 * ($cb - 128)),
    ];
}

/** Mean and standard deviation of Y/Cb/Cr over the opaque pixels of $images. */
function yccStats(array $images): array
{
    $sum = [0.0, 0.0, 0.0];
    $sq = [0.0, 0.0, 0.0];
    $n = 0;
    foreach ($images as $im) {
        for ($y = 0, $h = imagesy($im); $y < $h; $y++) {
            for ($x = 0, $w = imagesx($im); $x < $w; $x++) {
                [$r, $g, $b, $a] = px($im, $x, $y);
                if ($a >= OPAQUE_MAX) {
                    continue;
                }
                foreach (toYcc($r, $g, $b) as $c => $v) {
                    $sum[$c] += $v;
                    $sq[$c] += $v * $v;
                }
                $n++;
            }
        }
    }
    $n = max(1, $n);
    $stats = [];
    for ($c = 0; $c < 3; $c++) {
        $mean = $sum[$c] / $n;
        $stats[$c] = [$mean, sqrt(max(1e-6, $sq[$c] / $n - $mean * $mean))];
    }
    return $stats;
}

/** Stats of the reference paintings in $dir, memoized per directory. */
function refStats(string $dir): ?array
{
    static $cache = [];
    if (!array_key_exists($dir, $cache)) {
        $files = is_dir($dir) ? (glob("$dir/*.png") ?: []) : [];
        $cache[$dir] = $files === [] ? null : yccStats(array_map('loadPng', $files));
    }
    return $cache[$dir];
}

/**
 * Reinhard-style color transfer toward the reference paintings: the spread
 * follows the paintings (desaturation, softened contrast), the mean only
 * partially — a dark or cool facade keeps its identity.
 */
function paletteGrade(GdImage $im, array $refStats): void
{
    $src = yccStats([$im]);
    [$w, $h] = [imagesx($im), imagesy($im)];
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            [$r, $g, $b, $a] = px($im, $x, $y);
            if ($a >= OPAQUE_MAX) {
                continue;
            }
            $ycc = toYcc($r, $g, $b);
            $out = [];
            foreach ($ycc as $c => $v) {
                [$sMean, $sStd] = $src[$c];
                [$rMean, $rStd] = $refStats[$c];
                $scale = 1 + 0.7 * (min(1.15, $rStd / $sStd) - 1);
                $meanW = $c === 0 ? 0.12 : 0.30;
                $out[$c] = $sMean + $meanW * ($rMean - $sMean) + ($v - $sMean) * $scale;
            }
            [$r, $g, $b] = toRgb($out[0], $out[1], $out[2]);
            setPx($im, $x, $y, $r, $g, $b, $a);
        }
    }
}

// ---------------------------------------------------------------- light & texture

/** Light falls off from the roof line down to the base of the walls. */
function verticalGrade(GdImage $im): void
{
    [, $y0, , $y1] = opaqueBbox($im);
    if ($y1 <= $y0) {
        return;
    }
    for ($y = $y0; $y <= $y1; $y++) {
        $t = ($y - $y0) / ($y1 - $y0);
        $f = 1.04 - 0.14 * $t;
        for ($x = 0, $w = imagesx($im); $x < $w; $x++) {
            if (isOpaque($im, $x, $y)) {
                mulPx($im, $x, $y, $f);
            }
        }
    }
}

/** Separable box blur of a float grid, radius $r, edge-clamped. */
function boxBlur(array $grid, int $w, int $h, int $r): array
{
    $win = 2 * $r + 1;
    $tmp = [];
    for ($y = 0; $y < $h; $y++) {
        $sum = 0.0;
        for ($x = -$r; $x <= $r; $x++) {
            $sum += $grid[$y][max(0, min($w - 1, $x))];
        }
        for ($x = 0; $x < $w; $x++) {
            $tmp[$y][$x] = $sum / $win;
            $sum += $grid[$y][min($w - 1, $x + $r + 1)] - $grid[$y][max(0, $x - $r)];
        }
    }
    $out = [];
    for ($y = 0; $y < $h; $y++) {
        $out[$y] = array_fill(0, $w, 0.0);
    }
    for ($x = 0; $x < $w; $x++) {
        $sum = 0.0;
        for ($y = -$r; $y <= $r; $y++) {
            $sum += $tmp[max(0, min($h - 1, $y))][$x];
        }
        for ($y = 0; $y < $h; $y++) {
            $out[$y][$x] = $sum / $win;
            $sum += $tmp[min($h - 1, $y + $r + 1)][$x] - $tmp[max(0, $y - $r)][$x];
        }
    }
    return $out;
}

/**
 * Edge-based ambient occlusion: strong luminance discontinuities (face
 * junctions, roof line, frames) bleed a soft shadow onto their surroundings.
 */
function ambientOcclusion(GdImage $im, int $scale = 1): void
{
    [$w, $h] = [imagesx($im), imagesy($im)];
    $lum = [];
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            [$r, $g, $b, $a] = px($im, $x, $y);
            $lum[$y][$x] = $a < OPAQUE_MAX ? 0.299 * $r + 0.587 * $g + 0.114 * $b : null;
        }
    }
    $edge = [];
    for ($y = 0; $y < $h; $y++) {
        $edge[$y] = array_fill(0, $w, 0.0);
    }
    for ($y = 1; $y < $h - 1; $y++) {
        for ($x = 1; $x < $w - 1; $x++) {
            if ($lum[$y][$x] === null) {
                continue;
            }
            $d = 0.0;
            foreach ([[1, 0], [0, 1]] as [$dx, $dy]) {
                $n = $lum[$y + $dy][$x + $dx];
                if ($n !== null) {
                    $d = max($d, abs($lum[$y][$x] - $n));
                }
            }
            $edge[$y][$x] = min(1.0, $d / 60.0);
        }
    }
    $edge = boxBlur($edge, $w, $h, $scale);
    $edge = boxBlur($edge, $w, $h, $scale);
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            if ($lum[$y][$x] !== null && $edge[$y][$x] > 0.02) {
                mulPx($im, $x, $y, 1.0 - AO_STRENGTH * min(1.0, $edge[$y][$x] * 1.6));
            }
        }
    }
}

/** Soft block noise, multiplied over every opaque pixel. */
function grain(GdImage $im, int $seed, int $scale = 1): void
{
    [$w, $h] = [imagesx($im), imagesy($im)];
    $block = 2 * $scale;
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            if (!isOpaque($im, $x, $y)) {
                continue;
            }
            $n = hash01(intdiv($x, $block), intdiv($y, $block), $seed)
               + hash01(intdiv($x + $scale, $block), intdiv($y + $scale, $block), $seed + 7);
            mulPx($im, $x, $y, 1.0 + GRAIN_AMP * ($n - 1.0));
        }
    }
}

/** The base of each wall column sinks into shadow, grounding the sprite. */
function baseShadow(GdImage $im, int $scale = 1): void
{
    [$w, $h] = [imagesx($im), imagesy($im)];
    $depth = 5 * $scale;
    for ($x = 0; $x < $w; $x++) {
        $bottom = -1;
        for ($y = $h - 1; $y >= 0; $y--) {
            if (isOpaque($im, $x, $y)) {
                $bottom = $y;
                break;
            }
        }
        for ($i = 0; $i < $depth && $bottom - $i >= 0; $i++) {
            if (isOpaque($im, $x, $bottom - $i)) {
                mulPx($im, $x, $bottom - $i, 0.78 + 0.22 * $i / $depth);
            }
        }
    }
}

/**
 * Darken the silhouette with a jittered line $scale px wide; at final scale
 * also anti-alias it outward (at working scale the downscale does that).
 */
function outlineAndSoften(GdImage $im, int $seed, int $scale = 1): void
{
    [$w, $h] = [imagesx($im), imagesy($im)];
    $ring = [];
    $seen = [];
    $haloPx = [];
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $opq = isOpaque($im, $x, $y);
            $borders = 0;
            $nr = $ng = $nb = 0;
            foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                $xx = $x + $dx;
                $yy = $y + $dy;
                if ($xx < 0 || $xx >= $w || $yy < 0 || $yy >= $h) {
                    $borders += $opq ? 1 : 0;
                    continue;
                }
                if ($opq && !isOpaque($im, $xx, $yy)) {
                    $borders++;
                } elseif (!$opq && isOpaque($im, $xx, $yy)) {
                    $borders++;
                    [$r, $g, $b] = px($im, $xx, $yy);
                    $nr += $r; $ng += $g; $nb += $b;
                }
            }
            if ($opq && $borders > 0) {
                $ring[] = [$x, $y];
                $seen["$x,$y"] = true;
            } elseif (!$opq && $borders >= 2 && $scale === 1) {
                $haloPx[] = [$x, $y, intdiv($nr, $borders), intdiv($ng, $borders), intdiv($nb, $borders)];
            }
        }
    }
    // rings march inward, the ink fading with each ring
    for ($k = 0; $k < $scale && $ring !== []; $k++) {
        $f0 = 0.68 + 0.27 * $k / $scale;
        $next = [];
        foreach ($ring as [$x, $y]) {
            mulPx($im, $x, $y, $f0 + 0.14 * hash01($x, $y, $seed + 31));
            foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                $xx = $x + $dx;
                $yy = $y + $dy;
                if ($xx >= 0 && $xx < $w && $yy >= 0 && $yy < $h
                    && !isset($seen["$xx,$yy"]) && isOpaque($im, $xx, $yy)) {
                    $seen["$xx,$yy"] = true;
                    $next[] = [$xx, $yy];
                }
            }
        }
        $ring = $next;
    }
    imagealphablending($im, false);
    foreach ($haloPx as [$x, $y, $r, $g, $b]) {
        setPx($im, $x, $y, (int) ($r * 0.75), (int) ($g * 0.75), (int) ($b * 0.75), 88);
    }
}

// ---------------------------------------------------------------- entry points

/**
 * The full painterly pass, in place. $scale is the px size of one final px
 * (compose.php passes S for its working canvas, standalone use passes 1).
 */
function paintPass(GdImage $im, string $refsDir, int $seed, int $scale = 1): void
{
    imagealphablending($im, false);
    $refs = refStats($refsDir);
    if ($refs !== null) {
        paletteGrade($im, $refs);
    }
    verticalGrade($im);
    ambientOcclusion($im, $scale);
    grain($im, $seed, $scale);
    baseShadow($im, $scale);
    outlineAndSoften($im, $seed, $scale);
}

function runPass(string $in, string $out, string $refsDir, int $seed): void
{
    $im = loadPng($in);
    if (refStats($refsDir) === null) {
        fwrite(STDERR, "refs dir $refsDir missing, skipping palette grade\n");
    }
    paintPass($im, $refsDir, $seed);
    imagepng($im, $out);
    echo "$out\n";
}

/** Before/after sheet: raw build (--brut) vs painted build, next to a painted reference. */
function compareSheet(string $dir): void
{
    $refs = "$dir/out/refs";
    $builds = [
        ['maison_2x2', '--facade colombage --roof tiles --shape hip'],
        ['tour_1x1', '--facade darkstone --roof slate --shape hip'],
        ['halle_2x2', '--facade stone --roof thatch --shape gable'],
    ];
    $cells = [];
    foreach ($builds as $i => [$form, $opts]) {
        $name = "pp_demo_$i";
        exec("php $dir/compose.php build $form $opts --brut --name {$name}_brut", $o, $c1);
        exec("php $dir/compose.php build $form $opts --name $name", $o, $c2);
        if ($c1 !== 0 || $c2 !== 0) {
            fwrite(STDERR, "compose failed for $form\n");
            exit(1);
        }
        $cells[] = [loadPng("$dir/out/{$name}_brut.png"), loadPng("$dir/out/$name.png")];
    }
    // painted reference, assembled from its 50px tiles
    $ref = imagecreatetruecolor(100, 100);
    imagealphablending($ref, false);
    imagesavealpha($ref, true);
    imagefill($ref, 0, 0, 127 << 24);
    imagealphablending($ref, true);
    foreach (glob("$refs/maison_olympienne_0[0-3].png") as $i => $file) {
        imagecopy($ref, loadPng($file), ($i % 2) * 50, intdiv($i, 2) * 50, 0, 0, 50, 50);
    }

    $zoom = 3;
    $pad = 12;
    $cell = 100 * $zoom;
    $cols = 3; // brut | peint | reference
    $rows = count($cells);
    $sheet = imagecreatetruecolor($cols * ($cell + $pad) + $pad, $rows * ($cell + $pad) + $pad + 16);
    $bg = imagecolorallocate($sheet, 96, 100, 92);
    imagefill($sheet, 0, 0, $bg);
    $ink = imagecolorallocate($sheet, 235, 235, 225);
    foreach (['BRUT', 'PEINT', 'REFERENCE PEINTE'] as $c => $label) {
        imagestring($sheet, 2, $pad + $c * ($cell + $pad) + 4, 2, $label, $ink);
    }
    $paste = function (GdImage $src, int $col, int $row) use ($sheet, $zoom, $pad, $cell) {
        $big = imagescale($src, 100 * $zoom, 100 * $zoom, IMG_NEAREST_NEIGHBOUR);
        imagecopy($sheet, $big, $pad + $col * ($cell + $pad), 16 + $pad + $row * ($cell + $pad), 0, 0, $cell, $cell);
    };
    foreach ($cells as $row => [$before, $after]) {
        $paste($before, 0, $row);
        $paste($after, 1, $row);
    }
    $paste($ref, 2, 0);
    imagepng($sheet, "$dir/out/compare.png");
    echo "$dir/out/compare.png\n";
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $dir = __DIR__;
    $args = array_slice($argv, 1);
    $cmd = array_shift($args) ?? '';
    if ($cmd === 'run' && count($args) >= 2) {
        $refs = "$dir/out/refs";
        $seed = 1;
        for ($i = 2; $i < count($args); $i++) {
            if ($args[$i] === '--refs') {
                $refs = $args[++$i];
            } elseif ($args[$i] === '--seed') {
                $seed = (int) $args[++$i];
            }
        }
        runPass($args[0], $args[1], $refs, $seed);
    } elseif ($cmd === 'compare') {
        compareSheet($dir);
    } else {
        fwrite(STDERR, "usage: postpass.php run <in.png> <out.png> [--refs DIR] [--seed N] | compare\n");
        exit(1);
    }
}
