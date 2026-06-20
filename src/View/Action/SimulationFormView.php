<?php

namespace App\View\Action;

use App\Action\Schema\OptionCatalog;
use App\Action\Schema\SimulationField;
use App\Entity\Action;

/**
 * Renders the simulate panel's "hypothetical state" form: the action-derived
 * fields plus effect/passive pickers sourced from the OptionCatalog. Pure
 * display — repopulates from the submitted values it is handed, nothing more.
 */
final class SimulationFormView
{
    private OptionCatalog $catalog;

    public function __construct(?OptionCatalog $catalog = null)
    {
        $this->catalog = $catalog ?? new OptionCatalog();
    }

    /**
     * @param list<SimulationField> $fields
     * @param array<string, mixed> $posted
     */
    public function render(Action $action, array $fields, array $posted): string
    {
        $id = (int) $action->getId();

        $bySide = [SimulationField::SIDE_SHARED => [], SimulationField::SIDE_ACTOR => [], SimulationField::SIDE_TARGET => []];
        foreach ($fields as $field) {
            $bySide[$field->side][] = $field;
        }

        $shared = '';
        foreach ($bySide[SimulationField::SIDE_SHARED] as $field) {
            $shared .= $this->fieldControl($field, $posted);
        }

        $body = $shared
            . $this->sideGroup('Acteur', SimulationField::SIDE_ACTOR, $bySide[SimulationField::SIDE_ACTOR], $posted)
            . $this->sideGroup('Cible', SimulationField::SIDE_TARGET, $bySide[SimulationField::SIDE_TARGET], $posted)
            . '<div class="form-group"><label>Tirages</label>'
            . '<input class="form-control" type="number" min="1" max="5000" name="runs" value="' . $this->esc($posted['runs'] ?? 1) . '"></div>'
            . '<button class="btn btn-primary" type="submit">Simuler</button>';

        return '<h1>Simuler : ' . $this->esc($action->getDisplayName()) . '</h1>'
            . '<p class="text-muted">Simulation via le moteur réel : conditions, jets, dégâts, messages et logs sont ceux du jeu.</p>'
            . '<form method="post" class="card" style="max-width:560px">'
            . '<input type="hidden" name="id" value="' . $id . '">'
            . '<div class="card-header"><h3 class="card-title">État hypothétique</h3></div>'
            . '<div class="card-body">' . $body . '</div></form>'
            . $this->script();
    }

    /**
     * @param list<SimulationField> $fields
     * @param array<string, mixed> $posted
     */
    private function sideGroup(string $title, string $side, array $fields, array $posted): string
    {
        $controls = '';
        foreach ($fields as $field) {
            $controls .= $this->fieldControl($field, $posted);
        }

        return '<fieldset class="sim-group"><legend>' . $title . '</legend>'
            . '<div class="sim-fields">' . $controls . '</div>'
            . $this->effects($side, 'Effets', $posted)
            . '<div class="form-group"><label>Passifs</label>' . $this->passives($side . '_passives', $posted) . '</div>'
            . '</fieldset>';
    }

    private function shortLabel(string $label): string
    {
        return preg_replace('/^(Acteur|Cible) — /u', '', $label) ?? $label;
    }

    /**
     * @param array<string, mixed> $posted
     */
    private function fieldControl(SimulationField $field, array $posted): string
    {
        if ($field->kind === SimulationField::KIND_DISTANCE) {
            return $this->group($field->label, '<input class="form-control" type="number" min="1" name="distance" value="' . $this->esc($posted['distance'] ?? 1) . '">');
        }

        if ($field->kind === SimulationField::KIND_WEAPON) {
            $name = $field->side . '_weapon';
            $selected = (string) ($posted[$name] ?? $field->default ?? '');

            return $this->group($this->shortLabel($field->label), $this->select($name, ['' => '—'] + $this->catalog->weaponTypes(), $selected));
        }

        $group = $field->side . '_' . $field->kind;
        $default = $field->kind === SimulationField::KIND_REMAINING ? 6 : 10;
        $value = (int) ($posted[$group][$field->key] ?? $default);

        return $this->group(
            $this->shortLabel($field->label),
            '<input class="form-control" type="number" name="' . $this->esc($group) . '[' . $this->esc($field->key) . ']" value="' . $value . '">'
        );
    }

    /**
     * @param array<string, mixed> $posted
     */
    private function effects(string $side, string $label, array $posted): string
    {
        $names = (array) ($posted[$side . '_effect_name'] ?? []);
        $values = (array) ($posted[$side . '_effect_value'] ?? []);
        $rows = '';
        foreach ($names as $i => $name) {
            if (trim((string) $name) !== '') {
                $rows .= $this->effectRow($side, (string) $name, (int) ($values[$i] ?? 0));
            }
        }
        if ($rows === '') {
            $rows = $this->effectRow($side);
        }

        return '<div class="form-group"><label>' . $this->esc($label) . '</label>'
            . '<div id="' . $this->esc($side) . '-effects">' . $rows . '</div>'
            . '<button type="button" class="btn btn-sm btn-outline-secondary" onclick="addEffectRow(\'' . $this->esc($side) . '\')">+ ajouter un effet</button>'
            . '</div>';
    }

    private function effectRow(string $side, string $selected = '', int|string $value = ''): string
    {
        return '<div class="effect-row" style="display:flex;gap:6px;margin-bottom:4px">'
            . $this->select($side . '_effect_name', ['' => '—'] + $this->catalog->effects(), $selected, brackets: true)
            . '<input class="form-control" style="max-width:90px" type="number" name="' . $this->esc($side) . '_effect_value[]" value="' . $this->esc($value) . '" placeholder="val">'
            . '<button type="button" class="btn btn-sm btn-outline-danger" onclick="this.parentNode.remove()">&times;</button>'
            . '</div>';
    }

    /**
     * @param array<string, mixed> $posted
     */
    private function passives(string $name, array $posted): string
    {
        $selected = array_map('strval', (array) ($posted[$name] ?? []));
        $html = '<select class="form-control" name="' . $this->esc($name) . '[]" multiple>';
        foreach ($this->catalog->passives() as $value => $label) {
            $isSelected = in_array((string) $value, $selected, true) ? ' selected' : '';
            $html .= '<option value="' . $this->esc($value) . '"' . $isSelected . '>' . $this->esc($label) . '</option>';
        }

        return $html . '</select>';
    }

    /**
     * @param array<string, string> $options value => label
     */
    private function select(string $name, array $options, string $selected, bool $brackets = false): string
    {
        $html = '<select class="form-control" name="' . $this->esc($name) . ($brackets ? '[]' : '') . '">';
        foreach ($options as $value => $label) {
            $isSelected = ((string) $value === $selected) ? ' selected' : '';
            $html .= '<option value="' . $this->esc($value) . '"' . $isSelected . '>' . $this->esc($label) . '</option>';
        }

        return $html . '</select>';
    }

    private function group(string $label, string $control): string
    {
        return '<div class="form-group"><label>' . $this->esc($label) . '</label>' . $control . '</div>';
    }

    private function script(): string
    {
        return '<script>'
            . '/* Clone the last effect row (cleared) so admins can add name+value pairs. */'
            . 'function addEffectRow(side) {'
            . '  var container = document.getElementById(side + "-effects");'
            . '  var rows = container.getElementsByClassName("effect-row");'
            . '  if (rows.length === 0) { return; }'
            . '  var clone = rows[rows.length - 1].cloneNode(true);'
            . '  clone.querySelectorAll("select, input").forEach(function (el) { el.value = ""; });'
            . '  container.appendChild(clone);'
            . '}'
            . '</script>';
    }

    private function esc(int|string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
