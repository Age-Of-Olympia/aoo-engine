<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionCreateService;
use App\Service\AdminAuthorizationService;
use App\Service\CsrfProtectionService;

AdminAuthorizationService::DoAdminCheck();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/action-workbench.php');
    exit;
}

$csrf = new CsrfProtectionService();

try {
    $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
    $action = (new ActionCreateService())->create(
        (string) ($_POST['type'] ?? ''),
        (string) ($_POST['name'] ?? ''),
        (string) ($_POST['display_name'] ?? ''),
        (int) ($_POST['level'] ?? 1),
        (string) ($_POST['category'] ?? '') ?: null,
    );
    setFlash('success', 'Action créée.');
    $csrf->regenerateToken();
    header('Location: /admin/action-workbench.php?id=' . (int) $action->getId());
    exit;
} catch (\InvalidArgumentException $exception) {
    setFlash('warning', $exception->getMessage());
} catch (\Throwable $exception) {
    setFlash('danger', "Erreur lors de la création de l'action.");
}

header('Location: /admin/action-workbench.php');
exit;
