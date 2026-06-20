<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');

use App\Action\Schema\ActionSchemaCatalog;
use App\Action\Schema\Form\ParameterFieldRenderer;

$catalog = new ActionSchemaCatalog();
$renderer = new ParameterFieldRenderer();

$selected = (string) ($_GET['type'] ?? '');
[$kind, $type] = array_pad(explode('::', $selected, 2), 2, null);

$schema = null;
if ($type !== null && $type !== '') {
    $schema = $kind === 'outcome'
        ? $catalog->schemaForOutcomeInstruction($type)
        : $catalog->schemaForCondition($type);
}

$esc = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

ob_start();
?>
<h1>Action Schema Preview</h1>
<p class="text-muted mb-3">Pick a condition or outcome type to see the typed form the dashboard would render from its parameter schema. Preview only &mdash; nothing is saved.</p>

<form method="get" class="card" style="max-width:520px">
    <div class="form-group" style="margin-bottom:0">
        <label for="type">Type</label>
        <select id="type" name="type" class="form-control" onchange="this.form.submit()">
            <option value="">-- choose a type --</option>
            <optgroup label="Conditions">
                <?php foreach ($catalog->allConditionTypes() as $conditionType): ?>
                    <?php $value = 'condition::' . $conditionType; ?>
                    <option value="<?= $esc($value) ?>"<?= $value === $selected ? ' selected' : '' ?>><?= $esc($conditionType) ?></option>
                <?php endforeach; ?>
            </optgroup>
            <optgroup label="Outcome instructions">
                <?php foreach ($catalog->allOutcomeInstructionTypes() as $outcomeType): ?>
                    <?php $value = 'outcome::' . $outcomeType; ?>
                    <option value="<?= $esc($value) ?>"<?= $value === $selected ? ' selected' : '' ?>><?= $esc($outcomeType) ?></option>
                <?php endforeach; ?>
            </optgroup>
        </select>
    </div>
</form>

<?php if ($schema !== null): ?>
    <div class="card mt-3" style="max-width:520px">
        <div class="card-header"><h3 class="card-title"><?= $esc($type) ?> &mdash; parameters</h3></div>
        <div class="card-body">
            <?php if ($schema->isEmpty()): ?>
                <div class="alert alert-warning">No typed schema yet for <strong><?= $esc($type) ?></strong>. The editor would fall back to a raw-JSON field.</div>
            <?php else: ?>
                <?php foreach ($schema->fields() as $field): ?>
                    <?= $renderer->render($field, 'preview[' . $field->key . ']') ?>
                <?php endforeach; ?>
                <?= $renderer->traitDatalist() ?>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
echo admin_layout('Action Schema Preview', $content);
