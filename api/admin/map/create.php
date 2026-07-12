<?php
/**
 * Admin API : crée un plan vierge dans le jeu.
 *
 * POST /api/admin/map/create.php  body JSON : { "plan": "mon_nouveau_plan" }
 * Auth : header X-AoO-Tiled-Token (jeton délivré par auth.php)
 *
 * Crée la coordonnée d'amorce (0,0,0) ; 409 si le plan existe déjà.
 * L'éventuel JSON de plan (datas/private/plans/<plan>.json — fond, biomes,
 * player_visibility) reste à créer à la main si besoin.
 */

use App\Service\TiledMapService;

require_once __DIR__ . '/_common.php';

tiledRequireAdmin();

$body = json_decode(file_get_contents('php://input'), true);
$plan = (string) ($body['plan'] ?? '');

if (!tiledValidPlanName($plan)) {
    tiledFail(400, 'Nom de plan invalide : minuscules, chiffres, _ et - uniquement (64 max)');
}

try {
    (new TiledMapService())->createPlan($plan);
} catch (\RuntimeException $e) {
    tiledFail(tiledHttpCode($e), $e->getMessage());
}

tiledSucceed(['plan' => $plan]);
