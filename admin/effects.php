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

/** Colonne « Modificateurs » : caracs, combat, tour et annulations, compacts. */
function effect_modifiers(Effect $effect): string
{
    $parts = [];
    if ($effect->getBuffCarac() !== null) {
        $parts[] = '<span class="text-success">+1 ' . e(strtoupper($effect->getBuffCarac())) . '</span>';
    }
    if ($effect->getDebuffCarac() !== null) {
        $parts[] = '<span class="text-danger">−1 ' . e(strtoupper($effect->getDebuffCarac())) . '</span>';
    }

    foreach ([
        'getRollAttackMod' => 'jet att.', 'getRollDefenseMod' => 'jet déf.',
        'getDamageDealtMod' => 'dégâts', 'getDamageTakenMod' => 'dégâts subis',
        'getPushAttackMod' => 'poussée', 'getPushDefenseMod' => 'poussée déf.',
    ] as $getter => $short) {
        $mod = $effect->{$getter}();
        if ($mod !== 0) {
            $parts[] = '<span class="' . ($mod > 0 ? 'text-success' : 'text-danger') . '">'
                . ($mod > 0 ? '+' : '−') . 'valeur ' . e($short) . '</span>';
        }
    }
    if ($effect->getDamageTakenFactor() != 1.0) {
        $parts[] = '×' . e((string) $effect->getDamageTakenFactor()) . ' dégâts subis';
    }
    if ($effect->getBlockRecovery() !== '') {
        $parts[] = 'bloque récup ' . e(strtoupper($effect->getBlockRecovery()));
    }
    if ($effect->isTurnRegen()) {
        $parts[] = 'régén +RM';
    }
    if ($effect->isTurnMvtMalus()) {
        $parts[] = '−valeur Mvt au tour';
    }
    if ($effect->getDodgeScope() !== '') {
        $scopes = ['any' => 'tout', 'physical' => 'physique', 'spell' => 'sorts'];
        $parts[] = 'posture (' . ($scopes[$effect->getDodgeScope()] ?? $effect->getDodgeScope()) . ')';
    }
    if ($effect->grantsFlight()) {
        $parts[] = 'vol';
    }
    if ($effect->isCostMultiplier()) {
        $parts[] = '× coûts';
    }
    if ($effect->blocksTrading()) {
        $parts[] = 'bloque échanges';
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

    $rows = [];
    foreach ($effects as $effect) {
        $carriers = $carriersByEffect[$effect->getName()] ?? 0;
        $rows[] = '<tr>'
            . '<td><span class="ra ' . e($effect->getIcon()) . '" title="' . e($effect->getIcon()) . '"></span></td>'
            . '<td><code>' . e($effect->getName()) . '</code></td>'
            . '<td>' . e($effect->getLabel()) . '</td>'
            . '<td>' . effect_flag_badges($effect) . '</td>'
            . '<td>' . effect_modifiers($effect) . '</td>'
            . '<td>' . ($carriers > 0 ? '<strong>' . $carriers . '</strong>' : '<span class="text-muted">—</span>') . '</td>'
            . '<td><a class="btn btn-sm btn-outline-primary" href="/admin/effects.php?action=edit&amp;name='
            . e(urlencode($effect->getName())) . '">Éditer</a> '
            . '<a class="btn btn-sm btn-outline-secondary" title="Exporter cet effet (bundle JSON)"'
            . ' href="/admin/action-export.php?type=effect&amp;id=' . (int) $effect->getId() . '">JSON</a></td>'
            . '</tr>';
    }

    return '<div class="d-flex justify-content-between align-items-center mb-3">'
        . '<h1 class="mb-0">Effets</h1>'
        . '<div class="d-flex gap-2">'
        . '<a class="btn btn-outline-secondary" href="/admin/action-export.php?type=effect"'
        . ' title="Télécharger tout le catalogue en bundle JSON, ré-importable ici ou sur un autre environnement">'
        . '<i class="fas fa-download"></i> Exporter (JSON)</a>'
        . '<a class="btn btn-outline-secondary" href="/admin/action-import.php"'
        . ' title="Importer un bundle JSON (avec prévisualisation avant application)">'
        . '<i class="fas fa-upload"></i> Importer</a>'
        . '<a class="btn btn-outline-secondary" href="/admin/wiki.php?type=effect"'
        . ' title="Markup DokuWiki de la page regles:effets, généré depuis le catalogue">'
        . '<i class="fas fa-book"></i> Wiki</a>'
        . '<a class="btn btn-primary" href="/admin/effects.php?action=new">+ Nouvel effet</a>'
        . '</div></div>'
        . renderTable(
            ['', 'Code', 'Nom', 'Statut', 'Modificateurs',
             ['Porteurs', 'title="Personnages portant actuellement cet effet"'], ''],
            $rows,
            'class="table table-striped table-sm" data-admin-list data-page-size="30"'
        );
}

/* Le wiki des effets vit désormais au registre commun :
 * App\Service\Wiki\EffectWikiRenderer (admin → Wiki, onglet Effets). */

/**
 * <select> d'un modificateur de combat (−1 / 0 / +1) : la contribution
 * est multipliée par la valeur portée de l'effet.
 */
function effect_mod_select(string $fieldName, string $label, int $current, string $hint = ''): string
{
    return '<div class="form-group col-md-2 col-4"><label title="' . e($hint) . '">' . e($label) . '</label>'
        . formSelect($fieldName, ['-1' => '−1 × valeur', '0' => '—', '1' => '+1 × valeur'], (string) $current)
        . '</div>';
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
        ? formField('Code',
            '<input type="hidden" name="name" value="' . e($effect->getName()) . '">'
            . formInput('', $effect->getName(), 'disabled'),
            'form-group col-md-3',
            'Le code est référencé par players_effects et les paramètres d\'actions — non modifiable.')
        : formField('Code',
            formInput('name', '', 'required pattern="[a-z][a-z0-9_]*" placeholder="ex: gel"'),
            'form-group col-md-3',
            'Minuscules / chiffres / _ — stocké dans players_effects.name.');

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

    $identite = '<div class="row">'
        . $nameField
        . formField('Nom affiché', formInput('label', $isEdit ? $effect->getLabel() : '', 'required'), 'form-group col-md-3')
        . formField('Icône',
            '<div class="input-group">'
            . '<div class="input-group-prepend"><span class="input-group-text"><span class="ra '
            . e($isEdit ? $effect->getIcon() : EffectService::FALLBACK_ICON) . '"></span></span></div>'
            . formInput('icon', $isEdit ? $effect->getIcon() : '',
                'list="effect-icon-catalog" required pattern="ra-[a-z0-9-]+" placeholder="ra-…"')
            . '</div>',
            'form-group col-md-3',
            'Classe RPG-Awesome, affichée sur les fiches et le HUD.')
        . formField('Flags',
            '<div>'
            . formCheckbox('hidden', $isEdit && $effect->isHidden(), 'Caché',
                'class="mr-3" title="Posture éphémère (parade, leurre…) : purgée au nouveau tour ou à l\'usage, jamais listée sur les fiches"')
            . formCheckbox('is_map_marker', $isEdit && $effect->isMapMarker(), 'Marqueur de carte',
                'class="mr-3" title="Marqueur de carte (traces de pas…) : exclu des listes de gameplay (workbench, saignement)"')
            . formCheckbox('buildable_over', $isEdit && $effect->isBuildableOver(), 'Constructible par-dessus',
                'title="Posé au sol comme élément : n\'empêche ni construction ni aménagement de la case (sang, boue, traces) — décoché, la case est bloquée (feu, lave, ronce…)"')
            . '</div>',
            'form-group col-md-3')
        . formField('Description', formTextarea('description', $isEdit ? $effect->getDescription() : ''),
            'form-group col-12', 'Texte de règles (wiki des effets).')
        . '</div>';

    $comportement = '<div class="row">'
        . formField('Carac augmentée (+1)', effect_carac_select('buff_carac', $isEdit ? $effect->getBuffCarac() : null),
            'form-group col-md-4')
        . formField('Carac diminuée (−1)', effect_carac_select('debuff_carac', $isEdit ? $effect->getDebuffCarac() : null),
            'form-group col-md-4', 'Appliquée tant que l\'effet dure (la valeur portée sert de multiplicateur).')
        . formField('Annule les effets',
            '<select name="controls[]" class="form-control" multiple size="6">' . $controlOptions . '</select>',
            'form-group col-md-4',
            'Poser cet effet retire chaque effet coché (eau éteint feu…) ; les deux tombent si la cible'
            . ' porte déjà un effet qui annule celui-ci. Ctrl+clic pour en choisir plusieurs.')
        . '</div>';

    $combat = '<div class="row">'
        . effect_mod_select('roll_attack_mod', 'Jet d\'attaque', $isEdit ? $effect->getRollAttackMod() : 0, 'Ex-dextérité (+1) / maladresse (−1)')
        . effect_mod_select('roll_defense_mod', 'Jet de défense', $isEdit ? $effect->getRollDefenseMod() : 0, 'Ex-protection (+1) / vulnérabilité (−1)')
        . effect_mod_select('damage_dealt_mod', 'Dégâts infligés', $isEdit ? $effect->getDamageDealtMod() : 0, 'Ex-agressivité (+1) / faiblesse (−1)')
        . effect_mod_select('damage_taken_mod', 'Dégâts subis', $isEdit ? $effect->getDamageTakenMod() : 0, 'Ex-fragilité (+1) / armure (−1)')
        . effect_mod_select('push_attack_mod', 'Poussée (att.)', $isEdit ? $effect->getPushAttackMod() : 0, 'Ex-renforcement (+1)')
        . effect_mod_select('push_defense_mod', 'Poussée (déf.)', $isEdit ? $effect->getPushDefenseMod() : 0, 'Ex-stabilité (+1) / instabilité (−1)')
        . '</div>'
        . '<div class="row">'
        . formField('Facteur sur les dégâts subis',
            formInput('damage_taken_factor',
                $isEdit ? rtrim(rtrim(number_format($effect->getDamageTakenFactor(), 2, '.', ''), '0'), '.') : '1',
                'type="number" step="0.05" min="0.05" max="5"'),
            'form-group col-md-3',
            '1 = neutre ; 0.75 = encaisse (les facteurs portés se multiplient, minimum 1 dégât).')
        . '</div>';

    $posture = '<div class="row">'
        . formField('Annule les attaques',
            formSelect('dodge_scope', [
                'any' => 'Toutes', 'physical' => 'Physiques (hors sorts)', 'spell' => 'Sorts',
            ], $isEdit && $effect->getDodgeScope() !== '' ? $effect->getDodgeScope() : null, '— pas une posture —'),
            'form-group col-md-2',
            'La posture est CONSOMMÉE quand elle se déclenche.')
        . formField('Arme de l\'attaquant',
            formSelect('dodge_attacker_weapon', ['melee' => 'Mêlée'],
                $isEdit && $effect->getDodgeAttackerWeapon() !== '' ? $effect->getDodgeAttackerWeapon() : null,
                '— indifférent —'),
            'form-group col-md-2')
        . formField('Arme du défenseur',
            formSelect('dodge_defender_weapon', ['melee' => 'Mêlée', 'poing' => 'Mains nues (Poing)'],
                $isEdit && $effect->getDodgeDefenderWeapon() !== '' ? $effect->getDodgeDefenderWeapon() : null,
                '— indifférent —'),
            'form-group col-md-2')
        . formField('Réaction',
            formSelect('dodge_reaction', [
                'immobilize_attacker' => 'Immobilise l\'attaquant (Mvt à zéro)',
                'step_aside' => 'Le défenseur se décale d\'une case',
                'delete_double' => 'Efface le double (carte)',
            ], $isEdit && $effect->getDodgeReaction() !== '' ? $effect->getDodgeReaction() : null, '— aucune —'),
            'form-group col-md-3')
        . formField('Message',
            formInput('dodge_message', $isEdit ? $effect->getDodgeMessage() : '',
                'placeholder="{defender} pare votre attaque !"'),
            'form-group col-md-3',
            '{attacker} et {defender} sont remplacés ; le nom et l\'icône de l\'effet sont ajoutés à la fin.')
        . '</div>';

    $auras = '<div class="row">'
        . formField('Vol', '<div>' . formCheckbox('grants_flight', $isEdit && $effect->grantsFlight(),
            'Traverse les obstacles au déplacement, ne laisse pas de traces') . '</div>', 'form-group col-md-4')
        . formField('Multiplicateur de coût', '<div>' . formCheckbox('cost_multiplier', $isEdit && $effect->isCostMultiplier(),
            'Les actions à coût « imposture » coûtent × (valeur portée + 1)') . '</div>', 'form-group col-md-4')
        . formField('Bloque marchand & écoles', '<div>' . formCheckbox('blocks_trading', $isEdit && $effect->blocksTrading(),
            'Ni marchander ni apprendre, des deux côtés (ex-adrénaline)') . '</div>', 'form-group col-md-4')
        . formField('Empilement', '<div>' . formCheckbox('stack_refresh_duration', $isEdit && $effect->isStackRefreshDuration(),
            'Re-poser un effet empilable rafraîchit aussi sa durée') . '</div>', 'form-group col-md-4')
        . '</div>';

    $tour = '<div class="row">'
        . formField('Bloque la récupération',
            formSelect('block_recovery', ['pv' => 'PV (ex-poison)', 'pm' => 'PM (ex-poison magique)'],
                $isEdit ? $effect->getBlockRecovery() : '', '— non —'),
            'form-group col-md-3',
            'La récup de la carac tombe à zéro, l\'effet expire. Prime sur la régénération.')
        . formField('Régénération',
            '<div>' . formCheckbox('turn_regen', $isEdit && $effect->isTurnRegen(),
                'La récup PV gagne +RM, l\'effet expire') . '</div>',
            'form-group col-md-3')
        . formField('Malus de mouvement',
            '<div>' . formCheckbox('turn_mvt_malus', $isEdit && $effect->isTurnMvtMalus(),
                'Retire sa valeur en Mvt au tour suivant') . '</div>',
            'form-group col-md-3')
        . '</div>';

    $corruption = '<div class="row">'
        . formField('Chance de casse supplémentaire (%)',
            formInput('corruption_break_chance', $breakChance === null ? '' : (string) $breakChance,
                'type="number" min="0" max="100"'),
            'form-group col-md-4',
            'Vide = pas une corruption. S\'applique au matériel fabriqué avec les matériaux ci-contre'
            . ' quand le porteur attaque ou défend.')
        . formField('Matériaux corruptibles (un par ligne)',
            formTextarea('corruption_materials', $materials, 4, 'spellcheck="false"'),
            'form-group col-md-8',
            'Noms d\'objets-matériaux (bronze, cuir…) — un matériau corrompu n\'est pas restitué'
            . ' quand l\'objet casse.')
        . '</div>';

    return '<div class="d-flex justify-content-between align-items-center mb-3">'
        . '<h1 class="mb-0">' . $title . '</h1>'
        . '<a class="btn btn-sm btn-outline-secondary" href="/admin/effects.php">← Retour à la liste</a></div>'

        . '<form method="post" action="/admin/effects-save.php?action=' . $action . '">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
        . formCard('Identité', $identite)
        . formCard('Comportement', $comportement)
        . formCard('Combat', $combat)
        . formCard('Défense (posture)', $posture)
        . formCard('Présence (vol, coûts, échanges)', $auras)
        . formCard('Au nouveau tour', $tour)
        . formCard('Corruption', $corruption)
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

if ($action === 'wiki') {
    // ancienne adresse : la fiche vit au registre commun désormais
    redirectTo('/admin/wiki.php?type=effect');
} elseif ($action === 'new') {
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
