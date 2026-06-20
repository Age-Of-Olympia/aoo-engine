<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Action\OutcomeInstruction\OutcomeInstructionFactory;
use App\Action\Schema\ActionSchemaCatalog;
use App\Action\Schema\Form\ParameterFieldRenderer;
use App\Service\Action\ActionCatalogService;
use App\Service\Action\ActionSimulationService;
use App\Service\Action\SimulationFormBuilder;
use App\Service\Action\SimulationInputMapper;
use App\Service\CsrfProtectionService;
use App\Service\OutcomeInstructionService;
use App\View\Action\SimulationFormView;
use App\View\Action\SimulationReportView;

$esc = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$typeOf = static fn(object $a): string => strtolower(substr((new ReflectionClass($a))->getShortName(), 0, -6));

$catalogService = new ActionCatalogService();
$actions = $catalogService->listActions();
$id = (int) ($_GET['id'] ?? ($_POST['id'] ?? 0));
$action = $id ? $catalogService->getActionById($id) : ($actions[0] ?? null);

$renderer = new ParameterFieldRenderer();
$schemaCatalog = new ActionSchemaCatalog();
$instructionService = new OutcomeInstructionService();
$csrf = new CsrfProtectionService();

/* ---------- Panel 1: actions list ---------- */
ob_start();
foreach ($actions as $item) {
    $active = ($action && $item->getId() === $action->getId()) ? ' wb-item--active' : '';
    echo '<a class="wb-item' . $active . '" href="/admin/action-workbench.php?id=' . (int) $item->getId() . '"'
        . ' data-search="' . $esc(strtolower($item->getName() . ' ' . $item->getDisplayName() . ' ' . $typeOf($item) . ' ' . $item->getCategory())) . '">'
        . '<span class="wb-item-name">' . $esc($item->getDisplayName()) . '</span>'
        . '<span class="wb-item-meta">' . $esc($typeOf($item)) . ' · niv.' . $esc($item->getLevel())
        . ' · ' . $item->getConditions()->count() . 'c/' . $item->getOutcomes()->count() . 'o</span>'
        . '</a>';
}
$listHtml = ob_get_clean();

/* ---------- Panel 2: configure (editor) ---------- */
ob_start();
if ($action === null) {
    echo '<p class="wb-empty">Sélectionnez une action.</p>';
} else {
    echo '<form method="post" action="/admin/action-save.php" class="wb-form">';
    echo $csrf->renderTokenField();
    echo '<input type="hidden" name="action_id" value="' . (int) $action->getId() . '">';
    echo '<input type="hidden" name="return_to" value="/admin/action-workbench.php?id=' . (int) $action->getId() . '">';

    echo '<div class="wb-meta">'
        . '<span class="badge badge-info">' . $esc($typeOf($action)) . '</span>'
        . '<span class="wb-chip">niv. ' . $esc($action->getLevel()) . '</span>'
        . ($action->getCategory() ? '<span class="wb-chip">' . $esc($action->getCategory()) . '</span>' : '')
        . '<code class="wb-chip">' . $esc($action->getName()) . '</code>'
        . '</div>';

    echo '<div class="wb-section-title">Conditions</div>';
    if ($action->getConditions()->count() === 0) {
        echo '<p class="wb-muted">Aucune condition.</p>';
    }
    foreach ($action->getConditions() as $condition) {
        $schema = $schemaCatalog->schemaForCondition($condition->getConditionType());
        $params = $condition->getParameters() ?? [];
        echo '<div class="wb-block">';
        echo '<div class="wb-block-head">' . $esc($condition->getConditionType())
            . ($condition->isBlocking() ? ' <span class="badge badge-warning">bloquante</span>' : '') . '</div>';
        echo '<div class="wb-block-body">';
        if ($schema->isEmpty()) {
            echo '<div class="wb-raw">Paramètres bruts : <code>' . $esc((string) json_encode($params)) . '</code></div>';
        } else {
            foreach ($schema->fields() as $field) {
                echo $renderer->render($field, 'cond[' . (int) $condition->getId() . '][' . $field->key . ']', $params[$field->key] ?? null);
            }
        }
        echo '</div></div>';
    }

    echo '<div class="wb-section-title">Outcomes</div>';
    if ($action->getOutcomes()->count() === 0) {
        echo '<p class="wb-muted">Aucun outcome.</p>';
    }
    foreach ($action->getOutcomes() as $outcome) {
        echo '<div class="wb-block">';
        echo '<div class="wb-block-head">Outcome '
            . ($outcome->isOnSuccess() ? '<span class="badge badge-success">succès</span>' : '<span class="badge badge-danger">échec</span>') . '</div>';
        echo '<div class="wb-block-body">';
        foreach ($instructionService->getOutcomeInstructionsByOutcome((int) $outcome->getId()) as $instruction) {
            $instructionType = OutcomeInstructionFactory::typeOf($instruction);
            $schema = $schemaCatalog->schemaForOutcomeInstruction($instructionType);
            $params = $instruction->getParameters() ?? [];
            echo '<div class="wb-inst-name">' . $esc($instructionType) . '</div>';
            if ($schema->isEmpty()) {
                echo '<div class="wb-raw">Paramètres bruts : <code>' . $esc((string) json_encode($params)) . '</code></div>';
            } else {
                foreach ($schema->fields() as $field) {
                    echo $renderer->render($field, 'inst[' . (int) $instruction->getId() . '][' . $field->key . ']', $params[$field->key] ?? null);
                }
            }
        }
        echo '</div></div>';
    }

    echo $renderer->traitDatalist();
    echo '<div class="wb-form-actions"><button type="submit" class="btn btn-success">Enregistrer</button></div>';
    echo '</form>';
}
$configHtml = ob_get_clean();

/* ---------- Panel 3: simulate ---------- */
ob_start();
if ($action === null) {
    echo '<p class="wb-empty">—</p>';
} else {
    $fields = (new SimulationFormBuilder())->fieldsFor($action);
    echo (new SimulationFormView())->render($action, $fields, $_POST);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $mapper = new SimulationInputMapper();
        try {
            $report = (new ActionSimulationService())->distribution($action, $mapper->fromPost($_POST), $mapper->runs($_POST));
            echo (new SimulationReportView($report))->render();
        } catch (\Throwable $e) {
            echo SimulationReportView::unavailable($e->getMessage());
        }
    }
}
$simHtml = ob_get_clean();

/* ---------- Assemble the single-screen layout ---------- */
ob_start();
?>
<style>
    .admin-main { padding: 0 !important; }
    .wb {
        display: grid;
        grid-template-columns: 250px minmax(0, 1fr) 420px;
        gap: 10px;
        height: 100vh;
        padding: 10px;
        box-sizing: border-box;
        background: #ecf0f1;
    }
    .wb-col { display: flex; flex-direction: column; min-height: 0; background: #fff; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
    .wb-col-head { padding: 10px 12px; border-bottom: 1px solid #e7ebee; font-weight: 600; color: #2c3e50; display: flex; align-items: center; justify-content: space-between; gap: 8px; flex: 0 0 auto; }
    .wb-col-head small { font-weight: 400; color: #8a97a3; }
    .wb-col-body { padding: 10px 12px; overflow: auto; flex: 1 1 auto; }

    .wb-search { width: 100%; padding: 7px 10px; border: 1px solid #ced4da; border-radius: 6px; font-size: 13px; box-sizing: border-box; }
    .wb-list { margin-top: 8px; display: flex; flex-direction: column; gap: 2px; }
    .wb-item { display: flex; flex-direction: column; padding: 7px 9px; border-radius: 6px; text-decoration: none; color: #2c3e50; border: 1px solid transparent; }
    .wb-item:hover { background: #f3f6f9; }
    .wb-item--active { background: rgba(74,144,226,.12); border-color: rgba(74,144,226,.35); }
    .wb-item-name { font-weight: 600; font-size: 13px; }
    .wb-item-meta { font-size: 11px; color: #8a97a3; }

    .wb-meta { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin-bottom: 12px; }
    .wb-chip { background: #eef2f5; color: #5d6d7e; border-radius: 10px; padding: 2px 9px; font-size: 12px; }
    .wb-section-title { text-transform: uppercase; letter-spacing: .05em; font-size: 11px; font-weight: 700; color: #8a97a3; margin: 14px 0 6px; }
    .wb-block { border: 1px solid #e7ebee; border-radius: 7px; margin-bottom: 8px; }
    .wb-block-head { background: #f7f9fb; padding: 7px 10px; border-bottom: 1px solid #e7ebee; font-weight: 600; font-size: 13px; border-radius: 7px 7px 0 0; }
    .wb-block-body { padding: 10px; display: grid; grid-template-columns: 1fr 1fr; gap: 8px 12px; }
    .wb-block-body .form-group { margin-bottom: 0; }
    .wb-block-body label { font-size: 12px; margin-bottom: 2px; color: #5d6d7e; }
    .wb-block-body .form-control { padding: 5px 8px; font-size: 13px; }
    .wb-inst-name { grid-column: 1 / -1; font-weight: 600; font-size: 12px; color: #4a90e2; margin-top: 2px; }
    .wb-raw { grid-column: 1 / -1; font-size: 12px; color: #8a97a3; }
    .wb-raw code { word-break: break-all; }
    .wb-form-actions { position: sticky; bottom: 0; background: #fff; padding-top: 10px; margin-top: 8px; border-top: 1px solid #e7ebee; }
    .wb-muted, .wb-empty { color: #8a97a3; font-size: 13px; }

    /* compact the embedded simulate form */
    .wb-col-sim form.card { max-width: none !important; box-shadow: none; border: 0; margin: 0; padding: 0; }
    .wb-col-sim .card-header { background: transparent; border: 0; margin: 0 0 6px; padding: 0; }
    .wb-col-sim h1 { font-size: 1.1em; margin: 0 0 8px; }
    .wb-col-sim .form-group { margin-bottom: 7px; }
    .wb-col-sim .form-control { padding: 5px 8px; font-size: 13px; }
    .wb-col-sim .card.mt-3 { max-width: none !important; }
</style>

<div class="wb">
    <div class="wb-col">
        <div class="wb-col-head">Actions <small><?= count($actions) ?></small></div>
        <div class="wb-col-body">
            <input type="text" class="wb-search" id="wb-search" placeholder="Filtrer…" autocomplete="off">
            <div class="wb-list" id="wb-list"><?= $listHtml ?></div>
        </div>
    </div>

    <div class="wb-col">
        <div class="wb-col-head">Configurer <small><?= $action ? $esc($action->getDisplayName()) : '' ?></small></div>
        <div class="wb-col-body"><?= renderFlashMessage() . $configHtml ?></div>
    </div>

    <div class="wb-col wb-col-sim">
        <div class="wb-col-head">Simuler</div>
        <div class="wb-col-body"><?= $simHtml ?></div>
    </div>
</div>

<script>
    /* Live client-side filter of the actions list. */
    (function () {
        var search = document.getElementById('wb-search');
        if (!search) { return; }
        search.addEventListener('input', function () {
            var q = search.value.toLowerCase();
            document.querySelectorAll('#wb-list .wb-item').forEach(function (el) {
                el.style.display = el.getAttribute('data-search').indexOf(q) === -1 ? 'none' : '';
            });
        });
    })();
</script>
<?php
$content = ob_get_clean();
echo admin_layout('Workbench', $content);
