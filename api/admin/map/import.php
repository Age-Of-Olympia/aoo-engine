<?php
/**
 * Admin API : applique au jeu les couches éditées dans Tiled.
 *
 * POST /api/admin/map/import.php
 * Auth : header X-AoO-Tiled-Token (jeton délivré par auth.php)
 * Body JSON : { plan, z, version, layers: {walls: [{x,y,name}...], triggers: [{x,y,name,params}...], ...} }
 *
 * `version` doit être celle reçue au pull : si le plan a changé entre-temps
 * (autre admin), l'import répond 409 sans rien écrire.
 *
 * Garanties (voir TiledMapService) : transactionnel, lignes des joueurs
 * (player_id) intouchables, état runtime (damages, endTime) préservé sur
 * les lignes inchangées, map_items jamais concerné.
 */

use App\Service\TiledMapService;

require_once __DIR__ . '/_common.php';
// WALLS_PV : damages par défaut des murs insérés
require_once __DIR__ . '/../../../config/constants.php';

tiledRequireAdmin();

$body = json_decode(file_get_contents('php://input'), true);

$plan = (string) ($body['plan'] ?? '');
$version = (string) ($body['version'] ?? '');
$layers = $body['layers'] ?? null;

if (!tiledValidPlanName($plan) || $version === '' || !is_array($layers)) {
    tiledFail(400, 'Body invalide : plan, version et layers sont requis');
}

$z = (int) ($body['z'] ?? 0);

try {
    $service = new TiledMapService();
    $result = $service->importPlan($plan, $z, $layers, $version);

    // Propriétés du plan (JSON de plan), '' = retirer la clé. Hors du
    // contrôle de version : configuration de plan, pas du contenu de carte.
    if (isset($body['planConfig']) && is_array($body['planConfig'])) {
        $service->writePlanConfig($plan, $body['planConfig']);
    }

    // Bornes visibles du niveau recalées sur le contenu réel
    $service->updateZLevelBounds($plan, $z);

    // Bilan de santé du JSON de plan (validator), remonté si non vide
    $health = $service->validatePlanJson($plan);
    if ($health['errors'] !== [] || $health['warnings'] !== []) {
        $result['planHealth'] = $health;
    }
} catch (\RuntimeException $e) {
    tiledFail(tiledHttpCode($e), $e->getMessage());
}

tiledSucceed($result);
