<?php
/**
 * Admin API : connexion de l'extension Tiled avec un compte du jeu.
 *
 * POST /api/admin/map/auth.php  body JSON : { "name": "...", "psw": "..." }
 * Le compte doit posséder l'option isAdmin, lui-même ou via l'un de ses PNJ.
 *
 * Réponse : { success, token, expiresAt } — jeton à renvoyer dans le header
 * X-AoO-Tiled-Token des appels suivants.
 */

use App\Service\FirewallService;
use App\Service\TiledAuthService;

require_once __DIR__ . '/_common.php';

if (!TiledAuthService::isEnabled()) {
    tiledFail(403, 'Endpoints Tiled désactivés (TILED_HMAC_SECRET vide ou absent)');
}

// Même pare-feu anti-force-brute que login.php
$firewall = new FirewallService();
$firewall->TryPassFirewall();

$body = json_decode(file_get_contents('php://input'), true);
$name = trim((string) ($body['name'] ?? ''));
$password = (string) ($body['psw'] ?? '');

if ($name === '' || $password === '') {
    tiledFail(400, 'name et psw sont requis');
}

$playerId = TiledAuthService::authenticate($name, $password);

if ($playerId === null) {
    $firewall->RecordFailedAttempt();
    tiledFail(401, 'Identifiants invalides ou compte sans droits admin');
}

tiledSucceed(TiledAuthService::issueToken($playerId));
