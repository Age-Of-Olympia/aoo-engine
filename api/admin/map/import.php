<?php
/**
 * Admin API : applique au jeu les couches éditées dans Tiled.
 *
 * POST /api/admin/map/import.php
 * Auth : header X-AoO-Tiled-Token (jeton délivré par auth.php)
 * Body JSON : { plan, z, version, layers: {resources: [{x,y,name}...], triggers: [{x,y,name,params}...], ...},
 *              biomes?: {version, zones: [{id?, type, name, zone: [[x,y]...]}...]} }
 *
 * `biomes` absent : les biomes du plan restent tels quels (ancienne extension,
 * module éteint). Présent : la liste remplace les biomes du plan (voir
 * BiomeAdminService::pushZones), avant les couches, qui recalculent les
 * cases des biomes avec les nouvelles zones.
 *
 * `version` doit être celle reçue au pull : si le plan a changé entre-temps
 * (autre admin), l'import répond 409 sans rien écrire.
 *
 * Garanties (voir TiledMapService) : transactionnel, lignes des joueurs
 * (player_id) intouchables, état runtime (damages, endTime) préservé sur
 * les lignes inchangées, map_items jamais concerné.
 */

use App\Module\Module;
use App\Module\Modules;
use App\Service\Map\BiomeAdminService;
use App\Service\TiledMapService;

require_once __DIR__ . '/_common.php';

tiledRequireAdmin();

$body = json_decode(file_get_contents('php://input'), true);

$plan = (string) ($body['plan'] ?? '');
$version = (string) ($body['version'] ?? '');
$layers = $body['layers'] ?? null;

if (!tiledValidPlanName($plan) || $version === '' || !is_array($layers)) {
    tiledFail(400, 'Body invalide : plan, version et layers sont requis');
}

$z = (int) ($body['z'] ?? 0);

$planConfig = (isset($body['planConfig']) && is_array($body['planConfig'])) ? $body['planConfig'] : null;
$zConfig = (isset($body['zConfig']) && is_array($body['zConfig'])) ? $body['zConfig'] : null;

$biomes = $body['biomes'] ?? null;
if ($biomes !== null && (!is_array($biomes) || !is_array($biomes['zones'] ?? null))) {
    tiledFail(400, 'Body invalide : biomes.zones doit être une liste');
}

try {
    $biomeReport = null;
    if ($biomes !== null && Modules::on(Module::Biomes)) {
        $biomeReport = (new BiomeAdminService())->pushZones($plan, $biomes['zones'], (string) ($biomes['version'] ?? ''));
    }
    $result = (new TiledMapService())->applyPush($plan, $z, $layers, $version, $planConfig, $zConfig);
} catch (\InvalidArgumentException $e) {
    tiledFail(400, $e->getMessage());
} catch (\RuntimeException $e) {
    tiledFail(tiledHttpCode($e), $e->getMessage());
}
if ($biomeReport !== null) {
    $result['biomes'] = $biomeReport;
}

tiledSucceed($result);
