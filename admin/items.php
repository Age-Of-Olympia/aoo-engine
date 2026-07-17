<?php
/**
 * Items catalog management (admin dashboard → Objets) — v1.
 *
 * Lists the whole catalog and edits the DB-BACKED columns: gameplay
 * flags (maudit, enchanté, vorpal, élément, sort lié, banque, exotique,
 * déprécié) and the WEAR configuration (déclencheurs + rythme par
 * tour) — the balance lever of items Phase 2, inert by default.
 *
 * Item STATS (emplacement, caracs, prix, texte…) still live in
 * datas/*\/items/*.json and are shown read-only here; their JSON→DB
 * migration (same move as races) is the follow-up that unlocks full
 * editing.
 *
 * All mutations POST to items-save.php (CSRF-validated, PRG). This
 * page only renders. Access enforced by layout.php via
 * AdminMenuAccessService.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use Classes\Item;

const ITEM_WEAR_TRIGGERS = [
    'attack'  => 'Attaque (porter un coup)',
    'defense' => 'Défense (encaisser un coup)',
    'move'    => 'Déplacement',
    'usage'   => 'Utilisation',
];

/** @return array<int, object> catalog rows ordered by name */
function items_catalog(): array
{
    $rows = [];
    $res = (new \Classes\Db())->exe('SELECT * FROM items ORDER BY name');
    while ($row = $res->fetch_object()) {
        $rows[] = $row;
    }
    return $rows;
}

function item_flag_badges(object $row): string
{
    $badges = [];
    foreach ([
        'cursed' => ['Maudit', 'danger'],
        'enchanted' => ['Enchanté', 'info'],
        'vorpal' => ['Vorpal', 'info'],
        'is_deprecated' => ['Déprécié', 'secondary'],
    ] as $col => [$label, $style]) {
        if (!empty($row->$col)) {
            $badges[] = '<span class="badge badge-' . $style . '">' . $label . '</span>';
        }
    }
    if (empty($row->is_bankable)) {
        $badges[] = '<span class="badge badge-warning">Non bancable</span>';
    }
    if (!empty($row->exotique)) {
        $badges[] = '<span class="badge badge-secondary">Exotique (' . e($row->exotique) . ')</span>';
    }
    return $badges === [] ? '<span class="text-muted">—</span>' : implode(' ', $badges);
}

function item_wear_cell(object $row): string
{
    if ((int) $row->wear_rate <= 0 || trim((string) $row->wear_triggers) === '') {
        return '<span class="text-muted">ne s\'use pas</span>';
    }
    return '<b>−' . (int) $row->wear_rate . '/tour</b> sur ' . e($row->wear_triggers);
}

/** @param array<int, object> $items */
function items_render_list(array $items): string
{
    $rows = '';
    foreach ($items as $row) {
        $rows .= '<tr>'
            . '<td><img src="/img/items/' . e($row->name) . '_mini.webp" style="max-height:24px"'
            . ' onerror="this.style.display=\'none\'" alt=""> <code>' . e($row->name) . '</code></td>'
            . '<td>' . item_flag_badges($row) . '</td>'
            . '<td>' . ($row->element !== '' && $row->element !== null ? e($row->element) : '<span class="text-muted">—</span>') . '</td>'
            . '<td>' . ($row->spell !== '' && $row->spell !== null ? e($row->spell) : '<span class="text-muted">—</span>') . '</td>'
            . '<td>' . item_wear_cell($row) . '</td>'
            . '<td><a class="btn btn-sm btn-outline-primary" href="/admin/items.php?action=edit&id=' . (int) $row->id . '">Éditer</a></td>'
            . '</tr>';
    }

    return '<p class="text-muted">Les stats de jeu (emplacement, caracs, prix, texte…) vivent encore dans'
        . ' <code>datas/*/items/*.json</code> — leur migration en base (comme les races) débloquera leur édition ici.</p>'
        . '<input type="text" class="form-control mb-2" id="items-filter" placeholder="filtrer…"'
        . ' onkeyup="var q=this.value.toLowerCase();document.querySelectorAll(\'#items-table tbody tr\').forEach('
        . 'function(tr){tr.style.display = tr.textContent.toLowerCase().indexOf(q) === -1 ? \'none\' : \'\';});">'
        . '<div class="table-responsive"><table class="table table-sm table-striped align-middle" id="items-table">'
        . '<thead><tr><th>Objet</th><th>Flags</th><th>Élément</th><th>Sort lié</th><th>Usure</th><th></th></tr></thead>'
        . '<tbody>' . $rows . '</tbody></table></div>';
}

function items_render_edit(object $row, string $csrfToken): string
{
    $item = new Item((int) $row->id, $row);
    $item->get_data();

    $triggers = array_filter(array_map('trim', explode(',', (string) $row->wear_triggers)));

    $triggerBoxes = '';
    foreach (ITEM_WEAR_TRIGGERS as $key => $label) {
        $triggerBoxes .= '<label class="mr-3"><input type="checkbox" name="wear_triggers[]" value="' . $key . '" '
            . checked(in_array($key, $triggers, true)) . '> ' . $label . '</label> ';
    }

    $flagBoxes = '';
    foreach ([
        'cursed' => 'Maudit (ne se lâche ni se déséquipe)',
        'enchanted' => 'Enchanté (ne casse pas)',
        'vorpal' => 'Vorpal',
        'is_bankable' => 'Stockable en banque',
        'is_deprecated' => 'Déprécié',
    ] as $col => $label) {
        $flagBoxes .= '<label class="d-block"><input type="checkbox" name="' . $col . '" '
            . checked(!empty($row->$col)) . '> ' . $label . '</label>';
    }

    // Lecture seule : les stats JSON.
    $jsonStats = [];
    foreach (['emplacement', 'type', 'subtype', 'price'] as $k) {
        if (!empty($item->data->$k)) {
            $jsonStats[] = '<b>' . $k . '</b> : ' . e((string) $item->data->$k);
        }
    }
    $caracs = Item::get_item_carac($item->data);
    if ($caracs !== []) {
        $jsonStats[] = '<b>caracs</b> : ' . implode(', ', $caracs);
    }

    $body = '<form method="post" action="/admin/items-save.php?action=update">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
        . '<input type="hidden" name="id" value="' . (int) $row->id . '">'
        . '<div class="row"><div class="col-md-4"><h5>Flags</h5>' . $flagBoxes
        . '<div class="form-group mt-2"><label>Élément</label>'
        . '<input type="text" class="form-control" name="element" value="' . e((string) $row->element) . '"></div>'
        . '<div class="form-group"><label>Sort lié (consommation)</label>'
        . '<input type="text" class="form-control" name="spell" value="' . e((string) $row->spell) . '"></div>'
        . '<div class="form-group"><label>Exotique (race)</label>'
        . '<input type="text" class="form-control" name="exotique" value="' . e((string) $row->exotique) . '"></div>'
        . '</div>'
        . '<div class="col-md-4"><h5>Usure <small class="text-muted">(par tour)</small></h5>'
        . '<div class="form-group">' . $triggerBoxes . '</div>'
        . '<div class="form-group"><label>Points perdus par tour armé</label>'
        . '<input type="number" min="0" class="form-control" name="wear_rate" value="' . (int) $row->wear_rate . '">'
        . '<small class="text-muted">0 = ne s\'use jamais. Les événements arment, le passage de tour applique.</small></div>'
        . '</div>'
        . '<div class="col-md-4"><h5>Stats (JSON, lecture seule)</h5>'
        . '<div class="text-muted">' . ($jsonStats === [] ? 'aucune' : implode('<br>', $jsonStats)) . '</div>'
        . '<div class="mt-2"><small>' . e((string) ($item->data->text ?? '')) . '</small></div>'
        . '</div></div>'
        . '<button class="btn btn-primary" type="submit">Enregistrer</button> '
        . '<a class="btn btn-secondary" href="/admin/items.php">Retour</a>'
        . '</form>';

    return '<div class="card"><div class="card-header">' . e(ucfirst($row->name)) . ' <code>#' . (int) $row->id . '</code></div>'
        . '<div class="card-body">' . $body . '</div></div>';
}

/* -------------------------------------------------------------------------
 * Route
 * ---------------------------------------------------------------------- */
$csrfToken = (new CsrfProtectionService())->generateToken();
$action = $_GET['action'] ?? 'list';

if ($action === 'edit') {
    $id = (int) ($_GET['id'] ?? 0);
    $res = (new \Classes\Db())->exe('SELECT * FROM items WHERE id = ?', $id);
    $row = $res->num_rows ? $res->fetch_object() : null;
    if ($row === null) {
        setFlash('warning', 'Objet introuvable.');
        redirectTo('/admin/items.php');
    }
    $content = items_render_edit($row, $csrfToken);
} else {
    $content = items_render_list(items_catalog());
}

echo admin_layout('Objets', renderFlashMessage() . $content);
