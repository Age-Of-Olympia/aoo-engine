<?php
/** Cult admin mutations (POST only). */
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\AdminMenuAccessService;
use App\Service\CultAdminService;

(new AdminMenuAccessService())->enforce('faith.cults');

$action = (string) ($_POST['action'] ?? '');
$id = (int) ($_POST['id'] ?? 0);
$redirect = match ($action) {
    'edit' => '/admin/cults.php?action=edit&id=' . $id,
    default => '/admin/cults.php',
};

handleAdminPost($redirect, static function () use ($action, $id): string {
    $service = new CultAdminService();
    if ($action === 'remove') {
        if ($id === 0) {
            throw new InvalidArgumentException('Matricule du dieu manquant.');
        }
        $removed = $service->remove($id);
        return "Culte retiré. {$removed['followers']} fidèle(s) ont perdu leurs PF ; {$removed['altars']} autel(s) désaffilié(s).";
    }

    if ($action === 'add') {
        $pnjId = (int) ($_POST['pnj_id'] ?? 0);
        if ($pnjId === 0) {
            throw new InvalidArgumentException('Veuillez sélectionner un PNJ.');
        }
        $service->addCult($pnjId);
        return "Le PNJ #{$pnjId} peut désormais être prié.";
    }

    foreach (['x', 'y', 'z'] as $coordinate) {
        if (!isset($_POST[$coordinate]) || !is_numeric($_POST[$coordinate])) {
            throw new InvalidArgumentException('Les coordonnées X, Y et Z sont obligatoires.');
        }
    }
    $x = (int) $_POST['x'];
    $y = (int) $_POST['y'];
    $z = (int) $_POST['z'];
    $plan = trim((string) ($_POST['plan'] ?? ''));

    if ($action === 'edit') {
        if ($id === 0) {
            throw new InvalidArgumentException('Matricule du dieu manquant.');
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        $service->update($id, $name, $x, $y, $z, $plan);
        return "Divinité #{$id} enregistrée.";
    }

    throw new InvalidArgumentException('Action inconnue.');
});
