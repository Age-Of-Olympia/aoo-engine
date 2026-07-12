<?php
/**
 * Admin API : liste les plans existants et leurs niveaux z.
 *
 * GET /api/admin/map/plans.php
 * Auth : header X-AoO-Tiled-Token (jeton délivré par auth.php)
 *
 * Réponse : { success, plans: { "arcadia": { "zLevels": [-1, 0], "coords": 613 }, ... } }
 */

use App\Service\TiledMapService;

require_once __DIR__ . '/_common.php';

tiledRequireAdmin();

tiledSucceed(['plans' => (new TiledMapService())->listPlans()]);
