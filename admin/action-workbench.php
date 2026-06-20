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

// Active tab persists in the URL (?tab=) so a refresh stays put; a simulate POST opens on the results.
$activeTab = in_array($_GET['tab'] ?? null, ['config', 'sim'], true)
    ? $_GET['tab']
    : ($_SERVER['REQUEST_METHOD'] === 'POST' ? 'sim' : 'config');

$renderer = new ParameterFieldRenderer();
$schemaCatalog = new ActionSchemaCatalog();
$instructionService = new OutcomeInstructionService();
$csrf = new CsrfProtectionService();

/* ---------- Panel 1: actions list ---------- */
ob_start();
foreach ($actions as $item) {
    $active = ($action && $item->getId() === $action->getId()) ? ' wb-item--active' : '';
    echo '<a class="wb-item' . $active . '" href="/admin/action-workbench.php?id=' . (int) $item->getId() . '&tab=' . $activeTab . '"'
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
    echo '<div class="wb-grid">';
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
    echo '</div>';

    echo '<div class="wb-section-title">Outcomes</div>';
    if ($action->getOutcomes()->count() === 0) {
        echo '<p class="wb-muted">Aucun outcome.</p>';
    }
    echo '<div class="wb-grid">';
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
    echo '</div>';

    echo $renderer->traitDatalist();
    echo '<div class="wb-form-actions"><button type="submit" class="btn btn-success">Enregistrer</button></div>';
    echo '</form>';
}
$configHtml = ob_get_clean();

/* ---------- Panel 3: simulate (form + results side by side) ---------- */
$simResultHtml = '';
if ($action !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $mapper = new SimulationInputMapper();
    try {
        $report = (new ActionSimulationService())->distribution($action, $mapper->fromPost($_POST), $mapper->runs($_POST));
        $simResultHtml = (new SimulationReportView($report))->render();
    } catch (\Throwable $e) {
        $simResultHtml = SimulationReportView::unavailable($e->getMessage());
    }
}
ob_start();
if ($action === null) {
    echo '<p class="wb-empty">—</p>';
} else {
    $fields = (new SimulationFormBuilder())->fieldsFor($action);
    echo '<div class="wb-sim-form">' . (new SimulationFormView())->render($action, $fields, $_POST) . '</div>';
    echo '<div class="wb-sim-result">' . $simResultHtml . '</div>';
}
$simHtml = ob_get_clean();

/* ---------- Assemble the single-screen layout ---------- */
ob_start();
?>
<style>
    .admin-main { padding: 0 !important; }
    .wb {
        display: grid;
        grid-template-columns: 250px minmax(0, 1fr);
        gap: 12px;
        height: 100vh;
        padding: 12px 14px 12px 20px;
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

    .wb-meta { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin-bottom: 8px; }
    .wb-chip { background: #eef2f5; color: #5d6d7e; border-radius: 10px; padding: 2px 9px; font-size: 12px; }
    .wb-section-title { text-transform: uppercase; letter-spacing: .05em; font-size: 11px; font-weight: 700; color: #8a97a3; margin: 10px 0 5px; }
    /* Pack condition/outcome blocks into responsive columns so they sit side by side. */
    .wb-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 8px 10px; align-items: start; }
    .wb-block { border: 1px solid #e7ebee; border-radius: 7px; margin-bottom: 0; }
    .wb-block-head { background: #f7f9fb; padding: 7px 10px; border-bottom: 1px solid #e7ebee; font-weight: 600; font-size: 13px; border-radius: 7px 7px 0 0; }
    .wb-block-body { padding: 8px 10px; display: grid; grid-template-columns: 1fr; gap: 4px; }

    /* Compact single-line fields (label + control on one row) everywhere. */
    .wb .form-group { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }
    .wb .form-group > label { margin: 0; font-size: 12px; color: #5d6d7e; white-space: nowrap; flex: 0 0 auto; }
    .wb .form-group > .form-control { flex: 1 1 auto; min-width: 0; padding: 3px 7px; font-size: 13px; height: 28px; }
    .wb .form-group > input[type="number"] { flex: 0 0 72px; }
    .wb .form-group > select.form-control { height: 28px; }
    .wb select[multiple].form-control { height: 58px; flex: 1 1 auto; }
    .wb .form-check { margin: 0 0 4px; }
    .wb-inst-name { grid-column: 1 / -1; font-weight: 600; font-size: 12px; color: #4a90e2; margin-top: 2px; }
    .wb-raw { grid-column: 1 / -1; font-size: 12px; color: #8a97a3; }
    .wb-raw code { word-break: break-all; }
    /* Configurer labels can be long — let them wrap instead of spilling out of the box. */
    .wb-config .form-group { align-items: flex-start; }
    .wb-config .form-group > label { white-space: normal; flex: 0 1 48%; line-height: 1.25; }
    .wb-config .form-group > .form-control { flex: 1 1 auto; }
    .wb-config .wb-raw { overflow-wrap: anywhere; }
    .wb-config .wb-raw code { white-space: normal; }
    .wb-form-actions { position: sticky; bottom: 0; background: #fff; padding-top: 10px; margin-top: 8px; border-top: 1px solid #e7ebee; }
    .wb-muted, .wb-empty { color: #8a97a3; font-size: 13px; }

    /* Tabs in the main column */
    .wb-tabs { padding: 0 6px; }
    .wb-tabbtns { display: flex; gap: 2px; }
    .wb-tab-btn { border: 0; background: transparent; padding: 11px 16px; font-size: 14px; font-weight: 600; color: #8a97a3; cursor: pointer; border-bottom: 2px solid transparent; }
    .wb-tab-btn:hover { color: #2c3e50; }
    .wb-tab-btn.active { color: #4a90e2; border-bottom-color: #4a90e2; }
    .wb-tab[hidden] { display: none; }

    /* Simulate tab — full width, readable. */
    .wb-sim form.card { max-width: none !important; box-shadow: none; border: 0; margin: 0; padding: 0; }
    .wb-sim .card-header { background: transparent; border: 0; margin: 0 0 4px; padding: 0; }
    .wb-sim h1 { font-size: 1.2em; margin: 0 0 4px; }
    .wb-sim p.text-muted { font-size: 12px; margin: 0 0 10px; }
    .wb-sim .card.mt-3 { max-width: 720px !important; margin-top: 14px; }
    .wb-sim .effect-row { margin-bottom: 4px !important; }
    .wb-sim .card-body { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 24px; align-items: start; max-width: 1000px; }
    .wb-sim .card-body > .form-group,
    .wb-sim .card-body > .sim-run,
    .wb-sim .card-body > button { grid-column: 1 / -1; }
    .wb-sim .sim-run { display: flex; align-items: center; gap: 14px; }
    .wb-sim .sim-run .form-group { margin: 0; }
    .wb-sim .sim-group { grid-column: span 1; min-width: 0; border: 1px solid #e7ebee; border-radius: 8px; padding: 8px 12px 12px; margin: 0; }
    .wb-sim .sim-group > legend { width: auto; border: 0; margin: 0; padding: 0 6px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #4a90e2; }
    .wb-sim .sim-fields { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 4px 16px; }
    .wb-sim .sim-group > .form-group { margin-top: 6px; }
    .wb-sim .form-group > label { font-size: 13px; }
    .wb-sim .form-group > .form-control { font-size: 13px; height: 30px; }
    .wb-sim select[multiple].form-control { height: 84px; }
    /* Results: a full-width one-line distribution bar, then the detailed sample
       capped at a readable width (its row/col breakdown spreads if too wide). */
    .wb-sim-result { margin-top: 14px; }
    .wb-sim-result .sim-distribution { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 22px; padding: 8px 14px; background: #f7f9fb; border: 1px solid #e7ebee; border-radius: 7px; font-size: 13px; }
    .wb-sim-result .sim-distribution .sim-dist-runs { background: #4a90e2; color: #fff; border-radius: 10px; padding: 1px 9px; font-weight: 700; }
    .wb-sim-result .card.mt-3 { margin: 12px 0 0 !important; max-width: 760px !important; }
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
        <div class="wb-col-head wb-tabs">
            <div class="wb-tabbtns">
                <button type="button" class="wb-tab-btn<?= $activeTab === 'config' ? ' active' : '' ?>" data-tab="config">Configurer</button>
                <button type="button" class="wb-tab-btn<?= $activeTab === 'sim' ? ' active' : '' ?>" data-tab="sim">Simuler</button>
            </div>
            <small><?= $action ? $esc($action->getDisplayName()) : '' ?></small>
        </div>
        <div class="wb-col-body">
            <div class="wb-tab wb-config" data-tab="config"<?= $activeTab === 'config' ? '' : ' hidden' ?>><?= renderFlashMessage() . $configHtml ?></div>
            <div class="wb-tab wb-sim" data-tab="sim"<?= $activeTab === 'sim' ? '' : ' hidden' ?>><?= $simHtml ?></div>
        </div>
    </div>
</div>

<script>
    /* Live client-side filter of the actions list. */
    (function () {
        var search = document.getElementById('wb-search');
        if (search) {
            search.addEventListener('input', function () {
                var q = search.value.toLowerCase();
                document.querySelectorAll('#wb-list .wb-item').forEach(function (el) {
                    el.style.display = el.getAttribute('data-search').indexOf(q) === -1 ? 'none' : '';
                });
            });
        }
    })();
    /* Configurer / Simuler tab switching. */
    document.querySelectorAll('.wb-tab-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tab = btn.getAttribute('data-tab');
            document.querySelectorAll('.wb-tab-btn').forEach(function (b) { b.classList.toggle('active', b === btn); });
            document.querySelectorAll('.wb-tab').forEach(function (p) { p.hidden = p.getAttribute('data-tab') !== tab; });
            /* Keep the tab in the URL so a refresh stays on it. */
            var url = new URL(window.location);
            url.searchParams.set('tab', tab);
            window.history.replaceState({}, '', url);
        });
    });
</script>
<?php
$content = ob_get_clean();
echo admin_layout('Workbench', $content);
