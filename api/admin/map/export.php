<?php
/**
 * Admin API : exporte les couches d'un plan pour l'extension Tiled.
 *
 * GET /api/admin/map/export.php?plan=arcadia&z=0
 * Auth : header X-AoO-Tiled-Token (jeton délivré par auth.php)
 *
 * Réponse : { success, plan, z, zLevels, tileSize, version,
 *             layers: {tiles: [...], walls: [...], ...},
 *             catalog: {tiles: [noms...], ...},
 *             images: {"walls/arbre1": "img/walls/arbre1.png", ...} }
 *
 * `version` est l'empreinte du contenu authoré : l'import la vérifie pour
 * détecter les éditions concurrentes. map_items est volontairement absent :
 * état runtime (objets au sol), jamais authorable depuis l'éditeur.
 */

use App\Service\TiledMapService;

require_once __DIR__ . '/_common.php';

tiledRequireAdmin();

$plan = (string) ($_GET['plan'] ?? '');
if (!tiledValidPlanName($plan)) {
    tiledFail(400, 'Paramètre plan manquant ou invalide');
}

$z = (int) ($_GET['z'] ?? 0);

$data = (new TiledMapService())->exportPlan($plan, $z);

if ($data === null) {
    tiledFail(404, 'Plan inconnu : ' . $plan . ' (z=' . $z . ')');
}

tiledSucceed($data);
