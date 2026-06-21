<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');

use App\Service\Action\ActionCatalogService;
use App\View\Action\SimulationPanelView;

$assets = [
    'styles' => ['/admin/css/action-simulate.css'],
    'scripts' => ['/admin/js/action-simulate.js'],
];

$id = (int) ($_GET['id'] ?? 0);
$action = (new ActionCatalogService())->getActionById($id);

if ($action === null) {
    echo admin_layout('Simuler', '<div class="alert alert-danger">Action introuvable.</div>', $assets);

    return;
}

$panel = new SimulationPanelView();
$content = $panel->form($action, $_POST);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content .= $panel->result($action, $_POST);
}

echo admin_layout('Simuler', $content, $assets);
