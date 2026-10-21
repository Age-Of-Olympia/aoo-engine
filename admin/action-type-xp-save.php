<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionTypeXpEditService;
use App\Service\AdminMenuAccessService;

(new AdminMenuAccessService())->enforce('actions.type-defaults');

$typeKey = (string) ($_POST['type_key'] ?? '');

handleAdminPost('/admin/action-type-defaults.php?type=' . urlencode($typeKey), function () use ($typeKey): string {
    (new ActionTypeXpEditService())->save(
        $typeKey,
        (string) ($_POST['mode'] ?? ''),
        is_array($_POST['params'] ?? null) ? $_POST['params'] : [],
    );

    return 'Règle d\'XP enregistrée.';
});
