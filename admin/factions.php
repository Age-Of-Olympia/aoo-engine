<?php
/**
 * Faction management (admin dashboard → Factions).
 *
 * Two views, routed on ?action:
 *   - list (default): every faction with flags, icon, respawn plan, role and
 *                     member counts.
 *   - edit / new    : one form for everything a faction defines — identity
 *                     (code, nom, lore), icône, plan de respawn, drapeaux
 *                     (cachée / secrète) et la liste ORDONNÉE des rôles avec
 *                     leurs drapeaux de permissions.
 *
 * Factions were migrated from datas/*\/factions/*.json to the DB
 * (Version20260713120000_FactionsFromJson); this page is the editing surface
 * that replaces hand-editing those files. Delete is guarded: refused while
 * any character still references the code (players.faction / secretFaction).
 *
 * ⚠ players.factionRole is a positional index into the role list: deleting
 * or reordering roles shifts what existing members point at — fix them via
 * the Membres page afterwards.
 *
 * All mutations POST to factions-save.php (CSRF-validated, PRG). This page
 * only renders. Access enforced by layout.php via AdminMenuAccessService.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Entity\Faction;
use App\Entity\FactionRole;
use App\Service\CsrfProtectionService;
use App\Service\FactionService;

/** Libellés courts des drapeaux de rôle (colonnes de l'éditeur). */
const FACTION_ROLE_FLAG_LABELS = [
    'defaultRole'  => 'Défaut',
    'showPosition' => 'Position',
    'showForum'    => 'Forum',
    'addMember'    => 'Recruter',
    'editRole'     => 'Gérer rôles',
    'kickMember'   => 'Exclure',
    'initRole'     => 'Initier',
];

function faction_flag_badge(bool $on, string $label, string $class): string
{
    return $on ? '<span class="badge badge-' . $class . '">' . e($label) . '</span> ' : '';
}

/**
 * Cellule « Membres » : membres principaux et membres secrets, distingués.
 *
 * @param array{members: int, secretMembers: int} $counts
 */
function faction_member_counts(array $counts): string
{
    if ($counts['members'] === 0 && $counts['secretMembers'] === 0) {
        return '<span class="text-muted">—</span>';
    }

    $parts = [];
    if ($counts['members'] > 0) {
        $parts[] = '<strong>' . $counts['members'] . '</strong> membre' . ($counts['members'] > 1 ? 's' : '');
    }
    if ($counts['secretMembers'] > 0) {
        $parts[] = $counts['secretMembers'] . ' secret' . ($counts['secretMembers'] > 1 ? 's' : '');
    }

    return implode(' · ', $parts);
}

/**
 * @param Faction[] $factions
 */
function faction_render_list(array $factions): string
{
    $membersByFaction = (new FactionService())->countMembersByFaction();

    $rows = '';
    foreach ($factions as $faction) {
        $counts = $membersByFaction[$faction->getCode()] ?? ['members' => 0, 'secretMembers' => 0];
        $rows .= '<tr>'
            . '<td><code>' . e($faction->getCode()) . '</code></td>'
            . '<td>' . ($faction->getRaFont() !== ''
                ? '<span style="font-size:1.4em" class="ra ' . e($faction->getRaFont()) . '"></span>'
                : '<span class="text-muted">—</span>') . '</td>'
            . '<td>' . e($faction->getName()) . '</td>'
            . '<td>' . faction_flag_badge($faction->isSecret(), 'Secrète', 'dark')
            . faction_flag_badge($faction->isHidden(), 'Cachée', 'secondary')
            . (!$faction->isSecret() && !$faction->isHidden()
                ? '<span class="badge badge-success">Publique</span>' : '') . '</td>'
            . '<td><code>' . e($faction->getRespawnPlan()) . '</code></td>'
            . '<td>' . count($faction->getRoles()) . ' rôle(s)</td>'
            . '<td>' . faction_member_counts($counts) . '</td>'
            . '<td><a class="btn btn-sm btn-outline-primary" href="/admin/factions.php?action=edit&amp;code='
            . e(urlencode($faction->getCode())) . '">Éditer</a> '
            . '<a class="btn btn-sm btn-outline-secondary" href="/admin/faction-members.php?faction='
            . e(urlencode($faction->getCode())) . '">Membres</a> '
            . '<a class="btn btn-sm btn-outline-secondary" title="Exporter cette faction (bundle JSON)"'
            . ' href="/admin/action-export.php?type=faction&amp;id=' . (int) $faction->getId() . '">JSON</a></td>'
            . '</tr>';
    }

    return '<div class="d-flex justify-content-between align-items-center mb-3">'
        . '<h1 class="mb-0">Factions</h1>'
        . '<div class="d-flex gap-2">'
        . '<a class="btn btn-outline-secondary" href="/admin/action-export.php?type=faction"'
        . ' title="Télécharger toutes les factions en bundle JSON, ré-importable ici ou sur un autre environnement">'
        . '<i class="fas fa-download"></i> Exporter (JSON)</a>'
        . '<a class="btn btn-outline-secondary" href="/admin/action-import.php"'
        . ' title="Importer un bundle JSON (avec prévisualisation avant application)">'
        . '<i class="fas fa-upload"></i> Importer</a>'
        . '<a class="btn btn-primary" href="/admin/factions.php?action=new">+ Nouvelle faction</a>'
        . '</div></div>'
        . '<table class="table table-striped table-sm" data-admin-list data-page-size="30"><thead><tr>'
        . '<th>Code</th><th>Icône</th><th>Nom</th><th>Statut</th><th>Respawn</th>'
        . '<th>Rôles</th><th title="Personnages référençant cette faction (players.faction / secretFaction)">Membres</th><th></th>'
        . '</tr></thead><tbody>' . $rows . '</tbody></table>';
}

/** Une ligne de l'éditeur de rôles (nom + 7 cases + réordonner/supprimer). */
function faction_render_role_row(int $index, string $name, array $flags): string
{
    $cells = '<td class="text-muted role-position align-middle"></td>'
        . '<td><input type="text" class="form-control form-control-sm" name="roles[' . $index . '][name]"'
        . ' value="' . e($name) . '" placeholder="Nom du rôle"></td>';

    foreach (array_keys(FACTION_ROLE_FLAG_LABELS) as $flag) {
        $cells .= '<td class="text-center align-middle"><input type="checkbox"'
            . ' name="roles[' . $index . '][' . $flag . ']" ' . ($flags[$flag] ?? false ? 'checked' : '') . '></td>';
    }

    $cells .= '<td class="text-nowrap">'
        . '<button type="button" class="btn btn-sm btn-outline-secondary role-up" title="Monter">↑</button> '
        . '<button type="button" class="btn btn-sm btn-outline-secondary role-down" title="Descendre">↓</button> '
        . '<button type="button" class="btn btn-sm btn-outline-danger role-remove" title="Retirer">×</button>'
        . '</td>';

    return '<tr class="role-row">' . $cells . '</tr>';
}

function faction_render_form(?Faction $faction, string $csrfToken): string
{
    $isEdit = $faction !== null;
    $action = $isEdit ? 'update' : 'create';
    $title = $isEdit
        ? 'Faction : ' . e($faction->getName()) . ' <span class="text-muted">(' . e($faction->getCode()) . ')</span>'
        : 'Nouvelle faction';

    $codeField = $isEdit
        ? '<input type="hidden" name="code" value="' . e($faction->getCode()) . '">'
            . '<input type="text" class="form-control" value="' . e($faction->getCode()) . '" disabled>'
            . '<small class="form-text text-muted">Le code est référencé par players.faction — non modifiable.</small>'
        : '<input type="text" class="form-control" name="code" required pattern="[a-z][a-z0-9_]*"'
            . ' placeholder="ex: cercle_des_brumes">'
            . '<small class="form-text text-muted">Minuscules / chiffres / _ — stocké dans players.faction.</small>';

    $flagHeaders = '';
    foreach (FACTION_ROLE_FLAG_LABELS as $flag => $label) {
        $flagHeaders .= '<th class="text-center" style="font-size:11px" title="' . e($flag) . '">' . e($label) . '</th>';
    }

    $roleRows = '';
    $index = 0;
    if ($isEdit) {
        foreach ($faction->getRoles() as $role) {
            /** @var FactionRole $role */
            $roleRows .= faction_render_role_row($index++, $role->getName(), $role->getFlags());
        }
    }

    // Template row cloned by the "add" button; index rewritten in JS. PHP
    // rebuilds positions from DOM order (array_values), so row order = rôle 0..n.
    $templateRow = faction_render_role_row(9999, '', []);

    $rolesScript = <<<HTML
<script>
(function () {
    /* Roles editor: add / remove / reorder rows. The POSTed order of the
       rows IS the role order (position 0..n-1 server-side), so moving a row
       is enough — indexes in field names only need to be unique. */
    var table = document.getElementById('faction-roles');
    var body = table.querySelector('tbody');
    var template = document.getElementById('faction-role-template').content;
    var nextIndex = 1000;

    function renumberPositions() {
        body.querySelectorAll('.role-row').forEach(function (row, i) {
            row.querySelector('.role-position').textContent = i;
        });
    }

    document.getElementById('faction-role-add').addEventListener('click', function () {
        var row = template.cloneNode(true).querySelector('tr');
        row.querySelectorAll('input').forEach(function (input) {
            input.name = input.name.replace('[9999]', '[' + nextIndex + ']');
        });
        nextIndex++;
        body.appendChild(row);
        renumberPositions();
        row.querySelector('input[type=text]').focus();
    });

    table.addEventListener('click', function (e) {
        var row = e.target.closest('.role-row');
        if (!row) return;
        if (e.target.classList.contains('role-remove')) {
            row.remove();
        } else if (e.target.classList.contains('role-up') && row.previousElementSibling) {
            body.insertBefore(row, row.previousElementSibling);
        } else if (e.target.classList.contains('role-down') && row.nextElementSibling) {
            body.insertBefore(row.nextElementSibling, row);
        }
        renumberPositions();
    });

    renumberPositions();
})();
</script>
HTML;

    return '<div class="d-flex justify-content-between align-items-center mb-3">'
        . '<h1 class="mb-0">' . $title . '</h1>'
        . '<a class="btn btn-sm btn-outline-secondary" href="/admin/factions.php">← Retour à la liste</a></div>'

        . '<form method="post" action="/admin/factions-save.php?action=' . $action . '">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'

        . '<div class="card mb-3"><div class="card-header">Identité</div><div class="card-body"><div class="row">'
        . '<div class="form-group col-md-3"><label>Code</label>' . $codeField . '</div>'
        . '<div class="form-group col-md-3"><label>Nom affiché</label>'
        . '<input type="text" class="form-control" name="name" required value="'
        . e($isEdit ? $faction->getName() : '') . '"></div>'
        . '<div class="form-group col-md-3"><label>Icône (classe ra)</label>'
        . '<input type="text" class="form-control" name="raFont" value="'
        . e($isEdit ? $faction->getRaFont() : '') . '" placeholder="ex: ra-moon-sun">'
        . '<small class="form-text text-muted">Police RPG-Awesome — affichée sur la page faction,'
        . ' les cartes et les forums.</small></div>'
        . '<div class="form-group col-md-3"><label>Plan de respawn</label>'
        . '<input type="text" class="form-control" name="respawnPlan" value="'
        . e($isEdit ? $faction->getRespawnPlan() : 'olympia') . '">'
        . '<small class="form-text text-muted">Destination à la sortie des enfers.</small></div>'
        . '<div class="form-group col-md-8"><label>Lore</label>'
        . '<textarea class="form-control" name="text" rows="4">'
        . e($isEdit ? $faction->getText() : '') . '</textarea></div>'
        . '<div class="form-group col-md-4"><label>Flags</label><div>'
        . '<label class="mr-3"><input type="checkbox" name="hidden" '
        . checked($isEdit && $faction->isHidden()) . '> Cachée (page visible des admins seuls)</label><br>'
        . '<label><input type="checkbox" name="secret" '
        . checked($isEdit && $faction->isSecret()) . '> Secrète (membres via players.secretFaction,'
        . ' effectif masqué aux non-membres)</label>'
        . '</div></div>'
        . '</div></div></div>'

        . '<div class="card mb-3"><div class="card-header">Rôles (ordonnés)</div><div class="card-body">'
        . '<div class="alert alert-warning py-1" style="font-size: 13px;">'
        . '⚠ Le rôle d\'un membre est un <strong>index</strong> dans cette liste (players.factionRole) :'
        . ' supprimer ou réordonner des rôles décale le rôle affiché des membres existants —'
        . ' corrigez ensuite via la page <a href="/admin/faction-members.php">Membres</a>.</div>'
        . '<table class="table table-sm mb-2" id="faction-roles"><thead><tr>'
        . '<th>#</th><th style="min-width:180px">Nom</th>' . $flagHeaders . '<th></th>'
        . '</tr></thead><tbody>' . $roleRows . '</tbody></table>'
        . '<template id="faction-role-template"><table>' . $templateRow . '</table></template>'
        . '<button type="button" class="btn btn-sm btn-outline-primary" id="faction-role-add">+ Ajouter un rôle</button>'
        . '<small class="form-text text-muted mt-2">« Défaut » : rôle attribué quand un membre est affecté sans rôle précis.'
        . ' Les autres drapeaux sont conservés depuis les JSON legacy pour la future gestion en jeu.</small>'
        . '</div></div>'

        . '<button type="submit" class="btn btn-primary">' . ($isEdit ? 'Enregistrer' : 'Créer la faction') . '</button>'
        . '</form>'
        . ($isEdit ? faction_render_delete_zone($faction, $csrfToken) : '')
        . $rolesScript;
}

/**
 * Zone de suppression du formulaire d'édition. Le garde-fou côté serveur
 * (FactionService::deleteFaction) refuse tant que players.faction ou
 * players.secretFaction référence le code ; ici on adapte juste l'UI.
 */
function faction_render_delete_zone(Faction $faction, string $csrfToken): string
{
    $counts = (new FactionService())->countPlayersUsingFaction($faction->getCode());
    $total = $counts['members'] + $counts['secretMembers'];

    $body = $total > 0
        ? '<p class="mb-0 text-muted">Suppression impossible : cette faction est encore référencée par '
            . faction_member_counts($counts) . '. Réaffectez-les via la page'
            . ' <a href="/admin/faction-members.php?faction=' . e(urlencode($faction->getCode())) . '">Membres</a>,'
            . ' ou cochez « Cachée » pour la retirer du jeu sans la supprimer.</p>'
        : '<form method="post" action="/admin/factions-save.php?action=delete" class="d-flex align-items-center gap-3"'
            . ' onsubmit="return confirm(\'Supprimer définitivement la faction « '
            . e($faction->getCode()) . ' » et ses rôles ?\');">'
            . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
            . '<input type="hidden" name="code" value="' . e($faction->getCode()) . '">'
            . '<button type="submit" class="btn btn-outline-danger">Supprimer la faction</button>'
            . '<small class="text-muted">Aucun personnage ne référence cette faction. Supprime aussi ses rôles —'
            . ' pensez à exporter un bundle JSON avant, pour pouvoir la restaurer.</small>'
            . '</form>';

    return '<div class="card mt-4 border-danger"><div class="card-header text-danger">Zone dangereuse</div>'
        . '<div class="card-body">' . $body . '</div></div>';
}

/* -------------------------------------------------------------------------
 * Route
 * ---------------------------------------------------------------------- */
$csrfToken = (new CsrfProtectionService())->generateToken();
$service = new FactionService();

$action = $_GET['action'] ?? 'list';

if ($action === 'new') {
    $content = faction_render_form(null, $csrfToken);
} elseif ($action === 'edit') {
    $faction = $service->getFactionByCode((string) ($_GET['code'] ?? ''));
    if ($faction === null) {
        setFlash('warning', 'Faction introuvable.');
        redirectTo('/admin/factions.php');
    }
    $content = faction_render_form($faction, $csrfToken);
} else {
    $content = faction_render_list($service->getAllFactions());
}

echo admin_layout('Factions', renderFlashMessage() . $content);
