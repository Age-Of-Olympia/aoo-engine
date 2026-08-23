<?php
/**
 * Socle commun des endpoints admin Tiled (api/admin/map/*) : bootstrap,
 * en-tête JSON, chargement du secret local et garde d'authentification.
 * À inclure en tête de chaque endpoint.
 */

use App\Service\TiledAuthService;
use App\Service\TiledExtensionService;
use App\Service\TiledMapService;

/* Mêmes fichiers de configuration qu'une page du jeu, dans le même ordre
 * (voir config.php). constants.php manquait : getNextEntityId() y lit
 * ENTITY_ID_RANGES sans garde, donc poser un bâtiment depuis Tiled levait
 * « Undefined constant » au lieu de créer l'entité. */
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once __DIR__ . '/../../../config/functions.php';

header('Content-Type: application/json; charset=utf-8');

/* Le corps se compose en mémoire : une erreur fatale doit pouvoir jeter ce
 * qui a déjà été écrit et répondre du JSON à la place. */
ob_start();

/**
 * Une erreur fatale répond en JSON, comme le reste.
 *
 * `display_errors` est éteint en production : un dépassement de mémoire ou de
 * temps y répondait 500 avec un corps VIDE, et l'extension n'avait plus qu'un
 * « réponse illisible » à montrer. Le message existait — dans le journal du
 * serveur, que l'auteur de la carte n'a pas. Il repart donc dans la réponse.
 *
 * Fichier et ligne sans leur chemin : de quoi situer la panne dans le code
 * sans rien dire de l'arborescence du serveur.
 */
register_shutdown_function(function (): void {
    $error = error_get_last();

    if ($error === null
        || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)
    ) {
        return;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode([
        'success' => false,
        'error'   => 'Erreur fatale : ' . tiledFirstLine($error['message'])
            . ' (' . basename((string) $error['file']) . ':' . $error['line'] . ')',
    ]);
});

/** Première ligne d'un message d'erreur, bornée : une trace entière n'aide personne dans une alerte. */
function tiledFirstLine(string $message): string
{
    $line = trim(explode("\n", $message)[0]);

    return mb_strlen($line) > 300 ? mb_substr($line, 0, 300) . '…' : $line;
}

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

/**
 * Version guard: the extension announces its version, the instance says
 * from which one it speaks the same protocol. The bar is an admin
 * setting (dashboard → Options générales), so raising it needs no
 * deployment. See TiledExtensionService.
 */
function tiledRequireExtensionVersion(): void
{
    $service = new TiledExtensionService();
    $announced = TiledExtensionService::normalize($_SERVER['HTTP_X_AOO_TILED_VERSION'] ?? null);

    if (!$service->accepts($announced)) {
        tiledFail(426, $service->refusalMessage($announced));
    }
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

// Runs on include: every endpoint requires this file, so none can skip the guard.
tiledRequireExtensionVersion();
