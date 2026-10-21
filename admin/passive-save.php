<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionPassiveSaveService;
use App\Service\AdminMenuAccessService;

(new AdminMenuAccessService())->enforce('actions.passives');

$id = (int) ($_POST['passive_id'] ?? 0);

handleAdminPost('/admin/passive-workbench.php?id=' . $id, function () use ($id): string {
    (new ActionPassiveSaveService())->saveFields($id, is_array($_POST['passive'] ?? null) ? $_POST['passive'] : []);

    return 'Passif enregistré.';
});
