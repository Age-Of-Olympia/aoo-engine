<?php
/**
 * Admin Access — option toggle action (POST only).
 *
 * Toggles a single option on a player (add if missing, remove if present) then
 * redirects back to the dashboard (PRG pattern). Companion to admin-access.php.
 *
 * Guarantees:
 *   - Admin-only (DoAdminCheck).
 *   - CSRF-validated (same token the dashboard forms embed).
 *   - Option restricted to the shared whitelist — no arbitrary option writes.
 *   - Granting/revoking isSuperAdmin additionally requires the actor to be a
 *     super-admin (privilege-escalation guard).
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/admin-access-options.php');

use App\Service\AdminAuthorizationService;
use App\Service\AdminMenuAccessService;
use App\Service\CsrfProtectionService;
use App\Factory\PlayerFactory;
use Classes\Db;

// Enforce the same level as the admin-access menu, so a direct POST can't
// bypass a superadmin-only setting on that menu.
(new AdminMenuAccessService())->enforce('admin-access.php');

$backTerm = trim((string) ($_POST['q'] ?? ''));
$back = '/admin/admin-access.php' . ($backTerm !== '' ? '?q=' . urlencode($backTerm) . '#manager' : '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo($back);
}

$csrf = new CsrfProtectionService();
if (!$csrf->validateToken($_POST['csrf_token'] ?? null)) {
    setFlash('warning', 'Jeton de sécurité invalide ou expiré. Rechargez la page et réessayez.');
    redirectTo($back);
}

$targetId = (int) ($_POST['player_id'] ?? 0);
$option   = (string) ($_POST['option'] ?? '');

if (!in_array($option, ADMIN_ACCESS_VALID_OPTIONS, true)) {
    setFlash('warning', 'Option non reconnue : ' . e($option));
    redirectTo($back);
}

if ($targetId === 0) {
    setFlash('warning', 'Matricule invalide.');
    redirectTo($back);
}

// Granting or revoking super-admin is a privilege escalation → super-admin only.
if ($option === 'isSuperAdmin') {
    AdminAuthorizationService::DoSuperAdminCheck();
}

// Target must exist before we touch its options.
$check = (new Db())->get_single('players', $targetId);
if (!$check->num_rows) {
    setFlash('warning', 'Aucun personnage avec le matricule ' . $targetId . '.');
    redirectTo($back);
}

try {
    $target = PlayerFactory::legacy($targetId);
    $target->get_data();

    if ($target->have_option($option)) {
        $target->end_option($option);
        setFlash('success', 'Option ' . e($option) . ' retirée à '
            . e($target->data->name) . ' (#' . $targetId . ').');
    } else {
        $target->add_option($option);
        setFlash('success', 'Option ' . e($option) . ' ajoutée à '
            . e($target->data->name) . ' (#' . $targetId . ').');
    }
    $csrf->regenerateToken();
} catch (\Throwable $exception) {
    setFlash('danger', "Erreur lors de la mise à jour de l'option.");
}

redirectTo($back);
