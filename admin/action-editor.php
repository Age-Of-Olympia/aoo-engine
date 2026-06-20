<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Action\OutcomeInstruction\OutcomeInstructionFactory;
use App\Action\Schema\ActionSchemaCatalog;
use App\Action\Schema\Form\ParameterFieldRenderer;
use App\Service\Action\ActionCatalogService;
use App\Service\CsrfProtectionService;
use App\Service\OutcomeInstructionService;

$id = (int) ($_GET['id'] ?? 0);
$action = (new ActionCatalogService())->getActionById($id);

$schemaCatalog = new ActionSchemaCatalog();
$renderer = new ParameterFieldRenderer();
$csrf = new CsrfProtectionService();
$instructionService = new OutcomeInstructionService();

$esc = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$rawParams = static fn(?array $params): string => $esc((string) json_encode($params ?? []));

ob_start();
echo renderFlashMessage();

if ($action === null) {
    echo '<div class="alert alert-danger">Action introuvable.</div>';
} else {
    ?>
    <h1><?= $esc($action->getDisplayName()) ?> <small class="text-muted">(<?= $esc($action->getName()) ?>)</small></h1>
    <p>
        <a href="/admin/actions.php" class="btn btn-sm btn-outline-secondary">&larr; Toutes les actions</a>
        <a href="/admin/action-simulate.php?id=<?= (int) $action->getId() ?>" class="btn btn-sm btn-outline-primary">Simuler</a>
    </p>

    <form method="post" action="/admin/action-save.php">
        <?= $csrf->renderTokenField() ?>
        <input type="hidden" name="action_id" value="<?= (int) $action->getId() ?>">

        <h2>Conditions</h2>
        <?php foreach ($action->getConditions() as $condition): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= $esc($condition->getConditionType()) ?><?= $condition->isBlocking() ? ' <span class="badge badge-warning">bloquante</span>' : '' ?></h3>
                </div>
                <div class="card-body">
                    <?php
                    $schema = $schemaCatalog->schemaForCondition($condition->getConditionType());
                    $params = $condition->getParameters() ?? [];
                    if ($schema->isEmpty()) {
                        echo '<div class="alert alert-info">Pas encore de schéma typé. Paramètres : <code>' . $rawParams($params) . '</code></div>';
                    } else {
                        foreach ($schema->fields() as $field) {
                            echo $renderer->render($field, 'cond[' . (int) $condition->getId() . '][' . $field->key . ']', $params[$field->key] ?? null);
                        }
                    }
                    ?>
                </div>
            </div>
        <?php endforeach; ?>

        <h2>Outcomes</h2>
        <?php foreach ($action->getOutcomes() as $outcome): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Outcome <?= $outcome->isOnSuccess() ? '<span class="badge badge-success">succès</span>' : '<span class="badge badge-danger">échec</span>' ?></h3>
                </div>
                <div class="card-body">
                    <?php foreach ($instructionService->getOutcomeInstructionsByOutcome((int) $outcome->getId()) as $instruction): ?>
                        <?php
                        $instructionType = OutcomeInstructionFactory::typeOf($instruction);
                        $schema = $schemaCatalog->schemaForOutcomeInstruction($instructionType);
                        $params = $instruction->getParameters() ?? [];
                        ?>
                        <h4><?= $esc($instructionType) ?></h4>
                        <?php if ($schema->isEmpty()): ?>
                            <div class="alert alert-info">Pas encore de schéma typé. Paramètres : <code><?= $rawParams($params) ?></code></div>
                        <?php else: ?>
                            <?php foreach ($schema->fields() as $field): ?>
                                <?= $renderer->render($field, 'inst[' . (int) $instruction->getId() . '][' . $field->key . ']', $params[$field->key] ?? null) ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?= $renderer->traitDatalist() ?>
        <button type="submit" class="btn btn-success btn-lg">Enregistrer</button>
    </form>
    <?php
}
$content = ob_get_clean();
echo admin_layout('Édition action', $content);
