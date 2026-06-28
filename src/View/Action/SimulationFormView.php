<?php

namespace App\View\Action;

use App\Action\Schema\OptionCatalog;
use App\Action\Schema\SimulationField;
use App\Entity\Action;
use App\Service\Action\ActionTargeting;
use App\Service\Action\EnergieRule;
use App\Service\Action\SimulationInputMapper;
use App\Service\Action\SimulationWeaponCatalog;

/**
 * Renders the simulate panel's "hypothetical state" form: the action-derived
 * fields plus effect/passive pickers sourced from the OptionCatalog. Pure
 * display — repopulates from the submitted values it is handed, nothing more.
 */
final class SimulationFormView
{
    use EscapesHtml;

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
     * @param ActionTargeting::* $scope
     *        who the action targets — a self-only action disables the Cible panel.
     *        The Acteur panel is always enabled (the actor performs the action).
     */
    public function render(Action $action, array $fields, array $posted, string $scope = ActionTargeting::BOTH): string
    {
        $id = (int) $action->getId();

        $bySide = [SimulationField::SIDE_SHARED => [], SimulationField::SIDE_ACTOR => [], SimulationField::SIDE_TARGET => []];
        foreach ($fields as $field) {
            $bySide[$field->side][] = $field;
        }

        // A self-only action has no second fighter: keep the Cible panel for a
        // symmetric layout but disable it so no target state can be entered, and
        // default the distance to 0 (the fighters share a tile) — one less click.
        // Exception: a self-scoped action can still READ target state — e.g. a
        // heal/buff whose effect computes from the target's caracs (Régénération
        // soigne depuis la R de la cible). When the action declares any
        // target-side field, the Cible panel must stay editable, or that value is
        // silently stuck at its default and the simulation is wrong.
        $hasTargetFields = $bySide[SimulationField::SIDE_TARGET] !== [];
        $targetDisabled = $scope === ActionTargeting::SELF && !$hasTargetFields;
        $defaultDistance = $targetDisabled ? 0 : 1;

        $body = '<div class="sim-sides">'
            . $this->sidePanel('Acteur', SimulationField::SIDE_ACTOR, $bySide[SimulationField::SIDE_ACTOR], $posted)
            . $this->sidePanel('Cible', SimulationField::SIDE_TARGET, $bySide[SimulationField::SIDE_TARGET], $posted, $targetDisabled)
            . '</div>'
            . $this->context($bySide[SimulationField::SIDE_SHARED], $posted, $defaultDistance);

        return '<h1>Simuler : ' . $this->esc($action->getDisplayName()) . '</h1>'
            . '<p class="text-muted">Simulation via le moteur réel : conditions, jets, dégâts, messages et logs sont ceux du jeu.</p>'
            . '<form method="post" class="card sim-form">'
            . '<input type="hidden" name="id" value="' . $id . '">'
            . '<div class="card-body sim-body">' . $body . '</div></form>';
    }

    /**
     * The Environnement section: the distance (always shown — it positions the
     * two fighters, so it matters to every action) plus the toggles that exercise
     * the preconditions and tile-dependent effects — the Enfers plane (the global
     * Plan gate), an anti-Berserk window (the NoBerserk gate on compute
     * conditions), and the actor's tile (e.g. a road, read by the run bonus).
     * The tile toggle is shown for every action: it's a world property, and an
     * action that doesn't read it simply ignores it.
     *
     * @param string               $extra any non-distance shared fields (rare)
     * @param array<string, mixed> $posted
     * @param int                  $defaultDistance distance when none was posted
     *        (0 for a self action — the fighters share a tile)
     */
    private function environment(string $extra, array $posted, int $defaultDistance = 1): string
    {
        $distance = (int) ($posted['distance'] ?? $defaultDistance);
        $enfers = !empty($posted['enfers']) ? ' checked' : '';
        $berserk = !empty($posted['actor_berserk']) ? ' checked' : '';
        $route = !empty($posted['tile']['routes']) ? ' checked' : '';

        return $this->sub('Environnement', '<div class="sim-env">'
            . $this->group('Distance (cases)', '<input class="form-control" type="number" min="0" name="distance" value="' . $distance . '">')
            . $extra
            . '<label class="sim-check"><input type="checkbox" name="tile[routes]" value="1"' . $route . '> Sur une route</label>'
            . '<label class="sim-check"><input type="checkbox" name="enfers" value="1"' . $enfers . '> Aux Enfers</label>'
            . '<label class="sim-check"><input type="checkbox" name="actor_berserk" value="1"' . $berserk . '> Acteur berserk</label>'
            . '</div>');
    }

    /**
     * One fighter's panel: caracs, then equipment (weapon + every slot), effects
     * and passives — each in its own labelled sub-section. When $disabled, the
     * sub-sections are wrapped in an inert, display:contents <div> with a note —
     * non-interactive without breaking the panel's subgrid. (A <fieldset> can't
     * be used: Chromium ignores display:contents on it, collapsing the subgrid.)
     *
     * @param list<SimulationField> $fields
     * @param array<string, mixed>  $posted
     */
    private function sidePanel(string $title, string $side, array $fields, array $posted, bool $disabled = false): string
    {
        // Rank and energie are always shown: rank shifts the XP reward (actor rank
        // − target rank), and energie adds XP bonuses — both per fighter, so their
        // influence on XP is visible. Energie defaults to the real max for this
        // fighter's action points (ENERGIE_CST − a), not a flat value.
        $rank = (int) ($posted[$side . '_rank'] ?? 1);
        $actionPoints = (int) ($posted[$side . '_remaining']['a'] ?? EnergieRule::DEFAULT_ACTION_POINTS);
        $energie = (int) ($posted[$side . '_energie'] ?? EnergieRule::maxFor($actionPoints));
        $caracs = $this->group('Rang', '<input class="form-control" type="number" min="1" name="' . $side . '_rank" value="' . $rank . '">')
            . $this->group('Énergie', '<input class="form-control" type="number" min="0" name="' . $side . '_energie" value="' . $energie . '">');
        foreach ($fields as $field) {
            if ($field->kind === SimulationField::KIND_WEAPON) {
                continue; // the weapon is rendered inside the equipment block
            }
            $caracs .= $this->fieldControl($field, $posted);
        }

        $limit = defined('ITEM_LIMIT') ? ITEM_LIMIT : 3;

        $head = '<div class="sim-panel-h">' . $this->esc($title)
            . ($disabled ? ' <span class="sim-panel-note">action sur soi — pas de cible</span>' : '') . '</div>';

        $sections = $this->sub('Caractéristiques', '<div class="sim-grid">' . $caracs . '</div>')
            . $this->sub('Équipement', $this->equipment($side, $posted), 'max ' . $limit . ' + 1 bague, 1 munition, 1 trophée')
            . $this->sub('Effets', $this->effects($side, $posted))
            . $this->sub('Passifs', $this->passives($side . '_passives', $posted));

        if ($disabled) {
            $sections = '<div class="sim-panel-fieldset" inert>' . $sections . '</div>';
        }

        return '<section class="sim-panel' . ($disabled ? ' sim-panel--disabled' : '') . '">' . $head . $sections . '</section>';
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
    private function context(array $sharedFields, array $posted, int $defaultDistance = 1): string
    {
        // Distance is rendered unconditionally by environment(); drop any
        // duplicate a condition declared, keep the (rare) other shared fields.
        $extra = '';
        foreach ($sharedFields as $field) {
            if ($field->kind !== SimulationField::KIND_DISTANCE) {
                $extra .= $this->fieldControl($field, $posted);
            }
        }

        $maxRuns = SimulationInputMapper::MAX_RUNS;
        $runs = max(1, min($maxRuns, (int) ($posted['runs'] ?? 1)));

        return '<div class="sim-context">'
            . $this->environment($extra, $posted, $defaultDistance)
            . '<div class="sim-run">'
            . '<div class="form-group"><label>Tirages</label>'
            . '<input class="form-control" type="number" min="1" max="' . $maxRuns . '" name="runs" value="' . $runs . '"></div>'
            . '<button class="btn btn-primary" type="submit">Simuler</button>'
            . '</div></div>';
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
        // Action points ('a') commonly sit at 3 (6 is rare); other remaining
        // resources (pm/mvt) keep a higher default so a real cost is affordable.
        if ($field->kind === SimulationField::KIND_REMAINING) {
            $default = $field->key === 'a' ? EnergieRule::DEFAULT_ACTION_POINTS : 6;
        } else {
            $default = 10;
        }
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
     * type still guides the choice) — value is the weapon name. The default
     * (empty value) is the bare-handed "Poing": a real player is never truly
     * empty-handed, so the engine equips the Poing fist when main1 is unset
     * (see ActionSimulationService::emplacements / Player's fist fallback).
     */
    private function weaponSelect(string $name, string $selected): string
    {
        $html = '<select class="form-control" name="' . $this->esc($name) . '">'
            . '<option value=""' . ($selected === '' ? ' selected' : '') . '>Poing (mains nues)</option>';
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

}
