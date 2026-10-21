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
use App\Service\Action\RpgAwesomeIcons;
use App\View\Action\IconFieldView;
use App\Entity\FactionRole;
use App\Service\CsrfProtectionService;
use App\Service\FactionService;

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

    return '<table class="table table-striped table-sm" data-admin-list data-page-size="30"><thead><tr>'
        . '<th>Code</th><th>Icône</th><th>Nom</th><th>Statut</th><th>Respawn</th>'
        . '<th>Rôles</th><th title="Personnages membres de cette faction, ouvertement ou en secret">Membres</th><th></th>'
        . '</tr></thead><tbody>' . $rows . '</tbody></table>';
}

/** Une ligne de l'éditeur de rôles (noms + une case par drapeau + réordonner/supprimer). */
function faction_render_role_row(int $index, string $name, string $nameAlt, array $flags): string
{
    $cells = '<td class="text-muted role-position align-middle"></td>'
        . '<td><input type="text" class="form-control form-control-sm" name="roles[' . $index . '][name]"'
        . ' value="' . e($name) . '" placeholder="Nom du rôle">'
        . '<input type="text" class="form-control form-control-sm mt-1" name="roles[' . $index . '][nameAlt]"'
        . ' value="' . e($nameAlt) . '" placeholder="Second nom (ex : Reine)"></td>';

    foreach (FactionRole::flagKeys() as $flag) {
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
    $respawn = $isEdit ? $faction->getRespawnCoords() : ['x' => 0, 'y' => 0, 'z' => 0];
    $respawnInput = static fn (string $name, string $axis): string =>
        '<input type="number" class="form-control form-control-sm" name="' . $name . '" value="'
        . $respawn[$axis] . '" placeholder="' . $axis . '" aria-label="' . $axis . '">';

    $codeField = $isEdit
        ? '<input type="hidden" name="code" value="' . e($faction->getCode()) . '">'
            . '<input type="text" class="form-control" value="' . e($faction->getCode()) . '" disabled>'
            . '<small class="form-text text-muted">Non modifiable : les membres y sont rattachés'
            . '<code class="tech-name">players.faction</code>.</small>'
        : '<input type="text" class="form-control" name="code" required pattern="[a-z][a-z0-9_]*"'
            . ' placeholder="ex: cercle_des_brumes">'
            . '<small class="form-text text-muted">Minuscules, chiffres et _ uniquement ; non modifiable ensuite.</small>';

    $flagHeaders = '';
    foreach (FactionRole::FLAG_LABELS as $flag => $label) {
        $flagHeaders .= '<th class="text-center" style="font-size:11px" title="' . e($flag) . '">' . e($label) . '</th>';
    }

    $roleRows = '';
    $index = 0;
    if ($isEdit) {
        foreach ($faction->getRoles() as $role) {
            /** @var FactionRole $role */
            $roleRows .= faction_render_role_row($index++, $role->getName(), $role->getNameAlt(), $role->getFlags());
        }
    }

    // Template row cloned by the "add" button; index rewritten in JS. PHP
    // rebuilds positions from DOM order (array_values), so row order = rôle 0..n.
    $templateRow = faction_render_role_row(9999, '', '', []);

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

    return '<form method="post" action="/admin/factions-save.php?action=' . $action . '">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'

        . '<div class="card mb-3"><div class="card-header">Identité</div><div class="card-body"><div class="row">'
        . '<div class="form-group col-md-3"><label>Code</label>' . $codeField . '</div>'
        . '<div class="form-group col-md-3"><label>Nom affiché</label>'
        . '<input type="text" class="form-control" name="name" required value="'
        . e($isEdit ? $faction->getName() : '') . '"></div>'
        . '<div class="form-group col-md-3"><label>Icône</label>'
        . (new IconFieldView())->render($isEdit ? (string) $faction->getRaFont() : '', 'raFont', null, 'icon_color', false)
        . '<small class="form-text text-muted">Affichée sur la page faction, les cartes et les forums.</small></div>'
        . '<div class="form-group col-md-3"><label>Plan de respawn</label>'
        . formSelect('respawnPlan', plan_choices(), $isEdit ? (string) $faction->getRespawnPlan() : plans()->worldPlan())
        . '<small class="form-text text-muted">Où revient un membre après sa mort.</small>'
        . '<div class="d-flex mt-1" title="Case d\'arrivée (x, y, z) — la plus proche libre si occupée">'
        . $respawnInput('respawnX', 'x') . $respawnInput('respawnY', 'y') . $respawnInput('respawnZ', 'z')
        . '</div></div>'
        . '<div class="form-group col-md-3"><label>Animateur (matricule)</label>'
        . '<input type="number" class="form-control" name="animateurId" value="'
        . e($isEdit ? (string) $faction->getAnimateurId() : '') . '">'
        . '<small class="form-text text-muted">Reçoit les missives vers la faction. Vide = aucun, négatif = PNJ.</small></div>'
        . '<div class="form-group col-md-8"><label>Lore</label>'
        . '<textarea class="form-control" name="text" rows="4">'
        . e($isEdit ? $faction->getText() : '') . '</textarea></div>'
        . '<div class="form-group col-md-4"><label>Flags</label><div>'
        . '<label class="mr-3"><input type="checkbox" name="hidden" '
        . checked($isEdit && $faction->isHidden()) . '> Cachée (page visible des admins seuls)</label><br>'
        . '<label><input type="checkbox" name="secret" '
        . checked($isEdit && $faction->isSecret()) . '> Secrète (liste des membres cachée'
        . ' à qui n\'en fait pas partie)</label>'
        . '</div></div>'
        . '</div></div></div>'

        . '<div class="card mb-3"><div class="card-header">Rôles (ordonnés)</div><div class="card-body">'
        . '<div class="alert alert-warning py-1">'
        . '⚠ Le rôle d\'un membre est sa <strong>position</strong> dans cette liste'
        . '<code class="tech-name">players.factionRole</code> :'
        . ' supprimer ou réordonner des rôles change le rôle des membres existants ;'
        . ' corrigez ensuite via la page <a href="/admin/faction-members.php">Membres</a>.</div>'
        . '<table class="table table-sm mb-2" id="faction-roles"><thead><tr>'
        . '<th>#</th><th style="min-width:180px">Nom</th>' . $flagHeaders . '<th></th>'
        . '</tr></thead><tbody>' . $roleRows . '</tbody></table>'
        . '<template id="faction-role-template"><table>' . $templateRow . '</table></template>'
        . '<button type="button" class="btn btn-sm btn-outline-primary" id="faction-role-add">+ Ajouter un rôle</button>'
        . '<div class="alert alert-info mt-3 mb-0">'
        . 'ℹ️ Mêmes drapeaux que l\'échelle du panneau de faction en jeu. « Accueil » : rang donné aux'
        . ' recrues (le premier coché l\'emporte). Le second nom s\'affiche au choix du membre (Roi / Reine).'
        . '</div>'
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
            . ' data-confirm="' . e('Supprimer définitivement la faction « ' . $faction->getCode() . ' » et ses rôles ?') . '">'
            . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
            . '<input type="hidden" name="code" value="' . e($faction->getCode()) . '">'
            . '<button type="submit" class="btn btn-outline-danger">Supprimer la faction</button>'
            . '<small class="text-muted">Aucun personnage ne référence cette faction. Supprime aussi ses rôles —'
            . ' pensez à exporter un bundle JSON avant, pour pouvoir la restaurer.</small>'
            . '</form>';

    return dangerZone($body);
}

/* -------------------------------------------------------------------------
 * Route
 * ---------------------------------------------------------------------- */
$csrfToken = (new CsrfProtectionService())->generateToken();
$service = new FactionService();

$action = $_GET['action'] ?? 'list';

$options = ['back' => ['/admin/factions.php', 'Factions']];
if ($action === 'new') {
    $title = 'Nouvelle faction';
    $content = faction_render_form(null, $csrfToken);
} elseif ($action === 'edit') {
    $faction = $service->getFactionByCode((string) ($_GET['code'] ?? ''));
    if ($faction === null) {
        setFlash('warning', 'Faction introuvable.');
        redirectTo('/admin/factions.php');
    }
    $title = 'Faction : ' . e($faction->getName()) . ' <span class="text-muted">(' . e($faction->getCode()) . ')</span>';
    $content = faction_render_form($faction, $csrfToken);
}
if ($action === 'new' || $action === 'edit') {
    $options['styles'] = ['/css/rpg-awesome.min.css', '/admin/css/icon-picker.css'];
    $options['scripts'] = ['/admin/js/icon-picker.js'];
    // Icon catalogue of the picker (IconFieldView), as on the effects form.
    $content .= '<script>window.WB_ICONS = '
        . json_encode((new RpgAwesomeIcons())->all(), JSON_UNESCAPED_SLASHES) . ';</script>';
} else {
    $title = 'Factions';
    $options = ['actions' => '<a class="btn btn-outline-secondary" href="/admin/action-export.php?type=faction"'
        . ' title="Télécharger toutes les factions en bundle JSON, ré-importable ici ou sur un autre environnement">'
        . '<i class="fas fa-download"></i> Exporter (JSON)</a>'
        . '<a class="btn btn-outline-secondary" href="/admin/action-import.php"'
        . ' title="Importer un bundle JSON (avec prévisualisation avant application)">'
        . '<i class="fas fa-upload"></i> Importer</a>'
        . '<a class="btn btn-primary" href="/admin/factions.php?action=new">+ Nouvelle faction</a>'];
    $content = faction_render_list($service->getAllFactions());
}

echo admin_layout($title, $content, $options);
