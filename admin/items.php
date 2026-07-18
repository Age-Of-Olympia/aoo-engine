<?php
/**
 * Items catalog management (admin dashboard → Objets).
 *
 * CRUD complet sur le catalogue : création (?action=new), édition de
 * toutes les colonnes (flags, usure, stats, caracs, JSON), export
 * bundle JSON par objet ou global (action-export.php?type=item) et
 * import via action-import.php — même cycle de vie que races,
 * factions, dialogues et plans.
 *
 * All mutations POST to items-save.php (CSRF-validated, PRG). This
 * page only renders. Access enforced by layout.php via
 * AdminMenuAccessService.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use Classes\Item;

/** Libellés UI des déclencheurs — les CLÉS viennent de Item::WEAR_TRIGGERS. */
const ITEM_WEAR_TRIGGER_LABELS = [
    'attack'  => 'Attaque (porter un coup)',
    'defense' => 'Défense (encaisser un coup)',
    'move'    => 'Déplacement',
    'usage'   => 'Utilisation',
];

/** Libellés UI des flags — les CLÉS viennent de Item::FLAG_KEYS. */
const ITEM_FLAG_LABELS = [
    'cursed' => 'Maudit (ne se lâche ni se déséquipe)',
    'enchanted' => 'Enchanté (ne casse pas)',
    'vorpal' => 'Vorpal',
    'is_bankable' => 'Stockable en banque',
    'is_deprecated' => 'Déprécié',
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
    $inDb = 0;
    $rows = '';
    foreach ($items as $row) {
        $statsBadge = !empty($row->stats_in_db)
            ? '<span class="badge badge-success" title="Stats en base — édition complète">BDD</span>'
            : '<span class="badge badge-secondary" title="Stats encore dans le JSON legacy — seed à rejouer, ou enregistrer ici pour basculer">JSON</span>';
        $inDb += !empty($row->stats_in_db) ? 1 : 0;

        // Vignettes carte (mur / mur brisé) quand elles existent — repérage
        // rapide des structures constructibles et de leur état visuel.
        $mapThumbs = '';
        foreach (['' => '', '_broken' => 'brisé'] as $suffix => $title) {
            $wallPath = 'img/walls/' . $row->name . $suffix . '.png';
            if (is_file($_SERVER['DOCUMENT_ROOT'] . '/' . $wallPath)) {
                $mapThumbs .= ' <img src="/' . e($wallPath) . '" style="max-height:20px" title="carte ' . $title . '" alt="">';
            }
        }

        $rows .= '<tr>'
            . '<td><img src="/img/items/' . e($row->name) . '_mini.webp" style="max-height:24px"'
            . ' onerror="this.style.display=\'none\'" alt=""> <code>' . e($row->name) . '</code>' . $mapThumbs . '</td>'
            . '<td>' . $statsBadge . '</td>'
            . '<td>' . item_flag_badges($row) . '</td>'
            . '<td>' . ($row->element !== '' && $row->element !== null ? e($row->element) : '<span class="text-muted">—</span>') . '</td>'
            . '<td>' . ($row->spell !== '' && $row->spell !== null ? e($row->spell) : '<span class="text-muted">—</span>') . '</td>'
            . '<td>' . item_wear_cell($row) . '</td>'
            . '<td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="/admin/items.php?action=edit&id=' . (int) $row->id . '">Éditer</a> '
            . '<a class="btn btn-sm btn-outline-secondary" title="Exporter le bundle JSON"'
            . ' href="/admin/action-export.php?type=item&name=' . e(urlencode($row->name)) . '">JSON</a></td>'
            . '</tr>';
    }

    $toolbar = '<p><a class="btn btn-primary" href="/admin/items.php?action=new">Nouvel objet</a> '
        . '<a class="btn btn-outline-secondary" href="/admin/action-export.php?type=item">Exporter tout (JSON)</a> '
        . '<a class="btn btn-outline-secondary" href="/admin/action-import.php">Importer</a></p>';

    return $toolbar . '<p class="text-muted"><b>' . $inDb . '</b>/' . count($items) . ' objets ont leurs stats en base'
        . ' (édition complète ici). Les autres attendent leur JSON — sur cet environnement seuls les fichiers'
        . ' présents dans <code>datas/*/items/</code> ont pu être recopiés ; en prod,'
        . ' <a href="/admin/item-seed.php">rejouer le seed</a> les basculera tous.'
        . ' Enregistrer un objet « JSON » depuis cette page fait aussi de la base sa source.</p>'
        . '<input type="text" class="form-control mb-2" id="items-filter" placeholder="filtrer…"'
        . ' onkeyup="var q=this.value.toLowerCase();document.querySelectorAll(\'#items-table tbody tr\').forEach('
        . 'function(tr){tr.style.display = tr.textContent.toLowerCase().indexOf(q) === -1 ? \'none\' : \'\';});">'
        . '<div class="table-responsive"><table class="table table-sm table-striped align-middle" id="items-table">'
        . '<thead><tr><th>Objet</th><th>Stats</th><th>Flags</th><th>Élément</th><th>Sort lié</th><th>Usure</th><th></th></tr></thead>'
        . '<tbody>' . $rows . '</tbody></table></div>';
}

/**
 * <select multiple> d'effets de consommation, options depuis le
 * catalogue (admin → Effets). Une valeur enregistrée qui n'y est plus
 * reste proposée, marquée ⚠, pour ne pas être écrasée en silence.
 *
 * @param string[] $selected
 */
function item_effect_multiselect(string $field, array $selected, string $label, string $hint): string
{
    $known = (new \App\Service\EffectService())->getGameplayEffectNames();

    $options = '';
    foreach (array_diff($selected, $known) as $orphan) {
        $options .= '<option value="' . e($orphan) . '" selected>⚠ inconnue : ' . e($orphan) . '</option>';
    }
    foreach ($known as $effectName) {
        $options .= '<option value="' . e($effectName) . '"'
            . (in_array($effectName, $selected, true) ? ' selected' : '') . '>' . e($effectName) . '</option>';
    }

    return '<div class="form-group"><label>' . $label . '</label>'
        . '<select name="' . $field . '[]" class="form-control" multiple size="5">' . $options . '</select>'
        . '<small class="text-muted">' . $hint . '</small></div>';
}

function items_render_edit(object $row, string $csrfToken): string
{
    $triggers = array_filter(array_map('trim', explode(',', (string) $row->wear_triggers)));

    $triggerBoxes = '';
    foreach (Item::WEAR_TRIGGERS as $key) {
        $triggerBoxes .= '<label class="mr-3"><input type="checkbox" name="wear_triggers[]" value="' . $key . '" '
            . checked(in_array($key, $triggers, true)) . '> ' . ITEM_WEAR_TRIGGER_LABELS[$key] . '</label> ';
    }

    $flagBoxes = '';
    foreach (Item::FLAG_KEYS as $col) {
        $flagBoxes .= '<label class="d-block"><input type="checkbox" name="' . $col . '" '
            . checked(!empty($row->$col)) . '> ' . ITEM_FLAG_LABELS[$col] . '</label>';
    }

    // Stats — pleinement éditables depuis la migration JSON→DB.
    $notInDb = empty($row->stats_in_db)
        ? '<div class="alert alert-warning">Stats pas encore migrées pour cet objet (JSON absent de cet'
          . ' environnement) — <b>enregistrer les stats ici fera de la base sa source</b>.'
          . ' En prod, rejouer le seed : <a href="/admin/item-seed.php">item-seed</a>.</div>'
        : '';

    $emplacementOptions = renderSelectOptions(
        array_combine(ITEM_EMPLACEMENT_FORMAT, ITEM_EMPLACEMENT_FORMAT),
        ((string) ($row->emplacement ?? '')) !== '' ? (string) $row->emplacement : null,
        '— aucun —'
    );

    $caracInputs = '';
    foreach (\App\Enum\Caracs::KEYS as $k) {
        $caracInputs .= '<div class="col-3 form-group"><label>' . strtoupper($k) . '</label>'
            . '<input type="number" class="form-control form-control-sm" name="' . $k . '" value="' . (int) ($row->$k ?? 0) . '"></div>';
    }

    $specialLabels = [
        'esquive' => 'Esquive', 'pr' => 'PR', 'pf' => 'PF', 'malus' => 'Malus',
        'spellMalus' => 'Malus de sort', 'fixedF' => 'F fixée', 'mDamage' => 'Dégâts magiques',
        'demolition' => 'Démolition', 'craftedByN' => 'Craft (n)', 'lootChance' => 'Chance de loot (%)',
    ];
    $specialInputs = '';
    foreach (Item::SPECIAL_KEYS as $col) {
        $specialInputs .= '<div class="col-4 form-group"><label>' . $specialLabels[$col] . '</label>'
            . '<input type="number" class="form-control form-control-sm" name="' . $col . '" value="' . (int) ($row->$col ?? 0) . '"></div>';
    }

    $munitions = !empty($row->munitions) ? implode(', ', (array) json_decode((string) $row->munitions, true)) : '';

    /* Effets de consommation : la clé `effet` d'extra, éclatée en deux
     * sélecteurs (appliqués / retirés, le préfixe « - » historique) —
     * le textarea Extra n'affiche plus cette clé, elle se recompose à
     * l'enregistrement. */
    $extraJson = json_decode((string) ($row->extra ?? ''));
    $consumeEffects = (is_object($extraJson) && !empty($extraJson->effet)) ? array_map('strval', (array) $extraJson->effet) : [];
    $effectsApplied = array_values(array_filter($consumeEffects, static fn (string $e): bool => !str_starts_with($e, '-')));
    $effectsRemoved = array_values(array_map(
        static fn (string $e): string => substr($e, 1),
        array_filter($consumeEffects, static fn (string $e): bool => str_starts_with($e, '-'))
    ));
    if (is_object($extraJson)) {
        unset($extraJson->effet);
    }
    $extraDisplay = (is_object($extraJson) && get_object_vars($extraJson) !== [])
        ? json_encode($extraJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : (is_object($extraJson) || $extraJson === null ? '' : (string) $row->extra);

    // Toutes les représentations visuelles de l'objet, manquantes incluses —
    // dont l'image « brisé » des structures de carte (bascule à mi-PV,
    // destroy.php) : voir d'un coup d'œil ce qui existe et ce qui manque.
    $imagesPanel = '';
    foreach ([
        'img/items/' . $row->name . '.webp' => 'Objet',
        'img/items/' . $row->name . '_mini.webp' => 'Vignette',
        'img/walls/' . $row->name . '.png' => 'Sur la carte',
        'img/walls/' . $row->name . '_broken.png' => 'Sur la carte — brisé',
    ] as $path => $label) {
        $exists = is_file($_SERVER['DOCUMENT_ROOT'] . '/' . $path);
        $imagesPanel .= '<div class="text-center d-inline-block m-1" style="width:110px;vertical-align:top;">'
            . ($exists
                ? '<img src="/' . e($path) . '" style="max-width:100px;max-height:80px;" alt="">'
                : '<div style="width:100px;height:80px;display:inline-flex;align-items:center;justify-content:center;'
                  . 'border:1px dashed #bbb;color:#999;font-size:11px;">manquante</div>')
            . '<div><small>' . $label . ($exists ? '' : ' <span class="text-muted">(repli : image par défaut)</span>') . '</small></div>'
            . '</div>';
    }
    $imagesPanel = '<div class="card mb-3"><div class="card-header">Images</div>'
        . '<div class="card-body py-2">' . $imagesPanel . '</div></div>';

    // Création : pas encore de ligne en base — champ nom éditable,
    // POST vers action=create, pas de panneau d'images (elles portent le nom).
    $isNew = (int) $row->id === 0;
    $nameField = $isNew
        ? '<div class="form-group"><label>Nom technique</label>'
          . '<input type="text" class="form-control" name="new_name" required maxlength="255"'
          . ' pattern="[a-z0-9_/-]+" placeholder="ex : hache_de_guerre">'
          . '<small class="text-muted">Minuscules, chiffres, _ / - ; sert de clé pour les images'
          . ' (img/items/{nom}_mini.webp) et les bundles.</small></div>'
        : '<input type="hidden" name="id" value="' . (int) $row->id . '">';

    $body = '<form method="post" action="/admin/items-save.php?action=' . ($isNew ? 'create' : 'update') . '">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
        . $nameField
        . ($isNew ? '' : $notInDb . $imagesPanel)
        . '<div class="row">'

        . '<div class="col-md-3"><h5>Identité</h5>'
        . '<div class="form-group"><label>Description</label>'
        . '<textarea class="form-control" name="text" rows="5">' . e((string) ($row->text ?? '')) . '</textarea>'
        . '<small class="text-muted">Texte montré au joueur (aperçu d\'inventaire, marchand).</small></div>'
        . '<div class="form-group"><label>Prix</label>'
        . '<input type="number" min="0" class="form-control" name="price" value="' . (int) ($row->price ?? 1) . '">'
        . '<small class="text-muted">Prix de référence du marchand et des contrats.</small></div>'
        . '<div class="form-group"><label>Type</label>'
        . '<input type="text" class="form-control" name="type" list="item-types" value="' . e((string) ($row->type ?? '')) . '"'
        . ' placeholder="equipement, consommable, ' . Item::TYPE_CONSTRUCTIBLE . '…">'
        . '<small class="text-muted">Décide du geste « Utiliser » : <b>equipement</b> se porte (1 Ae),'
        . ' <b>consommable</b> se consomme (1 A),'
        . ' <b>' . Item::TYPE_CONSTRUCTIBLE . '</b>/<b>' . Item::TYPE_STRUCTURE . '</b> se bâtit sur la carte.'
        . ' Un objet sans usage (matériau…) a son bouton grisé en jeu.</small></div>'
        . '<datalist id="item-types">'
        . '<option value="equipement"><option value="consommable">'
        . '<option value="' . Item::TYPE_CONSTRUCTIBLE . '"><option value="' . Item::TYPE_STRUCTURE . '">'
        . '</datalist>'
        . '<div class="form-group"><label>Emplacement</label>'
        . '<select name="emplacement" class="form-control">' . $emplacementOptions . '</select>'
        . '<small class="text-muted">Où l\'objet se porte — tout objet AVEC emplacement devient équipable,'
        . ' quel que soit son type.</small></div>'
        . '<div class="form-group"><label>Sous-type</label>'
        . '<input type="text" class="form-control" name="subtype" value="' . e((string) ($row->subtype ?? '')) . '"'
        . ' placeholder="melee, tir, jet, walls, routes…">'
        . '<small class="text-muted">Catégorie d\'arme pour le combat (melee, tir, jet, bouclier)'
        . ' ou de pose carte (walls, routes…).</small></div>'
        . '<div class="form-group"><label>Race (objet racial)</label>'
        . '<input type="text" class="form-control" name="race" value="' . e((string) ($row->race ?? '')) . '">'
        . '<small class="text-muted">Code de race (nain, elfe…) : colore le nom de l\'objet — vide : commun.</small></div>'
        . '</div>'

        . '<div class="col-md-3"><h5>Flags</h5>' . $flagBoxes
        . '<div class="form-group mt-2"><label>Élément</label>'
        . '<input type="text" class="form-control" name="element" value="' . e((string) $row->element) . '">'
        . '<small class="text-muted">Élément porté par l\'objet (feu, eau…) — marque le nom et joue'
        . ' avec les règles élémentaires.</small></div>'
        . '<div class="form-group"><label>Sort lié</label>'
        . '<input type="text" class="form-control" name="spell" value="' . e((string) $row->spell) . '">'
        . '<small class="text-muted">Objet à sort intégré : le sort est affiché sur l\'objet'
        . ' (l\'apprentissage des sorts passe par les écoles de guerre).</small></div>'
        . '<div class="form-group"><label>Exotique (race)</label>'
        . '<input type="text" class="form-control" name="exotique" value="' . e((string) $row->exotique) . '">'
        . '<small class="text-muted">Code de race : SEULE cette race peut équiper l\'objet.</small></div>'
        . '<h5>Usure <small class="text-muted">(par tour)</small></h5>'
        . '<div class="form-group">' . $triggerBoxes . '</div>'
        . '<div class="form-group"><label>Points perdus par tour armé</label>'
        . '<input type="number" min="0" class="form-control" name="wear_rate" value="' . (int) $row->wear_rate . '">'
        . '<small class="text-muted">0 = ne s\'use jamais.</small></div>'
        . '<div class="form-group"><label>Durabilité max (vie de l\'objet)</label>'
        . '<input type="number" min="1" class="form-control" name="durability_max" value="' . (int) ($row->durability_max ?? 100) . '">'
        . '<small class="text-muted">Vie de départ des exemplaires individualisés — les instances déjà nées gardent la leur.</small></div>'
        . '</div>'

        . '<div class="col-md-3"><h5>Caractéristiques</h5>'
        . '<p class="text-muted mb-2" style="font-size:88%">Double lecture selon le type :'
        . ' sur un <b>équipement</b>, modificateurs du porteur tant que l\'objet est porté ;'
        . ' sur un <b>consommable</b>, quantités RENDUES à la consommation (PV, PM, MVT, A, AE).</p>'
        . '<div class="row">' . $caracInputs . '</div></div>'

        . '<div class="col-md-3"><h5>Spéciaux</h5>'
        . '<p class="text-muted mb-2" style="font-size:88%">Modificateurs du porteur — sur un consommable,'
        . ' PR / PF / Malus s\'appliquent aussi à la consommation.</p>'
        . '<div class="row">' . $specialInputs . '</div>'
        . '<div class="form-group"><label>Munitions (noms, séparés par des virgules)</label>'
        . '<input type="text" class="form-control" name="munitions" value="' . e($munitions) . '">'
        . '<small class="text-muted">Arme de tir : les objets-munitions qu\'elle accepte.</small></div>'

        . '<h5>À la consommation</h5>'
        . item_effect_multiselect('effets_appliques', $effectsApplied, 'Effets appliqués',
            'Posés sur le buveur à la consommation (potion de poison, de régénération…). Ctrl+clic pour plusieurs.')
        . item_effect_multiselect('effets_retires', $effectsRemoved, 'Effets retirés',
            'Purgés du buveur à la consommation (antidote…). Catalogue : admin → Effets.')

        . '<h5>JSON avancé</h5>'
        . '<div class="form-group"><label>Effets d\'arme au coup porté (JSON)</label>'
        . '<textarea class="form-control" name="add_effects" rows="2">' . e((string) ($row->add_effects ?? '')) . '</textarea>'
        . '<small class="text-muted">Arme équipée : effets posés quand le coup touche —'
        . ' <code>[{"name":"poison","on":"target","duration":86400}]</code>.</small></div>'
        . '<div class="form-group"><label>Interdits (JSON)</label>'
        . '<textarea class="form-control" name="forbid" rows="2">' . e((string) ($row->forbid ?? '')) . '</textarea>'
        . '<small class="text-muted"><code>{"market":1}</code> : invendable au marché et aux contrats (ex : l\'or).</small></div>'
        . '<div class="form-group"><label>Extra (JSON, clés héritées — sans perte)</label>'
        . '<textarea class="form-control" name="extra" rows="2">' . e($extraDisplay) . '</textarea>'
        . '<small class="text-muted">Clés historiques diverses, conservées telles quelles. Les effets de'
        . ' consommation s\'éditent au-dessus, plus dans cette zone.</small></div>'
        . '</div>'

        . '</div>'
        . '<button class="btn btn-primary" type="submit">Enregistrer</button> '
        . '<a class="btn btn-secondary" href="/admin/items.php">Retour</a>'
        . '</form>';

    $header = $isNew
        ? 'Nouvel objet'
        : e(ucfirst($row->name)) . ' <code>#' . (int) $row->id . '</code>';

    return '<div class="card"><div class="card-header">' . $header . '</div>'
        . '<div class="card-body">' . $body . '</div></div>';
}

/* -------------------------------------------------------------------------
 * Route
 * ---------------------------------------------------------------------- */
$csrfToken = (new CsrfProtectionService())->generateToken();
$action = $_GET['action'] ?? 'list';

if ($action === 'new') {
    $blank = (object) array_fill_keys(array_merge(
        ['name', 'element', 'spell', 'exotique', 'wear_triggers', 'munitions'],
        \App\Service\ItemStatsSeeder::STRING_KEYS,
        Item::JSON_COLUMNS
    ), '');
    foreach (array_merge(
        ['id', 'wear_rate', 'price', 'stats_in_db'],
        Item::FLAG_KEYS,
        Item::SPECIAL_KEYS,
        \App\Enum\Caracs::KEYS
    ) as $k) {
        $blank->$k = 0;
    }
    $blank->is_bankable = 1;
    $blank->price = 1;
    $blank->durability_max = 100;
    $content = items_render_edit($blank, $csrfToken);
} elseif ($action === 'edit') {
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
