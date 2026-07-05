<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionTypeLogEditService;
use App\Service\AdminAuthorizationService;
use App\Service\CsrfProtectionService;

AdminAuthorizationService::DoAdminCheck();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/action-type-defaults.php');
    exit;
}

$csrf = new CsrfProtectionService();
$typeKey = (string) ($_POST['type_key'] ?? '');

try {
    $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
    (new ActionTypeLogEditService())->save(
        $typeKey,
        isset($_POST['actor_template']) ? (string) $_POST['actor_template'] : null,
        isset($_POST['target_template']) ? (string) $_POST['target_template'] : null,
    );
    setFlash('success', 'Messages de journal enregistrés.');
    $csrf->regenerateToken();
} catch (\InvalidArgumentException $exception) {
    setFlash('warning', $exception->getMessage());
} catch (\Throwable $exception) {
    setFlash('danger', "Erreur lors de l'enregistrement.");
}

header('Location: /admin/action-type-defaults.php?type=' . urlencode($typeKey));
exit;
