<?php
/**
 * Socle commun des endpoints admin Tiled (api/admin/map/*) : bootstrap,
 * en-tête JSON, chargement du secret local et garde d'authentification.
 * À inclure en tête de chaque endpoint.
 */

use App\Service\TiledAuthService;
use App\Service\TiledMapService;

require_once __DIR__ . '/../../../config/bootstrap.php';
require_once __DIR__ . '/../../../config/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Secret HMAC local, gitignoré — même pattern optionnel que onesignal_constants
$tiledConstants = __DIR__ . '/../../../config/tiled_constants.php';
if (file_exists($tiledConstants)) {
    require_once $tiledConstants;
}

/** Termine la requête sur une erreur JSON. */
function tiledFail(int $status, string $error): never
{
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $error]);
    exit;
}

/** Termine la requête sur une réponse JSON de succès. */
function tiledSucceed(array $data = []): never
{
    echo json_encode(['success' => true] + $data);
    exit;
}

/** Garde d'authentification : jeton valide + droits admin, sinon 401. */
function tiledRequireAdmin(): int
{
    $playerId = TiledAuthService::validateToken($_SERVER['HTTP_X_AOO_TILED_TOKEN'] ?? null);

    if ($playerId === null) {
        tiledFail(401, 'Jeton invalide, expiré, ou compte sans droits admin — se reconnecter via auth.php');
    }

    return $playerId;
}

/** Code HTTP d'une exception métier de TiledMapService (400/409, sinon 500). */
function tiledHttpCode(\RuntimeException $e): int
{
    return in_array($e->getCode(), [400, 409], true) ? $e->getCode() : 500;
}

/** Règle unique des noms de plan (voir TiledMapService::PLAN_NAME_PATTERN). */
function tiledValidPlanName(string $plan): bool
{
    return (bool) preg_match(TiledMapService::PLAN_NAME_PATTERN, $plan);
}
