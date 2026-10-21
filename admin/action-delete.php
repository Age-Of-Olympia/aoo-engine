<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionDeleteService;
use App\Service\AdminMenuAccessService;

(new AdminMenuAccessService())->enforce('actions.edit');

$actionId = (int) ($_POST['action_id'] ?? 0);

handleAdminPost('/admin/action-workbench.php', function () use ($actionId): string {
    (new ActionDeleteService())->delete($actionId);

    return 'Action supprimée.';
});
