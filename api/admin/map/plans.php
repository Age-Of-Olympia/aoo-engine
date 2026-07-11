<?php
/**
 * Admin API : liste les plans existants et leurs niveaux z.
 *
 * GET /api/admin/map/plans.php
 * Auth : header X-AoO-Tiled-Token (jeton délivré par auth.php, compte admin)
 *
 * Réponse : { success, plans: { "arcadia": { "zLevels": [-1, 0], "coords": 613 }, ... } }
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

echo json_encode([
    'success' => true,
    'plans'   => (new TiledMapService())->listPlans(),
]);
