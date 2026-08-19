<?php

declare(strict_types=1);

/**
 * GD environment self-test — temporary, to diagnose black sprites on hosts
 * whose GD build differs from the devcontainer's. Token-guarded, read-only,
 * fixed code paths (no user input reaches GD). Remove once the host is green.
 *
 *   diag.php?k=aoo-gd-diag-2026          text report
 *   diag.php?k=aoo-gd-diag-2026&png=1    the mini build as PNG
 */

if (($_GET['k'] ?? '') !== 'aoo-gd-diag-2026') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/compose.php';

/** @return array{int,int,int,int} r,g,b,alpha at (x,y) */
function sample(GdImage $im, int $x, int $y): array
{
    $c = imagecolorat($im, $x, $y);
    return [($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF, ($c >> 24) & 0x7F];
}

function report(string $label, array $rgba): void
{
    printf("%-38s rgb=(%3d,%3d,%3d) alpha=%d\n", $label, ...$rgba);
}

if (isset($_GET['png'])) {
    $img = build('hutte_1x1', 'colombage', 'tiles', seed: 4);
    header('Content-Type: image/png');
    imagepng($img);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

echo 'php: ', PHP_VERSION, "\n";
$gd = gd_info();
echo 'gd: ', $gd['GD Version'] ?? '?', ' png=', var_export($gd['PNG Support'] ?? null, true), "\n";
echo 'imageaffine: ', var_export(function_exists('imageaffine'), true), "\n";
echo 'memory_limit: ', ini_get('memory_limit'), "\n\n";

// t1: transparent canvas — expect alpha=127
$canvas = newImage(40, 40);
report('t1 newImage transparent (5,5)', sample($canvas, 5, 5));

// t2: solid texture — expect rgb=(200,50,50) alpha=0
$tex = newImage(16, 16, [200, 50, 50]);
report('t2 newImage solid (8,8)', sample($tex, 8, 8));

// t3: shade() multiplies via alpha overlay — expect ~(140,35,35)
shade($tex, 0.7);
report('t3 shade 0.7 (8,8)', sample($tex, 8, 8));

// t4: raw imageaffine identity — expect same as t3
$layer = imageaffine($tex, [1, 0, 0, 1, 0, 0]);
if ($layer === false) {
    echo "t4 imageaffine identity: FALSE\n";
} else {
    imagesavealpha($layer, true);
    report('t4 imageaffine identity (8,8)', sample($layer, 8, 8));
}

// t4b: the pure-PHP fallback on the same identity — expect same as t3
$manual = affineManualLayer($tex, [1, 0, 0, 1, 0, 0]);
report('t4b affineManualLayer identity (8,8)', $manual === false ? [0, 0, 0, -1] : sample($manual, 8, 8));

// t5: pasteQuad onto the canvas — expect the shaded red at the quad center
$tex2 = newImage(16, 16, [40, 180, 90]);
pasteQuad($canvas, $tex2, [4, 4], [36, 4], [4, 36], 1.0);
report('t5 pasteQuad axis-aligned (20,20)', sample($canvas, 20, 20));

// t6: pasteQuad with a negative determinant (the imageflip detour)
$canvas2 = newImage(40, 40);
$tex3 = newImage(16, 16, [60, 90, 200]);
pasteQuad($canvas2, $tex3, [4, 36], [36, 36], [4, 4], 1.0);
report('t6 pasteQuad flipped (20,20)', sample($canvas2, 20, 20));

// t7: clipToPoly path (imagefilledpolygon signature differs across PHP)
$canvas3 = newImage(40, 40);
$err = '';
try {
    pasteQuad($canvas3, newImage(16, 16, [220, 200, 60]), [4, 4], [36, 4], [4, 36], 1.0,
        [[4, 4], [36, 4], [36, 36], [4, 36]]);
} catch (Throwable $e) {
    $err = get_class($e) . ': ' . $e->getMessage();
}
report('t7 pasteQuad clipped (20,20)', sample($canvas3, 20, 20));
echo $err !== '' ? "t7 threw: $err\n" : '';

// t8: the full mini build, downscaled — count what the sprite is made of
$img = build('hutte_1x1', 'colombage', 'tiles', seed: 4);
$w = imagesx($img);
$h = imagesy($img);
$opaque = $colored = 0;
for ($y = 0; $y < $h; $y++) {
    for ($x = 0; $x < $w; $x++) {
        [$r, $g, $b, $a] = sample($img, $x, $y);
        if ($a < 64) {
            $opaque++;
            if ($r + $g + $b > 90) {
                $colored++;
            }
        }
    }
}
printf("t8 build hutte %dx%d: opaque=%d colored=%d (healthy: opaque ~40%%, colored most of it)\n",
    $w, $h, $opaque, $colored);
