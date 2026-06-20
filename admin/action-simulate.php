<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');

use App\Action\Schema\OptionCatalog;
use App\Service\Action\ActionCatalogService;
use App\Service\Action\ActionSimulationService;
use App\Service\Action\SimulationFormBuilder;
use App\Service\Action\SimulationInput;
use App\View\ActionResultsView;

$catalog = new OptionCatalog();

$id = (int) ($_GET['id'] ?? 0);
$action = (new ActionCatalogService())->getActionById($id);

$esc = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

/** Zip the posted effect-row name[] / value[] arrays into a name => value map. */
$parseEffectRows = static function (string $side): array {
    $names = (array) ($_POST[$side . '_effect_name'] ?? []);
    $values = (array) ($_POST[$side . '_effect_value'] ?? []);
    $map = [];
    foreach ($names as $i => $name) {
        $name = trim((string) $name);
        if ($name !== '') {
            $map[$name] = (int) ($values[$i] ?? 0);
        }
    }

    return $map;
};

ob_start();
if ($action === null) {
    echo '<div class="alert alert-danger">Action introuvable.</div>';
} else {
    $fields = (new SimulationFormBuilder())->fieldsFor($action);
    $posted = $_SERVER['REQUEST_METHOD'] === 'POST';
    $val = static fn(string $name, $default = '') => $_POST[$name] ?? $default;
    $traitVal = static fn(string $group, string $key, int $default) => (int) ($_POST[$group][$key] ?? $default);
    ?>
    <h1>Simuler : <?= $esc($action->getDisplayName()) ?></h1>
    <p><a href="/admin/action-editor.php?id=<?= (int) $action->getId() ?>" class="btn btn-sm btn-outline-secondary">&larr; Éditer</a></p>
    <p class="text-muted">Simulation via le moteur réel : conditions, jets, dégâts, messages et logs sont ceux du jeu.</p>

    <form method="post" class="card" style="max-width:560px">
        <input type="hidden" name="id" value="<?= (int) $action->getId() ?>">
        <div class="card-header"><h3 class="card-title">État hypothétique</h3></div>
        <div class="card-body">
            <?php foreach ($fields as $field): ?>
                <?php if ($field->kind === 'distance'): ?>
                    <div class="form-group"><label><?= $esc($field->label) ?></label><input class="form-control" type="number" min="1" name="distance" value="<?= $esc($val('distance', 1)) ?>"></div>
                <?php elseif ($field->kind === 'weapon'):
                    $selectedWeapon = (string) $val($field->side . '_weapon', $field->default ?? ''); ?>
                    <div class="form-group"><label><?= $esc($field->label) ?></label>
                        <select class="form-control" name="<?= $esc($field->side) ?>_weapon">
                            <option value="">—</option>
                            <?php foreach ($catalog->weaponTypes() as $type => $typeLabel): ?>
                                <option value="<?= $esc($type) ?>"<?= $type === $selectedWeapon ? ' selected' : '' ?>><?= $esc($typeLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: /* trait or remaining */
                    $group = $field->side . '_' . $field->kind;
                    $default = $field->kind === 'remaining' ? 6 : 10;
                    ?>
                    <div class="form-group"><label><?= $esc($field->label) ?></label><input class="form-control" type="number" name="<?= $esc($group) ?>[<?= $esc($field->key) ?>]" value="<?= $traitVal($group, $field->key, $default) ?>"></div>
                <?php endif; ?>
            <?php endforeach; ?>

            <hr>
            <?php
            $effects = $catalog->effects();
            $effectRow = static function (string $side, string $selected = '', $value = '') use ($effects, $esc): string {
                $options = '<option value="">—</option>';
                foreach ($effects as $name => $label) {
                    $options .= '<option value="' . $esc($name) . '"' . ($name === $selected ? ' selected' : '') . '>' . $esc($label) . '</option>';
                }

                return '<div class="effect-row" style="display:flex;gap:6px;margin-bottom:4px">'
                    . '<select class="form-control" name="' . $esc($side) . '_effect_name[]">' . $options . '</select>'
                    . '<input class="form-control" style="max-width:90px" type="number" name="' . $esc($side) . '_effect_value[]" value="' . $esc((string) $value) . '" placeholder="val">'
                    . '<button type="button" class="btn btn-sm btn-outline-danger" onclick="this.parentNode.remove()">&times;</button>'
                    . '</div>';
            };
            $renderEffects = static function (string $side, string $label) use ($effectRow, $esc): string {
                $names = (array) ($_POST[$side . '_effect_name'] ?? []);
                $values = (array) ($_POST[$side . '_effect_value'] ?? []);
                $rows = '';
                foreach ($names as $i => $name) {
                    if (trim((string) $name) !== '') {
                        $rows .= $effectRow($side, (string) $name, (int) ($values[$i] ?? 0));
                    }
                }
                if ($rows === '') {
                    $rows = $effectRow($side);
                }

                return '<div class="form-group"><label>' . $esc($label) . '</label>'
                    . '<div id="' . $esc($side) . '-effects">' . $rows . '</div>'
                    . '<button type="button" class="btn btn-sm btn-outline-secondary" onclick="addEffectRow(\'' . $esc($side) . '\')">+ ajouter un effet</button>'
                    . '</div>';
            };
            ?>
            <?= $renderEffects('actor', 'Effets acteur') ?>
            <?= $renderEffects('target', 'Effets cible') ?>
            <?php
            $passiveOptions = $catalog->passives();
            $renderPassives = static function (string $name) use ($passiveOptions, $esc): string {
                $selected = array_map('strval', (array) ($_POST[$name] ?? []));
                $html = '<select class="form-control" name="' . $esc($name) . '[]" multiple>';
                foreach ($passiveOptions as $value => $label) {
                    $isSelected = in_array((string) $value, $selected, true) ? ' selected' : '';
                    $html .= '<option value="' . $esc($value) . '"' . $isSelected . '>' . $esc($label) . '</option>';
                }

                return $html . '</select>';
            };
            ?>
            <div class="form-group"><label>Passifs acteur</label><?= $renderPassives('actor_passives') ?></div>
            <div class="form-group"><label>Passifs cible</label><?= $renderPassives('target_passives') ?></div>

            <div class="form-group"><label>Nombre de tirages (distribution)</label><input class="form-control" type="number" min="1" max="5000" name="runs" value="<?= $esc($val('runs', 1)) ?>"></div>
            <button class="btn btn-primary" type="submit">Simuler</button>
        </div>
    </form>
    <script>
        /* Clone the last effect row (cleared) so admins can add name+value pairs. */
        function addEffectRow(side) {
            var container = document.getElementById(side + '-effects');
            var rows = container.getElementsByClassName('effect-row');
            if (rows.length === 0) { return; }
            var clone = rows[rows.length - 1].cloneNode(true);
            clone.querySelectorAll('select, input').forEach(function (el) { el.value = ''; });
            container.appendChild(clone);
        }
    </script>

    <?php if ($posted):
        $base = ['pa' => 6, 'pv' => 20, 'pm' => 15, 'mvt' => 6];
        $input = new SimulationInput(
            actorCaracs: array_map('intval', (array) ($_POST['actor_trait'] ?? [])),
            targetCaracs: array_map('intval', (array) ($_POST['target_trait'] ?? [])),
            actorRemaining: array_merge($base, array_map('intval', (array) ($_POST['actor_remaining'] ?? []))),
            targetRemaining: array_merge($base, array_map('intval', (array) ($_POST['target_remaining'] ?? []))),
            distance: max(1, (int) ($_POST['distance'] ?? 1)),
            actorWeapon: ($_POST['actor_weapon'] ?? '') !== '' ? (string) $_POST['actor_weapon'] : null,
            targetWeapon: ($_POST['target_weapon'] ?? '') !== '' ? (string) $_POST['target_weapon'] : 'melee',
            actorEffects: $parseEffectRows('actor'),
            targetEffects: $parseEffectRows('target'),
            actorPassives: array_values(array_filter((array) ($_POST['actor_passives'] ?? []), 'is_string')),
            targetPassives: array_values(array_filter((array) ($_POST['target_passives'] ?? []), 'is_string')),
        );

        $service = new ActionSimulationService();
        try {
            $runs = max(1, min(5000, (int) ($_POST['runs'] ?? 1)));
            $report = $service->distribution($action, $input, $runs);
            ?>
            <div class="card mt-3" style="max-width:560px">
                <div class="card-header"><h3 class="card-title">Distribution (×<?= $report->runs ?>)</h3></div>
                <div class="card-body">
                    <p>Réussite : <strong><?= round($report->successRate() * 100) ?>%</strong> &nbsp; Touche : <strong><?= round($report->hitRate() * 100) ?>%</strong> &nbsp; Dégâts moyens (sur touche) : <strong><?= round($report->averageDamageOnHit, 1) ?></strong></p>
                </div>
            </div>
            <?php if ($report->sample !== null): ?>
                <div class="card mt-3" style="max-width:560px">
                    <div class="card-header"><h3 class="card-title">Exemple détaillé</h3></div>
                    <div class="card-body">
                        <?= (new ActionResultsView($report->sample))->getActionResults() ?>
                        <hr>
                        <strong>Logs</strong>
                        <p class="text-muted">Acteur : <?= $esc($report->sample->getLogsArray()['actor'] ?? '') ?></p>
                        <p class="text-muted">Cible : <?= $esc($report->sample->getLogsArray()['target'] ?? '') ?></p>
                    </div>
                </div>
            <?php endif; ?>
        <?php } catch (\Throwable $e) { ?>
            <div class="alert alert-warning mt-3">Cette action ne peut pas être entièrement simulée (elle dépend de l'état réel du monde, ex. la carte) : <?= $esc($e->getMessage()) ?></div>
        <?php }
    endif;
}
$content = ob_get_clean();
echo admin_layout('Simuler', $content);
