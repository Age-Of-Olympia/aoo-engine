<?php
/**
 * Admin API : pinceau de biome de l'extension Tiled.
 *
 * POST /api/admin/map/biome_brush.php
 * Auth : header X-AoO-Tiled-Token (jeton délivré par auth.php)
 * Body JSON : { biome, z, area?: {xMin, xMax, yMin, yMax} }
 *
 * Peint le sol de toutes les cases où ce biome est au-dessus, sur ce niveau,
 * comme un clic du pinceau dans le Tiled de l'admin (BiomeBrushService).
 * Avec `area`, seulement les cases de ce rectangle.
 * Le jeu écrit le sol lui-même : l'extension re-pulle le niveau ensuite, ce
 * qui lui apporte aussi les transitions créées pour l'occasion.
 */

use App\Module\Module;
use App\Module\Modules;
use App\Service\Map\BiomeBrushService;

require_once __DIR__ . '/_common.php';

tiledRequireAdmin();

if (!Modules::on(Module::Biomes)) {
    tiledFail(403, 'Le module biomes est désactivé sur cette instance');
}

$body = json_decode(file_get_contents('php://input'), true);
$biomeId = (int) ($body['biome'] ?? 0);
if ($biomeId <= 0) {
    tiledFail(400, 'Body invalide : biome est requis');
}

$area = null;
if (isset($body['area'])) {
    $area = [];
    foreach (['xMin', 'xMax', 'yMin', 'yMax'] as $bound) {
        if (!is_int($body['area'][$bound] ?? null)) {
            tiledFail(400, 'Body invalide : area.' . $bound . ' doit être un entier');
        }
        $area[$bound] = $body['area'][$bound];
    }
}

$painted = (new BiomeBrushService())->paintBiomeOnLevel($biomeId, (int) ($body['z'] ?? 0), $area);
if ($painted['biome'] === null) {
    tiledFail(404, 'Biome ' . $biomeId . ' inconnu, ou recouvert par un autre biome partout'
        . ($area === null ? '' : ' dans la sélection'));
}

$painted['cells'] = count($painted['coords']);
unset($painted['coords']);

tiledSucceed($painted);
