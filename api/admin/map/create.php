<?php
/**
 * Admin API : crée un plan vierge dans le jeu.
 *
 * POST /api/admin/map/create.php  body JSON : { "plan": "mon_nouveau_plan" }
 * Auth : header X-AoO-Tiled-Token (jeton délivré par auth.php, compte admin)
 *
 * Crée la coordonnée d'amorce (0,0,0) ; 409 si le plan existe déjà.
 * L'éventuel JSON de plan (datas/private/plans/<plan>.json — fond, biomes,
 * player_visibility) reste à créer à la main si besoin.
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

$body = json_decode(file_get_contents('php://input'), true);
$plan = (string) ($body['plan'] ?? '');

if (!preg_match('/^[a-z0-9_-]{1,64}$/', $plan)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Nom de plan invalide : minuscules, chiffres, _ et - uniquement (64 max)']);
    exit;
}

try {
    (new TiledMapService())->createPlan($plan);
} catch (\RuntimeException $e) {
    http_response_code($e->getCode() === 409 ? 409 : 500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

echo json_encode(['success' => true, 'plan' => $plan]);
