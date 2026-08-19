<?php
/**
 * Admin API : sets de terrain de l'instance (autotiling Tiled).
 *
 * GET /api/admin/map/terrains.php
 * Auth : header X-AoO-Tiled-Token (jeton délivré par auth.php)
 *
 * Réponse : { success, terrains } — le contenu de terrains.json de cette
 * instance (état runtime, absent tant que rien n'a été classé : {}).
 * L'extension Tiled le récupère au pull pour construire ses Terrain Sets.
 */

use App\Service\TerrainTransitionService;

require_once __DIR__ . '/_common.php';

tiledRequireAdmin();

try {
    $terrains = (new TerrainTransitionService())->loadTerrains();
} catch (\RuntimeException $e) {
    tiledFail(500, $e->getMessage());
}

tiledSucceed(['terrains' => $terrains ?: new \stdClass()]);
