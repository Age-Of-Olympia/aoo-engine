<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionCatalogService;
use App\Service\Action\ActionPassiveCatalogService;
use App\Service\SkillOwnershipService;
use App\View\Player\SkillOwnersView;

$type = (string) ($_GET['type'] ?? '');
$ownership = new SkillOwnershipService();

if ($type === 'action') {
    $name = trim((string) ($_GET['name'] ?? ''));
    $action = $name !== '' ? (new ActionCatalogService())->findByName($name) : null;
    if ($action === null) {
        setFlash('warning', 'Action introuvable.');
        redirectTo('/admin/actions.php');
    }
    $title = 'Joueurs ayant « ' . $action->getDisplayName() . ' »';
    $players = $ownership->actionOwners($name);
    $backHref = '/admin/actions.php';
    $backLabel = 'Actions';
} elseif ($type === 'passive') {
    $id = (int) ($_GET['id'] ?? 0);
    $passive = $id > 0 ? (new ActionPassiveCatalogService())->getById($id) : null;
    if ($passive === null) {
        setFlash('warning', 'Passif introuvable.');
        redirectTo('/admin/passive-workbench.php');
    }
    $title = 'Joueurs ayant « ' . $passive->getDisplayName() . ' »';
    $players = $ownership->passiveOwners($id);
    $backHref = '/admin/passive-workbench.php?id=' . $id;
    $backLabel = 'Passifs';
} else {
    setFlash('warning', 'Compétence introuvable.');
    redirectTo('/admin/actions.php');
}

$body = (new SkillOwnersView())->render($title, $backHref, $backLabel, $players);

echo admin_layout($title, renderFlashMessage() . $body);
