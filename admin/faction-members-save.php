<?php
/**
 * Faction members — mutation (POST only). Companion to faction-members.php.
 *
 * Updates one character's faction / factionRole / secretFaction /
 * secretFactionRole, with validation against the catalog:
 *  - unknown faction codes are refused (no silent ghost factions);
 *  - a role index outside the faction's role list is coerced to the
 *    faction's default role (defaultRole flag, else 0);
 *  - clearing a faction resets its role index to 0.
 *
 * players.* is the source of truth but Player::get_data() mirrors it into
 * datas/private/players/<id>.json — refresh_data() drops that cache so the
 * change is visible in-game immediately.
 *
 * CSRF-validated; enforces the factions menu access level. PRG back to the
 * filtered list.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Factory\EntityManagerFactory;
use App\Service\AdminMenuAccessService;
use App\Service\CsrfProtectionService;
use App\Service\FactionService;
use Classes\Player;

(new AdminMenuAccessService())->enforce('factions.php');

/** Retour vers la liste filtrée d'origine (querystring rejouée telle quelle). */
$backUrl = '/admin/faction-members.php';
$back = (string) ($_POST['back'] ?? '');
if ($back !== '' && preg_match('/^faction=[^&]*&q=[^&]*$/', $back)) {
    $backUrl .= '?' . $back;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo($backUrl);
}

try {
    (new CsrfProtectionService())->validateTokenOrFail($_POST['csrf_token'] ?? null);
} catch (\Throwable $e) {
    setFlash('warning', 'Jeton de sécurité invalide ou expiré. Rechargez la page et réessayez.');
    redirectTo($backUrl);
}

$playerId = (int) ($_POST['playerId'] ?? 0);

$connection = EntityManagerFactory::getEntityManager()->getConnection();
$existing = $connection->fetchAssociative('SELECT id, name FROM players WHERE id = ?', [$playerId]);
if ($existing === false) {
    setFlash('warning', 'Personnage introuvable.');
    redirectTo($backUrl);
}

$service = new FactionService();

/**
 * Valide un couple (code de faction, index de rôle) :
 * code inconnu → null (refus) ; code vide → ['', 0] ; index hors de la
 * liste des rôles → rôle par défaut de la faction.
 *
 * @return array{0: string, 1: int, 2: string}|null [code, rôle, notice]
 */
$sanitize = static function (string $codeField, string $roleField) use ($service): ?array {
    $code = strtolower(trim((string) ($_POST[$codeField] ?? '')));
    $role = (int) ($_POST[$roleField] ?? 0);

    if ($code === '') {
        return ['', 0, ''];
    }

    $faction = $service->getFactionByCode($code);
    if ($faction === null) {
        return null;
    }

    if (!isset($faction->getRoleNames()[$role])) {
        $fallback = $service->getDefaultRolePosition($faction);
        return [$code, $fallback, " (rôle {$role} hors limites pour « {$code} », remplacé par {$fallback})"];
    }

    return [$code, $role, ''];
};

$faction = $sanitize('faction', 'factionRole');
$secret = $sanitize('secretFaction', 'secretFactionRole');

if ($faction === null || $secret === null) {
    setFlash('warning', 'Code de faction inconnu du catalogue — affectation refusée.');
    redirectTo($backUrl);
}

$connection->executeStatement(
    'UPDATE players SET faction = ?, factionRole = ?, secretFaction = ?, secretFactionRole = ? WHERE id = ?',
    [$faction[0], $faction[1], $secret[0], $secret[1], $playerId]
);

// players.* est mirroré dans datas/private/players/<id>.json au premier
// get_data() : purge du cache pour que le jeu voie le changement tout de suite.
(new Player($playerId))->refresh_data();

setFlash('success', sprintf(
    '%s : faction « %s » (rôle %d), faction secrète « %s » (rôle %d).%s%s',
    $existing['name'],
    $faction[0] !== '' ? $faction[0] : 'aucune',
    $faction[1],
    $secret[0] !== '' ? $secret[0] : 'aucune',
    $secret[1],
    $faction[2],
    $secret[2]
));
redirectTo($backUrl);
