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

/**
 * Badge du type d'objet — le geste « Utiliser » qu'il implique se lit
 * d'un coup d'œil dans la liste.
 */
function item_type_badge(string $type): string
{
    $styles = [
        'equipement' => ['Équipement', 'primary', 'Se porte (1 Ae)'],
        'consommable' => ['Consommable', 'success', 'Se consomme (1 A)'],
        Item::TYPE_CONSTRUCTIBLE => ['Constructible', 'warning',
            'Système actuel : se bâtit depuis l\'inventaire en vraie entité bâtiment (PV, porte, fiche)'],
        Item::TYPE_STRUCTURE => ['Structure (legacy)', 'warning',
            'Chemin hérité build.php : pose un mur muet (map_walls) — coexiste jusqu\'à la migration murs→structures'],
        'matiere' => ['Matière', 'secondary', 'Matériau d\'artisanat, sans usage direct'],
    ];
    [$label, $style, $help] = $styles[$type] ?? [($type !== '' ? ucfirst($type) : '—'), 'light', ''];

    return '<span class="badge badge-' . $style . '"' . ($help !== '' ? ' title="' . e($help) . '"' : '') . '>'
        . e($label) . '</span>';
}

/** @param array<int, object> $items */
function items_render_list(array $items): string
{
    $inDb = 0;
    $typeCounts = [];
    $rows = [];
    foreach ($items as $row) {
        $type = (string) ($row->type ?? '');
        if ($type === '' && empty($row->stats_in_db)) {
            // Stats encore en JSON legacy : le type y dort — lecture
            // directe (pas Item::get_data, dont le repli ÉCRIT un json).
            $legacyJson = json()->decode('items', $row->name);
            $type = is_object($legacyJson) ? (string) ($legacyJson->type ?? '') : '';
        }
        $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
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

        $rows[] = '<tr data-type="' . e($type) . '">'
            . '<td><img src="/img/items/' . e($row->name) . '_mini.webp" style="max-height:24px"'
            . ' onerror="this.style.display=\'none\'" alt=""> <code>' . e($row->name) . '</code>' . $mapThumbs . '</td>'
            . '<td>' . item_type_badge($type) . '</td>'
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
        . items_type_filter_bar($typeCounts)
        . '<input type="text" class="form-control mb-2" id="items-filter" placeholder="filtrer…"'
        . ' onkeyup="itemsApplyFilters();">'
        . renderTable(
            ['Objet', 'Type', 'Stats', 'Flags', 'Élément', 'Sort lié', 'Usure', ''],
            $rows,
            'class="table table-sm table-striped align-middle" id="items-table"'
        );
}

/**
 * Barre de filtre par type : un bouton par type présent au catalogue
 * (avec son compte), combinable avec la recherche texte — une seule
 * fonction JS applique les deux critères.
 *
 * @param array<string, int> $typeCounts type => nombre d'objets
 */
function items_type_filter_bar(array $typeCounts): string
{
    ksort($typeCounts);

    $buttons = '<button type="button" class="btn btn-sm btn-outline-dark active" data-type-filter="*">'
        . 'Tous (' . array_sum($typeCounts) . ')</button>';
    foreach ($typeCounts as $type => $count) {
        // « Sans type » filtre sur la chaîne vide — distinct de « Tous » (*).
        $buttons .= ' <button type="button" class="btn btn-sm btn-outline-dark" data-type-filter="' . e($type) . '">'
            . ($type !== '' ? e(ucfirst($type)) : 'Sans type') . ' (' . $count . ')</button>';
    }

    $script = '<script>
    /* Filtre combiné type + texte : chaque critère élimine, la ligne
       survit si elle passe les deux. */
    window.itemsTypeFilter = "*";
    function itemsApplyFilters() {
        var q = document.getElementById("items-filter").value.toLowerCase();
        document.querySelectorAll("#items-table tbody tr").forEach(function (tr) {
            var typeOk = window.itemsTypeFilter === "*" || tr.dataset.type === window.itemsTypeFilter;
            var textOk = q === "" || tr.textContent.toLowerCase().indexOf(q) !== -1;
            tr.style.display = (typeOk && textOk) ? "" : "none";
        });
    }
    document.querySelectorAll("[data-type-filter]").forEach(function (btn) {
        btn.addEventListener("click", function () {
            window.itemsTypeFilter = btn.dataset.typeFilter;
            document.querySelectorAll("[data-type-filter]").forEach(function (b) { b.classList.remove("active"); });
            btn.classList.add("active");
            itemsApplyFilters();
        });
    });
    </script>';

    return '<div class="mb-2">' . $buttons . '</div>' . $script;
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
        ? formField('Nom technique',
            formInput('new_name', '', 'required maxlength="255" pattern="[a-z0-9_/-]+" placeholder="ex : hache_de_guerre"'),
            'form-group',
            'Minuscules, chiffres, _ / - ; sert de clé pour les images (img/items/{nom}_mini.webp) et les bundles.')
        : '<input type="hidden" name="id" value="' . (int) $row->id . '">';

    $identite = '<h5>Identité</h5>'
        . formField('Description', formTextarea('text', (string) ($row->text ?? ''), 5),
            'form-group', 'Texte montré au joueur (aperçu d\'inventaire, marchand).')
        . formField('Prix', formInput('price', (string) (int) ($row->price ?? 1), 'type="number" min="0"'),
            'form-group', 'Prix de référence du marchand et des contrats.')
        . formField('Type',
            formInput('type', (string) ($row->type ?? ''),
                'list="item-types" placeholder="equipement, consommable, ' . Item::TYPE_CONSTRUCTIBLE . '…"'),
            'form-group',
            'Décide du geste « Utiliser » : <b>equipement</b> se porte (1 Ae),'
            . ' <b>consommable</b> se consomme (1 A),'
            . ' <b>' . Item::TYPE_CONSTRUCTIBLE . '</b>/<b>' . Item::TYPE_STRUCTURE . '</b> se bâtit sur la carte.'
            . ' Un objet sans usage (matériau…) a son bouton grisé en jeu.')
        . renderDatalist('item-types', [
            'equipement' => '', 'consommable' => '',
            Item::TYPE_CONSTRUCTIBLE => '', Item::TYPE_STRUCTURE => '',
        ])
        . formField('Emplacement',
            formSelect('emplacement',
                array_combine(ITEM_EMPLACEMENT_FORMAT, ITEM_EMPLACEMENT_FORMAT),
                ((string) ($row->emplacement ?? '')) !== '' ? (string) $row->emplacement : null,
                '— aucun —'),
            'form-group',
            'Où l\'objet se porte — tout objet AVEC emplacement devient équipable, quel que soit son type.')
        . formField('Sous-type',
            formInput('subtype', (string) ($row->subtype ?? ''), 'placeholder="melee, tir, jet, walls, routes…"'),
            'form-group',
            'Catégorie d\'arme pour le combat (melee, tir, jet, bouclier) ou de pose carte (walls, routes…).')
        . formField('Race (objet racial)', formInput('race', (string) ($row->race ?? '')),
            'form-group', 'Code de race (nain, elfe…) : colore le nom de l\'objet — vide : commun.');

    $flags = '<h5>Flags</h5>' . $flagBoxes
        . formField('Élément', formInput('element', (string) $row->element),
            'form-group mt-2', 'Élément porté par l\'objet (feu, eau…) — marque le nom et joue avec les règles élémentaires.')
        . formField('Sort lié', formInput('spell', (string) $row->spell),
            'form-group',
            'Objet à sort intégré : le sort est affiché sur l\'objet'
            . ' (l\'apprentissage des sorts passe par les écoles de guerre).')
        . formField('Exotique (race)', formInput('exotique', (string) $row->exotique),
            'form-group', 'Code de race : SEULE cette race peut équiper l\'objet.')
        . '<h5>Usure <small class="text-muted">(par tour)</small></h5>'
        . '<div class="form-group">' . $triggerBoxes . '</div>'
        . formField('Points perdus par tour armé',
            formInput('wear_rate', (string) (int) $row->wear_rate, 'type="number" min="0"'),
            'form-group', '0 = ne s\'use jamais.')
        . formField('Durabilité max (vie de l\'objet)',
            formInput('durability_max', (string) (int) ($row->durability_max ?? 100), 'type="number" min="1"'),
            'form-group', 'Vie de départ des exemplaires individualisés — les instances déjà nées gardent la leur.');

    $caracsCol = '<h5>Caractéristiques</h5>'
        . '<p class="text-muted mb-2" style="font-size:88%">Double lecture selon le type :'
        . ' sur un <b>équipement</b>, modificateurs du porteur tant que l\'objet est porté ;'
        . ' sur un <b>consommable</b>, quantités RENDUES à la consommation (PV, PM, MVT, A, AE).</p>'
        . '<div class="row">' . $caracInputs . '</div>';

    $speciaux = '<h5>Spéciaux</h5>'
        . '<p class="text-muted mb-2" style="font-size:88%">Modificateurs du porteur — sur un consommable,'
        . ' PR / PF / Malus s\'appliquent aussi à la consommation.</p>'
        . '<div class="row">' . $specialInputs . '</div>'
        . formField('Munitions (noms, séparés par des virgules)', formInput('munitions', $munitions),
            'form-group', 'Arme de tir : les objets-munitions qu\'elle accepte.')

        . '<h5>À la consommation</h5>'
        . item_effect_multiselect('effets_appliques', $effectsApplied, 'Effets appliqués',
            'Posés sur le buveur à la consommation (potion de poison, de régénération…). Ctrl+clic pour plusieurs.')
        . item_effect_multiselect('effets_retires', $effectsRemoved, 'Effets retirés',
            'Purgés du buveur à la consommation (antidote…). Catalogue : admin → Effets.')

        . '<h5>JSON avancé</h5>'
        . formField('Effets d\'arme au coup porté (JSON)',
            formTextarea('add_effects', (string) ($row->add_effects ?? ''), 2),
            'form-group',
            'Arme équipée : effets posés quand le coup touche —'
            . ' <code>[{"name":"poison","on":"target","duration":86400}]</code>.')
        . formField('Interdits (JSON)', formTextarea('forbid', (string) ($row->forbid ?? ''), 2),
            'form-group', '<code>{"market":1}</code> : invendable au marché et aux contrats (ex : l\'or).')
        . formField('Extra (JSON, clés héritées — sans perte)', formTextarea('extra', $extraDisplay, 2),
            'form-group',
            'Clés historiques diverses, conservées telles quelles. Les effets de'
            . ' consommation s\'éditent au-dessus, plus dans cette zone.');

    $body = '<form method="post" action="/admin/items-save.php?action=' . ($isNew ? 'create' : 'update') . '">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
        . $nameField
        . ($isNew ? '' : $notInDb . $imagesPanel)
        . '<div class="row">'
        . '<div class="col-md-3">' . $identite . '</div>'
        . '<div class="col-md-3">' . $flags . '</div>'
        . '<div class="col-md-3">' . $caracsCol . '</div>'
        . '<div class="col-md-3">' . $speciaux . '</div>'
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
