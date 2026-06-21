<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');

use App\Service\Action\ActionCatalogService;
use App\Service\Action\ActionSimulationService;
use App\Service\Action\SimulationFormBuilder;
use App\Service\Action\SimulationInputMapper;
use App\View\Action\SimulationFormView;
use App\View\Action\SimulationReportView;

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

$fields = (new SimulationFormBuilder())->fieldsFor($action);
$content = (new SimulationFormView())->render($action, $fields, $_POST);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mapper = new SimulationInputMapper();
    try {
        $report = (new ActionSimulationService())->distribution($action, $mapper->fromPost($_POST), $mapper->runs($_POST));
        $content .= (new SimulationReportView($report))->render();
    } catch (\Throwable $e) {
        $content .= SimulationReportView::unavailable($e->getMessage());
    }
}

echo admin_layout('Simuler', $content, $assets);
