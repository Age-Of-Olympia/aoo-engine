<?php
/**
 * Admin API : applique au jeu les couches éditées dans Tiled.
 *
 * POST /api/admin/map/import.php
 * Auth : header X-AoO-Tiled-Token (jeton délivré par auth.php, compte admin)
 * Body JSON : { plan, z, version, layers: {walls: [{x,y,name}...], triggers: [{x,y,name,params}...], ...} }
 *
 * `version` doit être celle reçue au pull : si le plan a changé entre-temps
 * (autre admin), l'import répond 409 sans rien écrire.
 *
 * Garanties (voir TiledMapService) : transactionnel, lignes des joueurs
 * (player_id) intouchables, état runtime (damages, endTime) préservé sur
 * les lignes inchangées, map_items jamais concerné.
 */

use App\Service\TiledAuthService;
use App\Service\TiledMapService;

require_once __DIR__ . '/../../../config/bootstrap.php';
require_once __DIR__ . '/../../../config/functions.php';
// WALLS_PV : damages par défaut des murs insérés
require_once __DIR__ . '/../../../config/constants.php';

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
$version = (string) ($body['version'] ?? '');
$layers = $body['layers'] ?? null;

if (!preg_match('/^[a-z0-9_-]+$/i', $plan) || $version === '' || !is_array($layers)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Body invalide : plan, version et layers sont requis']);
    exit;
}

$z = (int) ($body['z'] ?? 0);

try {
    $result = (new TiledMapService())->importPlan($plan, $z, $layers, $version);
} catch (\RuntimeException $e) {
    $code = in_array($e->getCode(), [400, 409], true) ? $e->getCode() : 500;
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

echo json_encode(['success' => true] + $result);
