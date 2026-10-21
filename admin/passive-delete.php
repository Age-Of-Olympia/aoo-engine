<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionPassiveDeleteService;
use App\Service\AdminMenuAccessService;

(new AdminMenuAccessService())->enforce('actions.passives');

handleAdminPost('/admin/passive-workbench.php', function (): string {
    (new ActionPassiveDeleteService())->delete((int) ($_POST['passive_id'] ?? 0));

    return 'Passif supprimé.';
});
