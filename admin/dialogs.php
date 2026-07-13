<?php
/**
 * Gestion des dialogues de jeu (admin dashboard → Dialogues).
 *
 * Deux vues, routées sur ?action :
 *   - list (défaut) : les dialogues de la table `dialogs` (nœuds, actif,
 *     déclencheurs map_dialogs qui les référencent), plus une alerte quand
 *     des JSON legacy ne sont pas encore seedés.
 *   - edit / new    : un formulaire — code (immuable en édition), nom du PNJ,
 *     type, custom, actif, et les nœuds en JSON (validés serveur).
 *
 * Les dialogues migrent de datas/*\/dialogs/*.json vers la table `dialogs`
 * (Version20260713150000_DialogsFromJson) ; le seed se lance depuis
 * admin/dialog-seed.php et le jeu replie sur les fichiers tant qu'une ligne
 * manque. Suppression gardée : `register` (réécrit à chaque inscription) et
 * les dialogues encore référencés par des déclencheurs map_dialogs.
 *
 * Toutes les mutations POSTent vers dialogs-save.php (CSRF, PRG). Cette page
 * ne fait que rendre. Accès via layout.php (AdminMenuAccessService).
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\DialogSeedService;
use App\Service\DialogService;

/** Marqueurs des dialogues branchés en dur dans le code du jeu. */
function dialog_special_badge(string $name): string
{
    return match ($name) {
        DialogService::REGISTER_DIALOG => ' <span class="badge badge-info" title="Réécrit par le jeu à chaque'
            . ' inscription (options de races) — non supprimable">inscription</span>',
        'marchand' => ' <span class="badge badge-info" title="Dialogue des marchands itinérants">marchands</span>',
        default => '',
    };
}

/**
 * @param array<string, array> $dialogs   lignes de DialogService::listGameDialogs()
 * @param array<string, int>   $references code => nb de déclencheurs map_dialogs
 */
function dialogs_render_list(array $dialogs, array $references): string
{
    $rows = '';
    foreach ($dialogs as $dialog) {
        $refs = $references[$dialog['name']] ?? 0;

        $rows .= '<tr>'
            . '<td><code>' . e($dialog['name']) . '</code>' . dialog_special_badge($dialog['name']) . '</td>'
            . '<td>' . e($dialog['npc_name']) . '</td>'
            . '<td>' . e($dialog['type']) . '</td>'
            . '<td>' . count($dialog['nodes']) . '</td>'
            . '<td>' . ($dialog['is_active']
                ? '<span class="badge badge-success">actif</span>'
                : '<span class="badge badge-secondary">inactif</span>') . '</td>'
            . '<td>' . ($refs > 0
                ? $refs . ' déclencheur' . ($refs > 1 ? 's' : '')
                : '<span class="text-muted">—</span>') . '</td>'
            . '<td><a class="btn btn-sm btn-outline-primary" href="/admin/dialogs.php?action=edit&amp;name='
            . e(urlencode($dialog['name'])) . '">Éditer</a> '
            . '<a class="btn btn-sm btn-outline-secondary" title="Exporter ce dialogue (bundle JSON)"'
            . ' href="/admin/action-export.php?type=dialog&amp;dialog=' . e(urlencode($dialog['name'])) . '">JSON</a></td>'
            . '</tr>';
    }

    // JSON legacy pas encore seedés : le jeu les sert en repli, mais ils
    // n'apparaissent pas ici tant que le seed n'a pas tourné
    $pending = array_filter(
        (new DialogSeedService())->preview()['entries'],
        fn(array $entry) => $entry['action'] === 'create'
    );
    $pendingAlert = '';
    if ($pending !== []) {
        $names = array_map(fn(array $entry) => $entry['name'], $pending);
        $pendingAlert = '<div class="alert alert-warning" style="font-size:13px;">'
            . '<strong>' . count($pending) . ' dialogue(s) JSON non seedé(s)</strong> (' . e(implode(', ', $names)) . ')'
            . ' — le jeu les sert depuis leurs fichiers en attendant. '
            . '<a href="/admin/dialog-seed.php">Lancer le seed</a>.</div>';
    }

    return '<div class="d-flex justify-content-between align-items-center mb-3">'
        . '<h1 class="mb-0">Dialogues</h1>'
        . '<div class="d-flex gap-2">'
        . '<a class="btn btn-outline-secondary" href="/admin/action-export.php?type=dialog"'
        . ' title="Télécharger tous les dialogues en bundle JSON, ré-importable ici ou sur un autre environnement">'
        . '<i class="fas fa-download"></i> Exporter (JSON)</a>'
        . '<a class="btn btn-outline-secondary" href="/admin/action-import.php"'
        . ' title="Importer un bundle JSON (avec prévisualisation avant application)">'
        . '<i class="fas fa-upload"></i> Importer</a>'
        . '<a class="btn btn-primary" href="/admin/dialogs.php?action=new">+ Nouveau dialogue</a>'
        . '</div></div>'

        . '<div class="alert alert-info" style="font-size:13px;line-height:1.5;">'
        . 'Un dialogue est déclenché par une case <code style="display:inline">map_dialogs</code>'
        . ' (params « nom,avatar,dialogue », posés via l\'éditeur Tiled) ou directement par le code'
        . ' (inscription, marchands). Structure : des nœuds <code style="display:inline">{id, text, options}</code>,'
        . ' le nœud <code style="display:inline">bonjour</code> est le point d\'entrée ; chaque option mène à un'
        . ' nœud (<code style="display:inline">go</code>), une page (<code style="display:inline">url</code>)'
        . ' ou pose une variable (<code style="display:inline">set</code>).</div>'

        . $pendingAlert

        . '<table class="table table-striped table-sm" data-admin-list data-page-size="30"><thead><tr>'
        . '<th>Code</th><th>Nom du PNJ</th><th>Type</th><th>Nœuds</th><th>Statut</th>'
        . '<th title="Déclencheurs map_dialogs référençant ce dialogue">Références</th><th></th>'
        . '</tr></thead><tbody>' . $rows . '</tbody></table>';
}

/**
 * @param array<string, mixed>|null $dialog ligne de listGameDialogs(), null = création
 */
function dialogs_render_form(?array $dialog, string $csrfToken): string
{
    $isEdit = $dialog !== null;
    $action = $isEdit ? 'update' : 'create';
    $title = $isEdit
        ? 'Dialogue : <code>' . e($dialog['name']) . '</code>'
        : 'Nouveau dialogue';

    $nameField = $isEdit
        ? '<input type="hidden" name="name" value="' . e($dialog['name']) . '">'
            . '<input type="text" class="form-control" value="' . e($dialog['name']) . '" disabled>'
            . '<small class="form-text text-muted">Le code est référencé par les déclencheurs map_dialogs — non modifiable.</small>'
        : '<input type="text" class="form-control" name="name" required pattern="[a-z0-9_-]{1,100}"'
            . ' placeholder="ex: forgeron">'
            . '<small class="form-text text-muted">Minuscules / chiffres / _ / - (100 max) — référencé par les'
            . ' déclencheurs map_dialogs (3e champ des params).</small>';

    $nodesJson = $isEdit
        ? json_encode($dialog['nodes'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : json_encode([[
            'id'      => 'bonjour',
            'text'    => 'Bonjour, PLAYER_NAME !',
            'options' => [['go' => 'EXIT', 'text' => '[partir]']],
        ]], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return '<div class="d-flex justify-content-between align-items-center mb-3">'
        . '<h1 class="mb-0">' . $title . '</h1>'
        . '<div class="d-flex gap-2">'
        . '<a class="btn btn-sm btn-outline-secondary" href="/admin/dialogs.php">← Retour à la liste</a>'
        . ($isEdit
            ? '<a class="btn btn-sm btn-outline-secondary" title="Exporter ce dialogue (bundle JSON)"'
                . ' href="/admin/action-export.php?type=dialog&amp;dialog=' . e(urlencode($dialog['name'])) . '">'
                . '<i class="fas fa-download"></i> JSON</a>'
            : '')
        . '</div></div>'

        . '<form method="post" action="/admin/dialogs-save.php?action=' . $action . '">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'

        . '<div class="card mb-3"><div class="card-header">Identité</div><div class="card-body"><div class="row">'
        . '<div class="form-group col-md-3"><label>Code</label>' . $nameField . '</div>'
        . '<div class="form-group col-md-3"><label>Nom du PNJ affiché</label>'
        . '<input type="text" class="form-control" name="npc_name" value="'
        . e($isEdit ? $dialog['npc_name'] : 'TARGET_NAME') . '">'
        . '<small class="form-text text-muted">Placeholders : TARGET_NAME (PNJ ciblé), PLAYER_NAME.</small></div>'
        . '<div class="form-group col-md-2"><label>Type</label>'
        . '<input type="text" class="form-control" name="type" value="' . e($isEdit ? $dialog['type'] : 'pnj') . '"></div>'
        . '<div class="form-group col-md-2"><label>Custom</label>'
        . '<input type="text" class="form-control" name="custom" value="' . e($isEdit ? $dialog['custom'] : '') . '"></div>'
        . '<div class="form-group col-md-2"><label>Statut</label><div>'
        . '<label style="cursor:pointer;"><input type="checkbox" name="is_active" '
        . checked(!$isEdit || $dialog['is_active']) . '> Actif</label>'
        . '<small class="form-text text-muted">Inactif : le jeu replie sur le fichier JSON s\'il existe.</small>'
        . '</div></div>'
        . '</div></div></div>'

        . '<div class="card mb-3"><div class="card-header">Nœuds (JSON)</div><div class="card-body">'
        . '<textarea class="form-control" name="dialog_data" rows="24" spellcheck="false"'
        . ' style="font-family:monospace;font-size:12px;">' . e($nodesJson) . '</textarea>'
        . '<small class="form-text text-muted">Liste de nœuds <code style="display:inline">{id, text, options}</code>.'
        . ' Le nœud <code style="display:inline">bonjour</code> est affiché en premier ; chaque option porte un'
        . ' <code style="display:inline">text</code> et une cible : <code style="display:inline">go</code>'
        . ' (id de nœud, <code style="display:inline">EXIT</code>/<code style="display:inline">RESET</code>),'
        . ' <code style="display:inline">url</code> ou <code style="display:inline">set</code>.'
        . ' Optionnels sur un nœud : <code style="display:inline">avatar</code>,'
        . ' <code style="display:inline">type</code>, <code style="display:inline">shuffle</code>'
        . ' (mélange les options). Placeholders PLAYER_NAME / PLAYER_ID / TARGET_ID dans les textes et urls.</small>'
        . '</div></div>'

        . '<button type="submit" class="btn btn-primary">' . ($isEdit ? 'Enregistrer' : 'Créer le dialogue') . '</button>'
        . '</form>'
        . ($isEdit ? dialogs_render_delete_zone($dialog['name'], $csrfToken) : '');
}

/**
 * Zone de suppression : les garde-fous font foi côté serveur
 * (DialogService::deleteGameDialog) — ici on adapte juste l'UI.
 */
function dialogs_render_delete_zone(string $name, string $csrfToken): string
{
    $service = new DialogService();
    $references = $service->countMapDialogReferences($name);

    if ($name === DialogService::REGISTER_DIALOG) {
        $body = '<p class="mb-0 text-muted">Suppression impossible : « register » est réécrit par le jeu à chaque'
            . ' inscription (options de races). Décochez « Actif » pour replier sur le fichier JSON.</p>';
    } elseif ($references > 0) {
        $body = '<p class="mb-0 text-muted">Suppression impossible : ' . $references . ' déclencheur(s)'
            . ' map_dialogs référencent ce dialogue — retirez-les d\'abord (éditeur Tiled).</p>';
    } else {
        $body = '<form method="post" action="/admin/dialogs-save.php?action=delete" class="d-flex align-items-center gap-3"'
            . ' onsubmit="return confirm(\'Supprimer définitivement le dialogue « ' . e($name) . ' » ?\');">'
            . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
            . '<input type="hidden" name="name" value="' . e($name) . '">'
            . '<button type="submit" class="btn btn-outline-danger">Supprimer le dialogue</button>'
            . '<small class="text-muted">Aucun déclencheur ne le référence. Pensez à exporter un bundle JSON avant,'
            . ' pour pouvoir le restaurer.</small>'
            . '</form>';
    }

    return '<div class="card mt-4 border-danger"><div class="card-header text-danger">Zone dangereuse</div>'
        . '<div class="card-body">' . $body . '</div></div>';
}

/* -------------------------------------------------------------------------
 * Route
 * ---------------------------------------------------------------------- */
$csrfToken = (new CsrfProtectionService())->generateToken();
$service = new DialogService();
$dialogs = $service->listGameDialogs();

$action = $_GET['action'] ?? 'list';

if ($action === 'new') {
    $content = dialogs_render_form(null, $csrfToken);
} elseif ($action === 'edit') {
    $name = strtolower(trim((string) ($_GET['name'] ?? '')));
    if (!isset($dialogs[$name])) {
        setFlash('warning', 'Dialogue introuvable (pas encore seedé ?).');
        redirectTo('/admin/dialogs.php');
    }
    $content = dialogs_render_form($dialogs[$name], $csrfToken);
} else {
    $content = dialogs_render_list($dialogs, $service->mapDialogReferenceCounts());
}

echo admin_layout('Dialogues', renderFlashMessage() . $content);
