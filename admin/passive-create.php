<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionPassiveCreateService;
use App\Service\AdminMenuAccessService;

(new AdminMenuAccessService())->enforce('actions.passives');

requireAdminPost('/admin/passive-workbench.php');

try {
    $passive = (new ActionPassiveCreateService())->create((string) ($_POST['name'] ?? ''));
    setFlash('success', 'Passif créé.');
    redirectTo('/admin/passive-workbench.php?id=' . (int) $passive->getId());
} catch (\InvalidArgumentException $exception) {
    setFlash('warning', $exception->getMessage());
} catch (\Throwable $exception) {
    setFlash('danger', 'Erreur lors de la création du passif.');
}

redirectTo('/admin/passive-workbench.php');
