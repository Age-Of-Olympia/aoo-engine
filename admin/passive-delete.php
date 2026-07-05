<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionPassiveDeleteService;
use App\Service\AdminAuthorizationService;
use App\Service\CsrfProtectionService;

AdminAuthorizationService::DoAdminCheck();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/passive-workbench.php');
    exit;
}

$csrf = new CsrfProtectionService();

try {
    $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
    (new ActionPassiveDeleteService())->delete((int) ($_POST['passive_id'] ?? 0));
    setFlash('success', 'Passif supprimé.');
    $csrf->regenerateToken();
} catch (\InvalidArgumentException $exception) {
    setFlash('warning', $exception->getMessage());
} catch (\Throwable $exception) {
    setFlash('danger', 'Erreur lors de la suppression du passif.');
}

header('Location: /admin/passive-workbench.php');
exit;
