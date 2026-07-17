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

    // Stats — pleinement éditables depuis la migration JSON→DB.
    $notInDb = empty($row->stats_in_db)
        ? '<div class="alert alert-warning">Stats pas encore migrées pour cet objet (JSON absent de cet'
          . ' environnement) — <b>enregistrer les stats ici fera de la base sa source</b>.'
          . ' En prod, rejouer le seed : <a href="/admin/item-seed.php">item-seed</a>.</div>'
        : '';

    $emplacementOptions = '<option value="">— aucun —</option>';
    foreach (ITEM_EMPLACEMENT_FORMAT as $emp) {
        $emplacementOptions .= '<option value="' . e($emp) . '"'
            . (((string) ($row->emplacement ?? '')) === $emp ? ' selected' : '') . '>' . e($emp) . '</option>';
    }

    $caracInputs = '';
    foreach (['a', 'mvt', 'p', 'pv', 'cc', 'ct', 'f', 'e', 'agi', 'pm', 'fm', 'm', 'r', 'rm', 'spd', 'ae'] as $k) {
        $caracInputs .= '<div class="col-3 form-group"><label>' . strtoupper($k) . '</label>'
            . '<input type="number" class="form-control form-control-sm" name="' . $k . '" value="' . (int) ($row->$k ?? 0) . '"></div>';
    }

    $specialInputs = '';
    foreach ([
        'esquive' => 'Esquive', 'pr' => 'PR', 'pf' => 'PF', 'malus' => 'Malus',
        'spellMalus' => 'Malus de sort', 'fixedF' => 'F fixée', 'mDamage' => 'Dégâts magiques',
        'demolition' => 'Démolition', 'craftedByN' => 'Craft (n)', 'lootChance' => 'Chance de loot (%)',
    ] as $col => $label) {
        $specialInputs .= '<div class="col-4 form-group"><label>' . $label . '</label>'
            . '<input type="number" class="form-control form-control-sm" name="' . $col . '" value="' . (int) ($row->$col ?? 0) . '"></div>';
    }

    $munitions = !empty($row->munitions) ? implode(', ', (array) json_decode((string) $row->munitions, true)) : '';

    $body = '<form method="post" action="/admin/items-save.php?action=update">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
        . '<input type="hidden" name="id" value="' . (int) $row->id . '">'
        . $notInDb
        . '<div class="row">'

        . '<div class="col-md-3"><h5>Identité</h5>'
        . '<div class="form-group"><label>Description</label>'
        . '<textarea class="form-control" name="text" rows="5">' . e((string) ($row->text ?? '')) . '</textarea></div>'
        . '<div class="form-group"><label>Prix</label>'
        . '<input type="number" min="0" class="form-control" name="price" value="' . (int) ($row->price ?? 1) . '"></div>'
        . '<div class="form-group"><label>Emplacement</label>'
        . '<select name="emplacement" class="form-control">' . $emplacementOptions . '</select></div>'
        . '<div class="form-group"><label>Type</label>'
        . '<input type="text" class="form-control" name="type" value="' . e((string) ($row->type ?? '')) . '"'
        . ' placeholder="equipement, consommable, structure…"></div>'
        . '<div class="form-group"><label>Sous-type</label>'
        . '<input type="text" class="form-control" name="subtype" value="' . e((string) ($row->subtype ?? '')) . '"'
        . ' placeholder="melee, tir, jet, walls, routes…"></div>'
        . '<div class="form-group"><label>Race (objet racial)</label>'
        . '<input type="text" class="form-control" name="race" value="' . e((string) ($row->race ?? '')) . '"></div>'
        . '</div>'

        . '<div class="col-md-3"><h5>Flags</h5>' . $flagBoxes
        . '<div class="form-group mt-2"><label>Élément</label>'
        . '<input type="text" class="form-control" name="element" value="' . e((string) $row->element) . '"></div>'
        . '<div class="form-group"><label>Sort lié (consommation)</label>'
        . '<input type="text" class="form-control" name="spell" value="' . e((string) $row->spell) . '"></div>'
        . '<div class="form-group"><label>Exotique (race)</label>'
        . '<input type="text" class="form-control" name="exotique" value="' . e((string) $row->exotique) . '"></div>'
        . '<h5>Usure <small class="text-muted">(par tour)</small></h5>'
        . '<div class="form-group">' . $triggerBoxes . '</div>'
        . '<div class="form-group"><label>Points perdus par tour armé</label>'
        . '<input type="number" min="0" class="form-control" name="wear_rate" value="' . (int) $row->wear_rate . '">'
        . '<small class="text-muted">0 = ne s\'use jamais.</small></div>'
        . '</div>'

        . '<div class="col-md-3"><h5>Caractéristiques</h5><div class="row">' . $caracInputs . '</div></div>'

        . '<div class="col-md-3"><h5>Spéciaux</h5><div class="row">' . $specialInputs . '</div>'
        . '<div class="form-group"><label>Munitions (noms, séparés par des virgules)</label>'
        . '<input type="text" class="form-control" name="munitions" value="' . e($munitions) . '"></div>'
        . '<div class="form-group"><label>Effets ajoutés (JSON)</label>'
        . '<textarea class="form-control" name="add_effects" rows="2">' . e((string) ($row->add_effects ?? '')) . '</textarea></div>'
        . '<div class="form-group"><label>Interdits (JSON)</label>'
        . '<textarea class="form-control" name="forbid" rows="2">' . e((string) ($row->forbid ?? '')) . '</textarea></div>'
        . '<div class="form-group"><label>Extra (JSON, clés héritées — sans perte)</label>'
        . '<textarea class="form-control" name="extra" rows="2">' . e((string) ($row->extra ?? '')) . '</textarea></div>'
        . '</div>'

        . '</div>'
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
