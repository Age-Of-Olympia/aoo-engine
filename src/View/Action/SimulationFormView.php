<?php

namespace App\View\Action;

use App\Action\Schema\OptionCatalog;
use App\Action\Schema\SimulationField;
use App\Entity\Action;
use App\Service\Action\SimulationWeaponCatalog;

/**
 * Renders the simulate panel's "hypothetical state" form: the action-derived
 * fields plus effect/passive pickers sourced from the OptionCatalog. Pure
 * display — repopulates from the submitted values it is handed, nothing more.
 */
final class SimulationFormView
{
    /** Friendly French labels for the equipment slots (emplacement keys). */
    private const SLOT_LABELS = [
        'main2' => 'Main 2',
        'deuxmains' => 'Deux mains',
        'doigt' => 'Bague',
        'tete' => 'Tête',
        'bouche' => 'Bouche',
        'cou' => 'Cou',
        'epaule' => 'Épaule',
        'cape' => 'Cape',
        'tronc' => 'Tronc',
        'taille' => 'Taille',
        'pieds' => 'Pieds',
        'munition' => 'Munition',
        'trophee' => 'Trophée',
    ];

    private OptionCatalog $catalog;
    private SimulationWeaponCatalog $weapons;

    public function __construct(?OptionCatalog $catalog = null, ?SimulationWeaponCatalog $weapons = null)
    {
        $this->catalog = $catalog ?? new OptionCatalog();
        $this->weapons = $weapons ?? new SimulationWeaponCatalog();
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

        $body = '<div class="sim-sides">'
            . $this->sidePanel('Acteur', SimulationField::SIDE_ACTOR, $bySide[SimulationField::SIDE_ACTOR], $posted)
            . $this->sidePanel('Cible', SimulationField::SIDE_TARGET, $bySide[SimulationField::SIDE_TARGET], $posted)
            . '</div>'
            . $this->context($bySide[SimulationField::SIDE_SHARED], $posted);

        return '<h1>Simuler : ' . $this->esc($action->getDisplayName()) . '</h1>'
            . '<p class="text-muted">Simulation via le moteur réel : conditions, jets, dégâts, messages et logs sont ceux du jeu.</p>'
            . '<form method="post" class="card sim-form">'
            . '<input type="hidden" name="id" value="' . $id . '">'
            . '<div class="card-header"><h3 class="card-title">État hypothétique</h3></div>'
            . '<div class="card-body sim-body">' . $body . '</div></form>';
    }

    /**
     * Environment toggles that exercise the preconditions: the Enfers plane (the
     * global Plan gate) and an anti-Berserk window (the NoBerserk gate on compute
     * conditions).
     *
     * @param array<string, mixed> $posted
     */
    private function environment(array $posted): string
    {
        $enfers = !empty($posted['enfers']) ? ' checked' : '';
        $berserk = !empty($posted['actor_berserk']) ? ' checked' : '';

        return '<div class="form-group"><label>Environnement</label>'
            . '<div class="sim-env">'
            . '<label class="sim-check"><input type="checkbox" name="enfers" value="1"' . $enfers . '> Aux Enfers</label>'
            . '<label class="sim-check"><input type="checkbox" name="actor_berserk" value="1"' . $berserk . '> Acteur berserk</label>'
            . '</div></div>';
    }

    /**
     * One fighter's panel: caracs, then equipment (weapon + every slot), effects
     * and passives — each in its own labelled sub-section.
     *
     * @param list<SimulationField> $fields
     * @param array<string, mixed>  $posted
     */
    private function sidePanel(string $title, string $side, array $fields, array $posted): string
    {
        $caracs = '';
        foreach ($fields as $field) {
            if ($field->kind === SimulationField::KIND_WEAPON) {
                continue; // the weapon is rendered inside the equipment block
            }
            $caracs .= $this->fieldControl($field, $posted);
        }

        $limit = defined('ITEM_LIMIT') ? ITEM_LIMIT : 3;

        return '<fieldset class="sim-panel"><legend>' . $this->esc($title) . '</legend>'
            . $this->sub('Caractéristiques', '<div class="sim-grid">' . $caracs . '</div>')
            . $this->sub('Équipement', $this->equipment($side, $posted), 'max ' . $limit . ' + 1 bague, 1 munition, 1 trophée')
            . $this->sub('Effets', $this->effects($side, $posted))
            . $this->sub('Passifs', $this->passives($side . '_passives', $posted))
            . '</fieldset>';
    }

    private function sub(string $title, string $content, string $hint = ''): string
    {
        $head = '<div class="sim-sub-h">' . $this->esc($title)
            . ($hint !== '' ? ' <small>' . $this->esc($hint) . '</small>' : '') . '</div>';

        return '<div class="sim-sub">' . $head . $content . '</div>';
    }

    /**
     * The shared context bar: distance + environment toggles + run count + submit.
     *
     * @param list<SimulationField> $sharedFields
     * @param array<string, mixed>  $posted
     */
    private function context(array $sharedFields, array $posted): string
    {
        $shared = '';
        foreach ($sharedFields as $field) {
            $shared .= $this->fieldControl($field, $posted);
        }

        return '<div class="sim-context">'
            . $shared
            . $this->environment($posted)
            . '<div class="form-group"><label>Tirages</label>'
            . '<input class="form-control" type="number" min="1" max="5000" name="runs" value="' . $this->esc($posted['runs'] ?? 1) . '"></div>'
            . '<button class="btn btn-primary" type="submit">Simuler</button>'
            . '</div>';
    }

    /**
     * A picker per non-main-hand slot (helmet, ring, armour, shield, …) so this
     * side can be equipped with real defense items; their stats fold into caracs
     * and feed the conditions. Shown for both fighters.
     *
     * @param array<string, mixed> $posted
     */
    private function equipment(string $side, array $posted): string
    {
        $weaponField = $side . '_weapon';
        $html = '<div class="sim-grid">'
            . $this->group('Arme (main1)', $this->weaponSelect($weaponField, (string) ($posted[$weaponField] ?? '')));

        $selected = (array) ($posted[$side . '_equipment'] ?? []);
        foreach ($this->weapons->equipmentSlots() as $slot => $items) {
            $name = $side . '_equipment[' . $slot . ']';
            $current = (string) ($selected[$slot] ?? '');
            $options = '<option value="">—</option>';
            foreach ($items as $value => $label) {
                $options .= '<option value="' . $this->esc($value) . '"' . ((string) $value === $current ? ' selected' : '') . '>' . $this->esc($label) . '</option>';
            }
            $label = self::SLOT_LABELS[$slot] ?? ucfirst($slot);
            $html .= $this->group($label, '<select class="form-control" name="' . $this->esc($name) . '">' . $options . '</select>');
        }

        return $html . '</div>';
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
            return $this->group($field->label, '<input class="form-control" type="number" min="0" name="distance" value="' . $this->esc($posted['distance'] ?? 1) . '">');
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
    private function effects(string $side, array $posted): string
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

        return '<div id="' . $this->esc($side) . '-effects">' . $rows . '</div>'
            . '<button type="button" class="btn btn-sm btn-outline-secondary" onclick="addEffectRow(\'' . $this->esc($side) . '\')">+ ajouter un effet</button>';
    }

    private function effectRow(string $side, string $selected = '', int|string $value = ''): string
    {
        return '<div class="effect-row">'
            . $this->select($side . '_effect_name', ['' => '—'] + $this->catalog->effects(), $selected, brackets: true)
            . '<input class="form-control sim-effect-value" type="number" name="' . $this->esc($side) . '_effect_value[]" value="' . $this->esc($value) . '" placeholder="val">'
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
     * Real weapons grouped by subtype (the subtype as the optgroup label, so the
     * type still guides the choice) — value is the weapon name.
     */
    private function weaponSelect(string $name, string $selected): string
    {
        $html = '<select class="form-control" name="' . $this->esc($name) . '">'
            . '<option value=""' . ($selected === '' ? ' selected' : '') . '>— (mains nues)</option>';
        foreach ($this->weapons->groupedBySubtype() as $subtype => $weapons) {
            $html .= '<optgroup label="' . $this->esc($subtype) . '">';
            foreach ($weapons as $value => $label) {
                $isSelected = ((string) $value === $selected) ? ' selected' : '';
                $html .= '<option value="' . $this->esc($value) . '"' . $isSelected . '>' . $this->esc($label) . '</option>';
            }
            $html .= '</optgroup>';
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

    private function esc(int|string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
