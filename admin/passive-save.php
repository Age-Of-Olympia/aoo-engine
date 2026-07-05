<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionPassiveSaveService;
use App\Service\AdminAuthorizationService;
use App\Service\CsrfProtectionService;

AdminAuthorizationService::DoAdminCheck();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/passive-workbench.php');
    exit;
}

$csrf = new CsrfProtectionService();
$id = (int) ($_POST['passive_id'] ?? 0);

try {
    $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
    (new ActionPassiveSaveService())->saveFields($id, is_array($_POST['passive'] ?? null) ? $_POST['passive'] : []);
    setFlash('success', 'Passif enregistré.');
    $csrf->regenerateToken();
} catch (\InvalidArgumentException $exception) {
    setFlash('warning', $exception->getMessage());
} catch (\Throwable $exception) {
    setFlash('danger', "Erreur lors de l'enregistrement du passif.");
}

header('Location: /admin/passive-workbench.php?id=' . $id);
exit;
