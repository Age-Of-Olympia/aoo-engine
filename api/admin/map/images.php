<?php
/**
 * Admin API : synchronisation des images de l'éditeur Tiled (img/<couche>/…).
 *
 * GET  /api/admin/map/images.php              → { success, layers, images: ["img/walls/arbre1.png", …] }
 * GET  /api/admin/map/images.php?path=<rel>   → { success, path, data } (contenu en base64)
 * POST /api/admin/map/images.php  body JSON : { "path": "img/walls/x.png", "data": "<base64>" }
 *      → { success, path, replaced, bytes }
 *
 * Auth : header X-AoO-Tiled-Token (jeton délivré par auth.php).
 * Les chemins sont strictement bornés aux couches authorables et aux noms
 * d'assets du jeu — voir TileCatalogService::resolveImagePath(). L'upload
 * vérifie en plus les magic bytes (PNG/WebP/GIF) et une taille maximale.
 */

use App\Service\TileCatalogService;
use App\Service\TiledMapService;

require_once __DIR__ . '/_common.php';

tiledRequireAdmin();

$catalog = new TileCatalogService();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode((string) file_get_contents('php://input'), true);

    $path = (string) ($body['path'] ?? '');
    $absolute = $catalog->resolveImagePath($path);
    if ($absolute === null) {
        tiledFail(400, 'Paramètre path manquant ou invalide : ' . $path);
    }

    $bytes = base64_decode((string) ($body['data'] ?? ''), true);
    if ($bytes === false || $bytes === '') {
        tiledFail(400, 'Paramètre data manquant ou base64 invalide');
    }
    if (strlen($bytes) > TileCatalogService::IMAGE_MAX_BYTES) {
        tiledFail(400, 'Image trop lourde (max ' . (TileCatalogService::IMAGE_MAX_BYTES >> 20) . ' Mo)');
    }
    if (!TileCatalogService::looksLikeImage($bytes)) {
        tiledFail(400, 'Le contenu n\'est pas une image PNG, WebP ou GIF');
    }

    $replaced = is_file($absolute);
    if (!is_dir(dirname($absolute)) && !mkdir(dirname($absolute), 0775, true)) {
        tiledFail(500, 'Création du dossier impossible : ' . dirname($path));
    }
    if (file_put_contents($absolute, $bytes) === false) {
        tiledFail(500, 'Écriture impossible : ' . $path);
    }

    tiledSucceed(['path' => $path, 'replaced' => $replaced, 'bytes' => strlen($bytes)]);
}

$path = (string) ($_GET['path'] ?? '');
if ($path !== '') {
    $absolute = $catalog->resolveImagePath($path);
    if ($absolute === null) {
        tiledFail(400, 'Paramètre path invalide : ' . $path);
    }
    if (!is_file($absolute)) {
        tiledFail(404, 'Image inconnue : ' . $path);
    }

    tiledSucceed(['path' => $path, 'data' => base64_encode((string) file_get_contents($absolute))]);
}

tiledSucceed([
    'layers' => array_keys(TiledMapService::AUTHORABLE_LAYERS),
    'images' => $catalog->listImagePaths(),
]);
