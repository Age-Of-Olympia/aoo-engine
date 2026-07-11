<?php
/**
 * Admin API : connexion de l'extension Tiled avec un compte du jeu.
 *
 * POST /api/admin/map/auth.php  body JSON : { "name": "...", "psw": "..." }
 * Le compte doit posséder l'option isAdmin.
 *
 * Réponse : { success, token, expiresAt } — jeton à renvoyer dans le header
 * X-AoO-Tiled-Token des appels export/import.
 */

use App\Service\TiledAuthService;

require_once __DIR__ . '/../../../config/bootstrap.php';
require_once __DIR__ . '/../../../config/functions.php';

header('Content-Type: application/json; charset=utf-8');

$tiledConstants = __DIR__ . '/../../../config/tiled_constants.php';
if (file_exists($tiledConstants)) {
    require_once $tiledConstants;
}

if (!TiledAuthService::isEnabled()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Endpoints Tiled désactivés (TILED_HMAC_SECRET vide ou absent)']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$name = trim((string) ($body['name'] ?? ''));
$password = (string) ($body['psw'] ?? '');

if ($name === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'name et psw sont requis']);
    exit;
}

$playerId = TiledAuthService::authenticate($name, $password);

if ($playerId === null) {
    sleep(1); // freine la force brute
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Identifiants invalides ou compte sans droits admin']);
    exit;
}

echo json_encode(['success' => true] + TiledAuthService::issueToken($playerId));
