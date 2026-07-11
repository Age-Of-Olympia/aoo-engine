<?php
/**
 * Admin API : données de disposition d'un monde Tiled.
 *
 * GET /api/admin/map/world.php
 * Auth : header X-AoO-Tiled-Token (jeton délivré par auth.php)
 *
 * Réponse : { success, tileSize, plans: { "<plan>": { x, y, zLevels, bounds, links } } }
 * — position (x, y) sur la carte du monde, étendue par niveau, et plans
 * atteints par les déclencheurs tp (voir TiledMapService::worldLayout).
 */

use App\Service\TiledMapService;

require_once __DIR__ . '/_common.php';

tiledRequireAdmin();

tiledSucceed((new TiledMapService())->worldLayout());
