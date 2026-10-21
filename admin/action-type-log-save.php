<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionTypeLogEditService;
use App\Service\AdminMenuAccessService;

(new AdminMenuAccessService())->enforce('actions.type-defaults');

$typeKey = (string) ($_POST['type_key'] ?? '');

handleAdminPost('/admin/action-type-defaults.php?type=' . urlencode($typeKey), function () use ($typeKey): string {
    (new ActionTypeLogEditService())->save(
        $typeKey,
        isset($_POST['actor_template']) ? (string) $_POST['actor_template'] : null,
        isset($_POST['target_template']) ? (string) $_POST['target_template'] : null,
    );

    return 'Messages de journal enregistrés.';
});
