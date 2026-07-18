<?php
/**
 * Effect catalog management (admin dashboard → Effets).
 *
 * Two views, routed on ?action:
 *   - list (default): the whole catalog with icon, flags and modifiers.
 *   - edit / new    : one form for everything an effect defines — identity
 *                     (label, description, icône), comportement (caché,
 *                     buff/debuff de carac, contrôle élémentaire), corruption
 *                     (chance de casse + matériaux), marqueur de carte.
 *
 * Effects were migrated from the EFFECTS_* / ELE_* / ITEM_CORRUPT* constants
 * to the DB (Version20260719120000_EffectsFromConstants); this page is the
 * editing surface that replaces hand-editing config/constants.php. Delete is
 * guarded: refused while any character still carries the effect
 * (players_effects rows).
 *
 * All mutations POST to effects-save.php (CSRF-validated, PRG). This page only
 * renders. Access enforced by layout.php via AdminMenuAccessService.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Entity\Effect;
use App\Service\Action\RpgAwesomeIcons;
use App\Service\CsrfProtectionService;
use App\Service\EffectService;

function effect_flag_badges(Effect $effect): string
{
    $badges = [];
    if ($effect->isHidden()) {
        $badges[] = '<span class="badge badge-secondary" title="Posture éphémère : purgée au nouveau tour ou à l\'usage, jamais listée sur les fiches">Caché</span>';
    }
    if ($effect->isMapMarker()) {
        $badges[] = '<span class="badge badge-light" title="Marqueur de carte (traces de pas…) : transite par players_effects mais n\'est pas un effet de gameplay">Marqueur</span>';
    }
    if ($effect->getCorruptionBreakChance() !== null) {
        $badges[] = '<span class="badge badge-warning" title="Corruption : augmente la chance de casse du matériel fait de ses matériaux">Corruption</span>';
    }

    return implode(' ', $badges);
}

/** Colonne « Modificateurs » : ±carac et contrôle élémentaire, compacts. */
function effect_modifiers(Effect $effect): string
{
    $parts = [];
    if ($effect->getBuffCarac() !== null) {
        $parts[] = '<span class="text-success">+1 ' . e(strtoupper($effect->getBuffCarac())) . '</span>';
    }
    if ($effect->getDebuffCarac() !== null) {
        $parts[] = '<span class="text-danger">−1 ' . e(strtoupper($effect->getDebuffCarac())) . '</span>';
    }
    if ($effect->getControlNames() !== []) {
        $parts[] = 'annule <code>' . implode('</code>, <code>', array_map('e', $effect->getControlNames())) . '</code>';
    }

    return $parts === [] ? '<span class="text-muted">—</span>' : implode(' · ', $parts);
}

/**
 * @param Effect[] $effects
 */
function effect_render_list(array $effects): string
{
    $carriersByEffect = (new EffectService())->countCarriersByEffectName();

    $rows = '';
    foreach ($effects as $effect) {
        $carriers = $carriersByEffect[$effect->getName()] ?? 0;
        $rows .= '<tr>'
            . '<td><span class="ra ' . e($effect->getIcon()) . '" title="' . e($effect->getIcon()) . '"></span></td>'
            . '<td><code>' . e($effect->getName()) . '</code></td>'
            . '<td>' . e($effect->getLabel()) . '</td>'
            . '<td>' . effect_flag_badges($effect) . '</td>'
            . '<td>' . effect_modifiers($effect) . '</td>'
            . '<td>' . ($carriers > 0 ? '<strong>' . $carriers . '</strong>' : '<span class="text-muted">—</span>') . '</td>'
            . '<td><a class="btn btn-sm btn-outline-primary" href="/admin/effects.php?action=edit&amp;name='
            . e(urlencode($effect->getName())) . '">Éditer</a></td>'
            . '</tr>';
    }

    return '<div class="d-flex justify-content-between align-items-center mb-3">'
        . '<h1 class="mb-0">Effets</h1>'
        . '<a class="btn btn-primary" href="/admin/effects.php?action=new">+ Nouvel effet</a>'
        . '</div>'
        . '<table class="table table-striped table-sm" data-admin-list data-page-size="30"><thead><tr>'
        . '<th></th><th>Code</th><th>Nom</th><th>Statut</th><th>Modificateurs</th>'
        . '<th title="Personnages portant actuellement cet effet">Porteurs</th><th></th>'
        . '</tr></thead><tbody>' . $rows . '</tbody></table>';
}

/** <select> d'une carac (buff/debuff), « — aucune — » compris. */
function effect_carac_select(string $fieldName, ?string $current): string
{
    $options = [];
    foreach (CARACS as $key => $short) {
        $options[$key] = $short . ' — ' . (CARACS_TXT[$key] ?? $short);
    }

    return formSelect($fieldName, $options, $current, '— aucune —');
}

function effect_render_form(?Effect $effect, string $csrfToken): string
{
    $isEdit = $effect !== null;
    $action = $isEdit ? 'update' : 'create';
    $title = $isEdit
        ? 'Effet : ' . e($effect->getLabel()) . ' <span class="text-muted">(' . e($effect->getName()) . ')</span>'
        : 'Nouvel effet';

    $nameField = $isEdit
        ? '<input type="hidden" name="name" value="' . e($effect->getName()) . '">'
            . '<input type="text" class="form-control" value="' . e($effect->getName()) . '" disabled>'
            . '<small class="form-text text-muted">Le code est référencé par players_effects et les paramètres d\'actions — non modifiable.</small>'
        : '<input type="text" class="form-control" name="name" required pattern="[a-z][a-z0-9_]*"'
            . ' placeholder="ex: gel">'
            . '<small class="form-text text-muted">Minuscules / chiffres / _ — stocké dans players_effects.name.</small>';

    // Annulations : tout le catalogue de gameplay sauf soi-même —
    // multi-sélection, un effet peut en annuler plusieurs.
    $service = new EffectService();
    $currentControls = $isEdit ? $effect->getControlNames() : [];
    $controlOptions = '';
    foreach ($service->getGameplayEffectNames() as $name) {
        if (!$isEdit || $name !== $effect->getName()) {
            $controlOptions .= '<option value="' . e($name) . '"'
                . (in_array($name, $currentControls, true) ? ' selected' : '') . '>' . e($name) . '</option>';
        }
    }

    $iconCatalog = renderDatalist('effect-icon-catalog', array_combine(
        (new RpgAwesomeIcons())->all(),
        (new RpgAwesomeIcons())->all()
    ) ?: []);

    $breakChance = $isEdit ? $effect->getCorruptionBreakChance() : null;
    $materials = $isEdit ? implode("\n", $effect->getCorruptionMaterialNames()) : '';

    return '<div class="d-flex justify-content-between align-items-center mb-3">'
        . '<h1 class="mb-0">' . $title . '</h1>'
        . '<a class="btn btn-sm btn-outline-secondary" href="/admin/effects.php">← Retour à la liste</a></div>'

        . '<form method="post" action="/admin/effects-save.php?action=' . $action . '">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'

        . '<div class="card mb-3"><div class="card-header">Identité</div><div class="card-body"><div class="row">'
        . '<div class="form-group col-md-3"><label>Code</label>' . $nameField . '</div>'
        . '<div class="form-group col-md-3"><label>Nom affiché</label>'
        . '<input type="text" class="form-control" name="label" required value="'
        . e($isEdit ? $effect->getLabel() : '') . '"></div>'
        . '<div class="form-group col-md-3"><label>Icône</label>'
        . '<div class="input-group">'
        . '<div class="input-group-prepend"><span class="input-group-text"><span class="ra '
        . e($isEdit ? $effect->getIcon() : EffectService::FALLBACK_ICON) . '"></span></span></div>'
        . '<input type="text" class="form-control" name="icon" list="effect-icon-catalog" required'
        . ' pattern="ra-[a-z0-9-]+" value="' . e($isEdit ? $effect->getIcon() : '') . '" placeholder="ra-…">'
        . '</div>'
        . '<small class="form-text text-muted">Classe RPG-Awesome, affichée sur les fiches et le HUD.</small></div>'
        . '<div class="form-group col-md-3"><label>Flags</label><div>'
        . '<label class="mr-3"><input type="checkbox" name="hidden" ' . checked($isEdit && $effect->isHidden())
        . ' title="Posture éphémère (parade, leurre…) : purgée au nouveau tour ou à l\'usage, jamais listée sur les fiches"> Caché</label>'
        . '<label><input type="checkbox" name="is_map_marker" ' . checked($isEdit && $effect->isMapMarker())
        . ' title="Marqueur de carte (traces de pas…) : exclu des listes de gameplay (workbench, saignement)"> Marqueur de carte</label>'
        . '</div></div>'
        . '<div class="form-group col-12"><label>Description</label>'
        . '<textarea class="form-control" name="description" rows="3">'
        . e($isEdit ? $effect->getDescription() : '') . '</textarea>'
        . '<small class="form-text text-muted">Texte de règles (wiki des effets).</small></div>'
        . '</div></div></div>'

        . '<div class="card mb-3"><div class="card-header">Comportement</div><div class="card-body"><div class="row">'
        . '<div class="form-group col-md-4"><label>Carac augmentée (+1)</label>'
        . effect_carac_select('buff_carac', $isEdit ? $effect->getBuffCarac() : null)
        . '</div>'
        . '<div class="form-group col-md-4"><label>Carac diminuée (−1)</label>'
        . effect_carac_select('debuff_carac', $isEdit ? $effect->getDebuffCarac() : null)
        . '<small class="form-text text-muted">Appliquée tant que l\'effet dure (la valeur portée sert de multiplicateur).</small></div>'
        . '<div class="form-group col-md-4"><label>Annule les effets</label>'
        . '<select name="controls[]" class="form-control" multiple size="6">' . $controlOptions . '</select>'
        . '<small class="form-text text-muted">Poser cet effet retire chaque effet coché (eau éteint feu…) ;'
        . ' les deux tombent si la cible porte déjà un effet qui annule celui-ci.'
        . ' Ctrl+clic pour en choisir plusieurs.</small></div>'
        . '</div></div></div>'

        . '<div class="card mb-3"><div class="card-header">Corruption</div><div class="card-body"><div class="row">'
        . '<div class="form-group col-md-4"><label>Chance de casse supplémentaire (%)</label>'
        . '<input type="number" class="form-control" name="corruption_break_chance" min="0" max="100" value="'
        . e($breakChance === null ? '' : (string) $breakChance) . '">'
        . '<small class="form-text text-muted">Vide = pas une corruption. S\'applique au matériel'
        . ' fabriqué avec les matériaux ci-contre quand le porteur attaque ou défend.</small></div>'
        . '<div class="form-group col-md-8"><label>Matériaux corruptibles (un par ligne)</label>'
        . '<textarea class="form-control" name="corruption_materials" rows="4" spellcheck="false">'
        . e($materials) . '</textarea>'
        . '<small class="form-text text-muted">Noms d\'objets-matériaux (bronze, cuir…) — un matériau corrompu'
        . ' n\'est pas restitué quand l\'objet casse.</small></div>'
        . '</div></div></div>'

        . '<button type="submit" class="btn btn-primary">' . ($isEdit ? 'Enregistrer' : 'Créer l\'effet') . '</button>'
        . '</form>'
        . ($isEdit ? effect_render_delete_zone($effect, $csrfToken) : '')
        . $iconCatalog;
}

/**
 * Zone de suppression du formulaire d'édition. Le garde-fou côté serveur
 * (EffectService::deleteEffect) refuse tant que des personnages portent
 * l'effet ; ici on adapte juste l'UI : bouton actif + confirmation, ou
 * explication.
 */
function effect_render_delete_zone(Effect $effect, string $csrfToken): string
{
    $carriers = (new EffectService())->countPlayersUsingEffect($effect->getName());

    $body = $carriers > 0
        ? '<p class="mb-0 text-muted">Suppression impossible : cet effet est encore porté par '
            . $carriers . ' personnage' . ($carriers > 1 ? 's' : '') . '. Attendez son expiration ou retirez-le d\'abord.</p>'
        : '<form method="post" action="/admin/effects-save.php?action=delete" class="d-flex align-items-center gap-3"'
            . ' onsubmit="return confirm(\'Supprimer définitivement l\\\'effet « '
            . e($effect->getName()) . ' » ?\');">'
            . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
            . '<input type="hidden" name="name" value="' . e($effect->getName()) . '">'
            . '<button type="submit" class="btn btn-outline-danger">Supprimer l\'effet</button>'
            . '<small class="text-muted">Aucun personnage ne porte cet effet. Les actions qui le référencent'
            . ' afficheront « ⚠ inconnue » au workbench.</small>'
            . '</form>';

    return '<div class="card mt-4 border-danger"><div class="card-header text-danger">Zone dangereuse</div>'
        . '<div class="card-body">' . $body . '</div></div>';
}

/* -------------------------------------------------------------------------
 * Route
 * ---------------------------------------------------------------------- */
$csrfToken = (new CsrfProtectionService())->generateToken();
$service = new EffectService();

$action = $_GET['action'] ?? 'list';

if ($action === 'new') {
    $content = effect_render_form(null, $csrfToken);
} elseif ($action === 'edit') {
    $effect = $service->getEffectByName((string) ($_GET['name'] ?? ''));
    if ($effect === null) {
        setFlash('warning', 'Effet introuvable.');
        redirectTo('/admin/effects.php');
    }
    $content = effect_render_form($effect, $csrfToken);
} else {
    $content = effect_render_list($service->getAllEffects());
}

echo admin_layout('Effets', renderFlashMessage() . $content, [
    'styles' => ['/css/rpg-awesome.min.css'],
]);
