<?php
// verifies every form x roof shape stays inside its canvas (run after
// touching FORMS, EAVE or the projection): reports opaque bbox margins
require __DIR__ . '/compose.php';

foreach (array_keys(FORMS) as $form) {
    foreach (['gable', 'hip', 'flat', 'temple', 'banque', 'attique'] as $shape) {
        $img = build($form, 'stone', 'tiles', $shape, seed: 4);
        $w = imagesx($img);
        $h = imagesy($img);
        $minX = $w; $maxX = -1; $minY = $h; $maxY = -1;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                if (((imagecolorat($img, $x, $y) >> 24) & 0x7F) < 120) {
                    $minX = min($minX, $x); $maxX = max($maxX, $x);
                    $minY = min($minY, $y); $maxY = max($maxY, $y);
                }
            }
        }
        $cut = ($minX === 0 || $minY === 0 || $maxX === $w - 1 || $maxY === $h - 1);
        printf("%-14s %-6s margins L%d R%d T%d B%d %s\n", $form, $shape,
            $minX, $w - 1 - $maxX, $minY, $h - 1 - $maxY, $cut ? '<< TOUCHES EDGE' : 'ok');
    }
}
