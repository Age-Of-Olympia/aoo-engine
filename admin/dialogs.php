<?php
/**
 * Gestion des dialogues de jeu (admin dashboard → Dialogues).
 *
 * Deux vues, routées sur ?action :
 *   - list (défaut) : les dialogues de la table `dialogs` (nœuds, actif,
 *     déclencheurs map_dialogs qui les référencent).
 *   - edit / new    : un formulaire — code (immuable en édition), nom du PNJ,
 *     type, custom, actif, et les nœuds en JSON (validés serveur).
 *
 * Suppression gardée : `register` (réécrit à chaque inscription) et
 * les dialogues encore référencés par des déclencheurs map_dialogs.
 *
 * Toutes les mutations POSTent vers dialogs-save.php (CSRF, PRG). Cette page
 * ne fait que rendre. Accès via layout.php (AdminMenuAccessService).
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\DialogService;
use App\View\Action\IconFieldView;
use App\Service\Action\RpgAwesomeIcons;

/** What a dialogue opens from, by its stored type. */
const DIALOG_TYPE_LABELS = ['pnj' => 'PNJ', 'building' => 'Bâtiment'];

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
 * @param array<string, array> $dialogs      lignes de DialogService::listGameDialogs()
 * @param array<string, int>   $references   code => nb de déclencheurs map_dialogs
 * @param array<string, int>   $buildingRefs code => nb de bâtiments porteurs
 */
function dialogs_render_list(array $dialogs, array $references, array $buildingRefs): string
{
    $rows = '';
    foreach ($dialogs as $dialog) {
        $refs = $references[$dialog['name']] ?? 0;
        $bRefs = $buildingRefs[$dialog['name']] ?? 0;

        $refCell = [];
        if ($refs > 0) {
            $refCell[] = $refs . ' déclencheur' . ($refs > 1 ? 's' : '');
        }
        if ($bRefs > 0) {
            $refCell[] = $bRefs . ' bâtiment' . ($bRefs > 1 ? 's' : '');
        }

        $rows .= '<tr>'
            . '<td><code>' . e($dialog['name']) . '</code>' . dialog_special_badge($dialog['name']) . '</td>'
            . '<td>' . ($dialog['npc_name'] === 'TARGET_NAME'
                ? '<span class="text-muted">nom du bâtiment ou du PNJ</span><code class="tech-name">TARGET_NAME</code>'
                : e($dialog['npc_name'])) . '</td>'
            . '<td>' . techLabel(DIALOG_TYPE_LABELS[$dialog['type']] ?? $dialog['type'], (string) $dialog['type']) . '</td>'
            . '<td>' . count($dialog['nodes']) . '</td>'
            . '<td>' . ($dialog['is_active']
                ? '<span class="badge badge-success">actif</span>'
                : '<span class="badge badge-secondary">inactif</span>') . '</td>'
            . '<td>' . ($refCell !== []
                ? e(implode(', ', $refCell))
                : '<span class="text-muted">—</span>') . '</td>'
            . '<td><a class="btn btn-sm btn-outline-primary" href="/admin/dialogs.php?action=edit&amp;name='
            . e(urlencode($dialog['name'])) . '">Éditer</a> '
            . '<a class="btn btn-sm btn-outline-secondary" title="Exporter ce dialogue (bundle JSON)"'
            . ' href="/admin/action-export.php?type=dialog&amp;dialog=' . e(urlencode($dialog['name'])) . '">JSON</a></td>'
            . '</tr>';
    }

    return '<table class="table table-striped table-sm" data-admin-list data-page-size="30"><thead><tr>'
        . '<th>Code</th><th>Nom du PNJ</th><th>Type</th><th>Nœuds</th><th>Statut</th>'
        . '<th title="Cases (posées dans Tiled) et bâtiments qui ouvrent ce dialogue">Utilisé par</th><th></th>'
        . '</tr></thead><tbody>' . $rows . '</tbody></table>';
}

/**
 * Apparence des boutons de comptoir : une ligne par option menant à
 * merchant.php / warschool.php, avec le sélecteur d'icône des actions et
 * le libellé. Vide, l'un ou l'autre revient au défaut du jeu (Banque,
 * Réparer, Recycler, sinon Marchander ou Apprendre).
 *
 * @param array<int, array<string, mixed>> $nodes
 */
function dialogs_render_counters(array $nodes): string
{
    $counters = DialogService::counterOptionsOfNodes($nodes);
    if ($counters === []) {
        return '';
    }

    $rows = '';
    foreach ($counters as $counter) {
        $key = $counter['node'] . '|' . $counter['index'];
        // The *_was fields let the save tell a change here from a change made in the JSON
        $rows .= '<tr>'
            . '<td><code>' . e($counter['url']) . '</code><br><small class="text-muted">'
            . e($counter['text']) . '</small></td>'
            . '<td>' . (new IconFieldView())->render($counter['icon'], 'counter_icon[' . $key . ']', null, '', false)
            . '<input type="hidden" name="counter_icon_was[' . e($key) . ']" value="' . e($counter['icon']) . '"></td>'
            . '<td><input type="text" class="form-control" name="counter_button[' . e($key) . ']"'
            . ' value="' . e($counter['button']) . '" placeholder="libellé par défaut">'
            . '<input type="hidden" name="counter_button_was[' . e($key) . ']" value="' . e($counter['button']) . '"></td>'
            . '</tr>';
    }

    return '<div class="card mb-3"><div class="card-header">Boutons de comptoir</div><div class="card-body">'
        . '<p class="text-muted" style="font-size:13px;">La carte d\'une case affiche un bouton par comptoir'
        . ' servi. Laissez vide pour garder le bouton par défaut : Banque, Réparer, Recycler, sinon'
        . ' Marchander ou Apprendre.</p>'
        . '<table class="table table-sm mb-0"><thead><tr><th>Option</th><th>Icône</th><th>Libellé</th></tr></thead>'
        . '<tbody>' . $rows . '</tbody></table>'
        . '</div></div>';
}

/**
 * @param array<string, mixed>|null $dialog ligne de listGameDialogs(), null = création
 */
function dialogs_render_form(?array $dialog, string $csrfToken): string
{
    $isEdit = $dialog !== null;
    $action = $isEdit ? 'update' : 'create';
    $nameField = $isEdit
        ? '<input type="hidden" name="name" value="' . e($dialog['name']) . '">'
            . '<input type="text" class="form-control" value="' . e($dialog['name']) . '" disabled>'
            . '<small class="form-text text-muted">Non modifiable : les cases et bâtiments qui ouvrent ce dialogue y sont rattachés.</small>'
        : '<input type="text" class="form-control" name="name" required pattern="[a-z0-9_-]{1,100}"'
            . ' placeholder="ex: forgeron">'
            . '<small class="form-text text-muted">Minuscules, chiffres, _ et - (100 max) ; non modifiable ensuite.</small>';

    $nodesJson = $isEdit
        ? json_encode($dialog['nodes'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : json_encode([[
            'id'      => 'bonjour',
            'text'    => 'Bonjour, PLAYER_NAME !',
            'options' => [['go' => 'EXIT', 'text' => '[partir]']],
        ]], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return '<form method="post" action="/admin/dialogs-save.php?action=' . $action . '">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'

        . '<div class="card mb-3"><div class="card-header">Identité</div><div class="card-body"><div class="row">'
        . '<div class="form-group col-md-3"><label>Code</label>' . $nameField . '</div>'
        . '<div class="form-group col-md-3"><label>Nom du PNJ affiché</label>'
        . '<input type="text" class="form-control" name="npc_name" value="'
        . e($isEdit ? $dialog['npc_name'] : 'TARGET_NAME') . '">'
        . '<small class="form-text text-muted"><code>TARGET_NAME</code> affiche le nom du bâtiment ou du PNJ,'
        . ' <code>PLAYER_NAME</code> celui du joueur.</small></div>'
        . '<div class="form-group col-md-2"><label>Type</label>'
        . '<input type="text" class="form-control" name="type" value="' . e($isEdit ? $dialog['type'] : 'pnj') . '"></div>'
        . '<div class="form-group col-md-2"><label>Custom</label>'
        . '<input type="text" class="form-control" name="custom" value="' . e($isEdit ? $dialog['custom'] : '') . '"></div>'
        . '<div class="form-group col-md-2"><label>Statut</label><div>'
        . '<label><input type="checkbox" name="is_active" '
        . checked(!$isEdit || $dialog['is_active']) . '> Actif</label>'
        . '<small class="form-text text-muted">Inactif : le jeu lit le fichier JSON s\'il existe.</small>'
        . '</div></div>'
        . '</div></div></div>'

        . '<div class="card mb-3"><div class="card-header">Nœuds (JSON)</div><div class="card-body">'
        . '<textarea class="form-control" name="dialog_data" rows="24" spellcheck="false"'
        . ' style="font-family:var(--font-mono);font-size:12px;">' . e($nodesJson) . '</textarea>'
        . '<small class="form-text text-muted">Liste de nœuds <code>{id, text, options}</code>.'
        . ' Le nœud <code>bonjour</code> est affiché en premier ; chaque option a un'
        . ' <code>text</code> et une cible : <code>go</code>'
        . ' (id de nœud, <code>EXIT</code>/<code>RESET</code>),'
        . ' <code>url</code> ou <code>set</code>.'
        . ' Optionnels sur un nœud : <code>avatar</code>,'
        . ' <code>type</code>, <code>shuffle</code>'
        . ' (mélange les options). Placeholders PLAYER_NAME / PLAYER_ID / TARGET_ID dans les textes et urls.<br>'
        . ' Une option <code>url</code> vers merchant.php ou warschool.php ouvre un'
        . ' comptoir : son bouton sur la carte se règle dans « Boutons de comptoir », plus bas'
        . ' (clés <code>icon</code> et <code>button</code>).</small>'
        . '</div></div>'

        . ($isEdit ? dialogs_render_counters(json_decode((string) json_encode($dialog['nodes']), true) ?: []) : '')

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
    $buildingReferences = $service->countBuildingDialogReferences($name);

    if ($name === DialogService::REGISTER_DIALOG) {
        $body = '<p class="mb-0 text-muted">Suppression impossible : « register » est réécrit par le jeu à chaque'
            . ' inscription (options de races). Décochez « Actif » pour revenir au fichier JSON.</p>';
    } elseif ($references > 0) {
        $body = '<p class="mb-0 text-muted">Suppression impossible : ' . $references . ' déclencheur(s)'
            . ' de case ouvrent ce dialogue — retirez-les d\'abord dans Tiled.</p>';
    } elseif ($buildingReferences > 0) {
        $body = '<p class="mb-0 text-muted">Suppression impossible : ' . $buildingReferences . ' bâtiment(s)'
            . ' utilisent ce dialogue — détachez-les d\'abord (<a href="/admin/buildings.php">Bâtiments</a>).</p>';
    } else {
        $body = '<form method="post" action="/admin/dialogs-save.php?action=delete" class="d-flex align-items-center gap-3"'
            . ' data-confirm="' . e('Supprimer définitivement le dialogue « ' . $name . ' » ?') . '">'
            . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
            . '<input type="hidden" name="name" value="' . e($name) . '">'
            . '<button type="submit" class="btn btn-outline-danger">Supprimer le dialogue</button>'
            . '<small class="text-muted">Aucun déclencheur ne le référence. Pensez à exporter un bundle JSON avant,'
            . ' pour pouvoir le restaurer.</small>'
            . '</form>';
    }

    return dangerZone($body);
}

/* -------------------------------------------------------------------------
 * Route
 * ---------------------------------------------------------------------- */
$csrfToken = (new CsrfProtectionService())->generateToken();
$service = new DialogService();
$dialogs = $service->listGameDialogs();

$action = $_GET['action'] ?? 'list';

$options = [
    'styles' => ['/css/rpg-awesome.min.css', '/admin/css/icon-picker.css'],
    'scripts' => ['/admin/js/icon-picker.js'],
];
if ($action === 'new') {
    $title = 'Nouveau dialogue';
    $content = dialogs_render_form(null, $csrfToken);
} elseif ($action === 'edit') {
    $name = strtolower(trim((string) ($_GET['name'] ?? '')));
    if (!isset($dialogs[$name])) {
        setFlash('warning', 'Dialogue introuvable (pas encore seedé ?).');
        redirectTo('/admin/dialogs.php');
    }
    $title = 'Dialogue : <code>' . e($name) . '</code>';
    $options['actions'] = '<a class="btn btn-sm btn-outline-secondary" title="Exporter ce dialogue (bundle JSON)"'
        . ' href="/admin/action-export.php?type=dialog&amp;dialog=' . e(urlencode($name)) . '">'
        . '<i class="fas fa-download"></i> JSON</a>';
    $content = dialogs_render_form($dialogs[$name], $csrfToken);
} else {
    $title = 'Dialogues';
    $options['actions'] = '<a class="btn btn-outline-secondary" href="/admin/action-export.php?type=dialog"'
        . ' title="Télécharger tous les dialogues en bundle JSON, ré-importable ici ou sur un autre environnement">'
        . '<i class="fas fa-download"></i> Exporter (JSON)</a>'
        . '<a class="btn btn-outline-secondary" href="/admin/action-import.php"'
        . ' title="Importer un bundle JSON (avec prévisualisation avant application)">'
        . '<i class="fas fa-upload"></i> Importer</a>'
        . '<a class="btn btn-primary" href="/admin/dialogs.php?action=new">+ Nouveau dialogue</a>';
    $options['intro'] = 'Un dialogue s\'ouvre depuis une case posée dans Tiled'
        . '<code class="tech-name">map_dialogs</code>, un bâtiment, ou le jeu lui-même (inscription, marchands).'
        . ' Il commence au nœud <code>bonjour</code> ; chaque réponse mène à un autre nœud'
        . '<code class="tech-name">go</code>, ouvre une page<code class="tech-name">url</code>'
        . ' ou enregistre un choix<code class="tech-name">set</code>.';
    $content = dialogs_render_list(
        $dialogs,
        $service->mapDialogReferenceCounts(),
        $service->buildingDialogReferenceCounts()
    );
}

if ($title !== 'Dialogues') {
    $options['back'] = ['/admin/dialogs.php', 'Dialogues'];
    /* Catalogue d'icônes du sélecteur (IconFieldView) — même mécanisme que
     * le workbench des actions et l'admin des effets ; le formulaire seul en a besoin. */
    $content .= '<script>window.WB_ICONS = '
        . json_encode((new RpgAwesomeIcons())->all(), JSON_UNESCAPED_SLASHES) . ';</script>';
}

echo admin_layout($title, $content, $options);
