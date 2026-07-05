<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionPassiveCreateService;
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
    $passive = (new ActionPassiveCreateService())->create((string) ($_POST['name'] ?? ''));
    setFlash('success', 'Passif créé.');
    $csrf->regenerateToken();
    header('Location: /admin/passive-workbench.php?id=' . (int) $passive->getId());
    exit;
} catch (\InvalidArgumentException $exception) {
    setFlash('warning', $exception->getMessage());
} catch (\Throwable $exception) {
    setFlash('danger', 'Erreur lors de la création du passif.');
}

header('Location: /admin/passive-workbench.php');
exit;
