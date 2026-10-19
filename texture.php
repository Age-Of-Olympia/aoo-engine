<?php
/* A sliding composed tile, frozen, for the animated layers of the board
 * (App\View\AnimatedLayersView::textureUri). Versioned by its mtime in the
 * URL, so the browser keeps it. No session: it is the same for everyone. */

require_once __DIR__ . '/vendor/autoload.php';

$img = (string) ($_GET['img'] ?? '');
// No dot outside the extension: nothing above img/
$svg = preg_match('#^img/[\w/-]+\.svg$#', $img) === 1 && is_file(__DIR__ . '/' . $img)
    ? \App\View\AnimatedLayersView::frozenTexture(__DIR__ . '/' . $img)
    : null;

if ($svg === null) {
    http_response_code(404);
    exit();
}

header('Content-Type: image/svg+xml');
header('Cache-Control: public, max-age=31536000, immutable');
echo $svg;
