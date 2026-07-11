<?php
/**
 * Admin API : exporte les couches d'un plan pour l'extension Tiled.
 *
 * GET /api/admin/map/export.php?plan=arcadia&z=0
 * Auth : header X-AoO-Tiled-Token (jeton délivré par auth.php, compte admin)
 *
 * Réponse : { success, plan, z, tileSize, version,
 *             layers: {tiles: [...], walls: [...], ...},
 *             images: {"walls/arbre1": "img/walls/arbre1.png", ...} }
 *
 * `version` est l'empreinte du contenu authoré : l'import la vérifie pour
 * détecter les éditions concurrentes. map_items est volontairement absent :
 * état runtime (objets au sol), jamais authorable depuis l'éditeur.
 */

use App\Service\TiledAuthService;
use App\Service\TiledMapService;

require_once __DIR__ . '/../../../config/bootstrap.php';
require_once __DIR__ . '/../../../config/functions.php';

header('Content-Type: application/json; charset=utf-8');

$tiledConstants = __DIR__ . '/../../../config/tiled_constants.php';
if (file_exists($tiledConstants)) {
    require_once $tiledConstants;
}

if (TiledAuthService::validateToken($_SERVER['HTTP_X_AOO_TILED_TOKEN'] ?? null) === null) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Jeton invalide, expiré, ou compte sans droits admin — se reconnecter via auth.php']);
    exit;
}

$plan = $_GET['plan'] ?? '';
if ($plan === '' || !preg_match('/^[a-z0-9_-]+$/i', $plan)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Paramètre plan manquant ou invalide']);
    exit;
}

$z = (int) ($_GET['z'] ?? 0);

$data = (new TiledMapService())->exportPlan($plan, $z);

if ($data === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Plan inconnu ou vide : ' . $plan . ' (z=' . $z . ')']);
    exit;
}

echo json_encode(['success' => true] + $data);
