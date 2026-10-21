<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionCreateService;
use App\Service\AdminMenuAccessService;

(new AdminMenuAccessService())->enforce('actions.edit');

requireAdminPost('/admin/action-workbench.php');

try {
    $action = (new ActionCreateService())->create(
        (string) ($_POST['type'] ?? ''),
        (string) ($_POST['name'] ?? ''),
        (string) ($_POST['display_name'] ?? ''),
        (int) ($_POST['level'] ?? 1),
        (string) ($_POST['category'] ?? '') ?: null,
        (string) ($_POST['icon'] ?? ''),
        (string) ($_POST['icon_color'] ?? '') ?: null,
    );
    setFlash('success', 'Action créée.');
    redirectTo('/admin/action-workbench.php?id=' . (int) $action->getId());
} catch (\InvalidArgumentException $exception) {
    setFlash('warning', $exception->getMessage());
} catch (\Throwable $exception) {
    setFlash('danger', "Erreur lors de la création de l'action.");
}

redirectTo('/admin/action-workbench.php');
