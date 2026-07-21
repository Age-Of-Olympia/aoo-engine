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

/**
 * Joueurs distincts possédant chaque objet (inventaire ∪ banque) — la
 * colonne « Joueurs » de la liste, et le garde-fou du bouton Supprimer.
 *
 * @return array<int, int> item_id => nombre de joueurs
 */
function items_owner_counts(): array
{
    $counts = [];
    $res = (new \Classes\Db())->exe(
        'SELECT item_id, COUNT(DISTINCT player_id) AS n FROM (
            SELECT item_id, player_id FROM players_items
            UNION
            SELECT item_id, player_id FROM players_items_bank
         ) AS u GROUP BY item_id'
    );
    while ($row = $res->fetch_object()) {
        $counts[(int) $row->item_id] = (int) $row->n;
    }
    return $counts;
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
        'structure' => ['Structure (ancien)', 'danger',
            'Ancien système de pose (build.php, supprimé) — plus aucun usage en jeu : à migrer en Constructible'],
        'matiere' => ['Matière', 'secondary', 'Matériau d\'artisanat, sans usage direct'],
    ];
    [$label, $style, $help] = $styles[$type] ?? [($type !== '' ? ucfirst($type) : '—'), 'light', ''];

    return '<span class="badge badge-' . $style . '"' . ($help !== '' ? ' title="' . e($help) . '"' : '') . '>'
        . e($label) . '</span>';
}

/** @param array<int, object> $items */
/**
 * Sections renseignées alors que le type de l'objet ne les utilise pas
 * (usure sans equipement, effets sans consommable, pousses sans
 * graine) — la même détection que les volets « hors type » de la
 * fiche, pour marquer la liste comme la liste des plans marque les
 * siens.
 *
 * @return list<string> libellés des incohérences, vide si sain
 */
function items_type_inconsistencies(object $row, string $type): array
{
    $extra = json_decode((string) ($row->extra ?? ''));

    $issues = [];
    if (((int) ($row->wear_rate ?? 0) > 0 || trim((string) ($row->wear_triggers ?? '')) !== '')
        && $type !== 'equipement') {
        $issues[] = 'usure';
    }
    if (is_object($extra) && !empty($extra->effet) && $type !== 'consommable') {
        $issues[] = 'effets de consommation';
    }
    if (is_object($extra) && (!empty($extra->growTo) || !empty($extra->growZMin)) && $type !== 'graine') {
        $issues[] = 'pousses de graine';
    }

    return $issues;
}

function items_render_list(array $items, string $csrfToken): string
{
    $inDb = 0;
    $typeCounts = [];
    $rows = [];
    $ownerCounts = items_owner_counts();
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

        // Retardataire de l'ancien système de pose : conversion en un clic
        // (race structure + type constructible — l'action générique construire fait le reste).
        $migrateButton = '';
        if ($type === 'structure') {
            $migrateButton = ' <form method="post" style="display:inline"'
                . ' action="/admin/items-save.php?action=migrate-structure"'
                . ' onsubmit="return confirm(\'Migrer « ' . e($row->name) . ' » en constructible ?\');">'
                . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
                . '<input type="hidden" name="name" value="' . e($row->name) . '">'
                . '<button class="btn btn-sm btn-danger" title="Crée la race structure (défauts à affiner),'
                . ' passe l\'objet en constructible (l\'action générique construire fait le reste)">Migrer</button>'
                . '</form>';
        }

        // Supprimable seulement sans détenteur — les autres références
        // (instances, sol, recettes, marché) sont re-vérifiées côté
        // items-save.php avec le détail du refus.
        $owners = $ownerCounts[(int) $row->id] ?? 0;
        $deleteButton = ' <form method="post" style="display:inline"'
            . ' action="/admin/items-save.php?action=delete"'
            . ' onsubmit="return confirm(\'Supprimer définitivement « ' . e($row->name) . ' » du catalogue ?\');">'
            . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
            . '<input type="hidden" name="id" value="' . (int) $row->id . '">'
            . '<button class="btn btn-sm btn-outline-danger"'
            . ($owners > 0 ? ' disabled title="Encore détenu par ' . $owners . ' joueur(s)"' : ' title="Supprimer du catalogue"')
            . '>Supprimer</button>'
            . '</form>';

        // Incohérences type ↔ sections (mêmes règles que la fiche) :
        // badge orange dans la colonne Type, comme la liste des plans.
        $issues = items_type_inconsistencies($row, $type);
        $issuesBadge = $issues !== []
            ? ' <span class="badge" style="background-color:#f0ad4e;color:#fff;"'
                . ' title="Renseigné hors type : ' . e(implode(', ', $issues)) . ' — ouvrez la fiche pour corriger.">'
                . '<i class="fas fa-exclamation-triangle"></i> ' . count($issues) . '</span>'
            : '';

        $rows[] = '<tr data-type="' . e($type) . '">'
            . '<td><img src="/img/items/' . e($row->name) . '_mini.webp" style="max-height:24px"'
            . ' onerror="this.style.display=\'none\'" alt=""> <code>' . e($row->name) . '</code>' . $mapThumbs . '</td>'
            . '<td>' . item_type_badge($type) . $issuesBadge . '</td>'
            . '<td>' . $statsBadge . '</td>'
            . '<td>' . item_flag_badges($row) . '</td>'
            . '<td>' . ($row->element !== '' && $row->element !== null ? e($row->element) : '<span class="text-muted">—</span>') . '</td>'
            . '<td>' . ($row->spell !== '' && $row->spell !== null ? e($row->spell) : '<span class="text-muted">—</span>') . '</td>'
            . '<td>' . item_wear_cell($row) . '</td>'
            . '<td title="Joueurs distincts en possédant (inventaire ou banque)">'
            . ($owners > 0 ? '<strong>' . $owners . '</strong>' : '<span class="text-muted">0</span>') . '</td>'
            . '<td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="/admin/items.php?action=edit&id=' . (int) $row->id . '">Éditer</a> '
            . '<a class="btn btn-sm btn-outline-secondary" title="Exporter le bundle JSON"'
            . ' href="/admin/action-export.php?type=item&name=' . e(urlencode($row->name)) . '">JSON</a>'
            . $migrateButton . $deleteButton . '</td>'
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
            ['Objet', 'Type', 'Stats', 'Flags', 'Élément', 'Sort lié', 'Usure', 'Joueurs', ''],
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

/**
 * Lignes d'édition des pousses d'une graine (extra.growTo) : nom posé,
 * table de carte cible, taux « 1 chance sur N par jour » — plus une
 * ligne vierge pour l'ajout.
 *
 * @param list<object> $growTo entrées {name, table, chance}
 */
function items_grow_rows(array $growTo): string
{
    $rows = '<div class="d-flex gap-2 text-muted" style="font-size:85%;">'
        . '<span style="flex:2;">Pousse (nom posé sur la carte)</span>'
        . '<span style="flex:2;">Table cible</span>'
        . '<span style="flex:1;">1 chance sur…</span></div>';

    foreach (array_merge($growTo, [null]) as $entry) {
        $rows .= '<div class="d-flex gap-2 mb-1">'
            . '<input class="form-control form-control-sm" name="grow_name[]" style="flex:2;"'
            . ' value="' . e((string) ($entry->name ?? '')) . '" placeholder="ex : arbre1">'
            . '<input class="form-control form-control-sm" name="grow_table[]" list="grow-tables" style="flex:2;"'
            . ' value="' . e((string) ($entry->table ?? '')) . '" placeholder="plants, resources…">'
            . '<input class="form-control form-control-sm" type="number" min="1" name="grow_chance[]" style="flex:1;"'
            . ' value="' . (isset($entry->chance) ? (int) $entry->chance : '') . '" placeholder="N">'
            . '</div>';
    }

    return '<div class="form-group">' . $rows
        . renderDatalist('grow-tables', ['plants' => '', 'resources' => '', 'foregrounds' => ''])
        . '<small class="text-muted">La pousse est insérée dans <code>map_&lt;table&gt;</code> sous ce nom'
        . ' — le nom doit exister dans la couche visée (image et PV).</small></div>';
}

/**
 * Section repliable du formulaire d'édition : le titre porte un digest
 * toujours visible de ce qui est configuré, le détail ne s'ouvre que
 * quand il concerne l'objet — l'écran ne déplie que le pertinent, le
 * reste demeure accessible d'un clic (retour relecture : « beaucoup de
 * sections, beaucoup de champs, tout empilé »).
 */
/**
 * @param string|null $forType section liée à un type d'objet : le
 *        sélecteur Type l'ouvre quand il prend cette valeur et la
 *        replie/estompe sinon (les champs restent soumis et éditables)
 * @param bool $filled la section liée porte des valeurs — renseignée
 *        mais hors type, elle reste dépliée et affiche l'avertissement
 *        « hors type » (même convention que les warnings des plans)
 *        au lieu de s'estomper : une incohérence se montre, ne se
 *        cache pas
 * @param bool $warnNow état initial de l'avertissement (avant JS)
 */
function items_edit_section(string $title, string $digest, bool $open, string $html,
    ?string $forType = null, bool $filled = false, bool $warnNow = false): string
{
    $warnBadge = $forType !== null
        ? '<span class="badge item-section-warn" style="background-color:#f0ad4e;color:#fff;"'
            . ($warnNow ? '' : ' hidden')
            . ' title="Section renseignée alors que le type de l\'objet ne l\'utilise pas'
            . ' — nettoyez-la ou changez le type.">'
            . '<i class="fas fa-exclamation-triangle"></i> hors type</span>'
        : '';

    return '<details class="item-section"' . ($open || $warnNow ? ' open' : '')
        . ($forType !== null ? ' data-for-type="' . e($forType) . '" data-filled="' . ($filled ? '1' : '0') . '"' : '')
        . '>'
        . '<summary><span class="item-section-title">' . $title . '</span>'
        . $warnBadge
        . '<span class="item-section-digest">' . $digest . '</span></summary>'
        . '<div class="item-section-body">' . $html . '</div>'
        . '</details>';
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
    /* Graine : growTo (pousses possibles, table cible, 1 chance sur N par
     * jour — cron daily 20_grow_crops) et growZMin, éclatés en champs
     * dédiés — même contrat que les effets : le textarea Extra n'affiche
     * plus ces clés, elles se recomposent à l'enregistrement. */
    $growTo = (is_object($extraJson) && !empty($extraJson->growTo)) ? array_values((array) $extraJson->growTo) : [];
    $growZMin = (is_object($extraJson) && isset($extraJson->growZMin)) ? (int) $extraJson->growZMin : null;
    if (is_object($extraJson)) {
        unset($extraJson->effet, $extraJson->growTo, $extraJson->growZMin);
    }
    $extraDisplay = (is_object($extraJson) && get_object_vars($extraJson) !== [])
        ? json_encode($extraJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : (is_object($extraJson) || $extraJson === null ? '' : (string) $row->extra);

    // Toutes les représentations visuelles de l'objet, manquantes incluses —
    // dont l'image « brisé » des structures de carte (bascule à mi-PV,
    // destroy.php). Chaque emplacement s'importe ici même : l'image
    // téléversée est convertie au format attendu (webp/png), le nom de
    // fichier découle du nom technique. Les formulaires d'upload vivent
    // HORS du formulaire d'édition (attribut form — pas d'imbrication).
    $imagesPanel = '';
    $imageUploadForms = '';
    foreach ([
        'item' => ['img/items/' . $row->name . '.webp', 'Objet'],
        'mini' => ['img/items/' . $row->name . '_mini.webp', 'Vignette'],
        'wall' => ['img/walls/' . $row->name . '.png', 'Sur la carte'],
        'wall_broken' => ['img/walls/' . $row->name . '_broken.png', 'Sur la carte — brisé'],
    ] as $slot => [$path, $label]) {
        $exists = is_file($_SERVER['DOCUMENT_ROOT'] . '/' . $path);
        $formId = 'item-img-' . $slot;
        // Pas de .d-inline-block dans admin.css : les emplacements sont
        // des enfants d'un conteneur flex (ci-dessous), sinon ils
        // s'empilent en colonne au lieu d'occuper la largeur.
        $imagesPanel .= '<div class="text-center" style="width:130px;">'
            . ($exists
                ? '<img src="/' . e($path) . '?t=' . filemtime($_SERVER['DOCUMENT_ROOT'] . '/' . $path) . '" style="max-width:100px;max-height:80px;" alt="">'
                : '<div style="width:100px;height:80px;display:inline-flex;align-items:center;justify-content:center;'
                  . 'border:1px dashed #bbb;color:#999;font-size:11px;margin:auto;">manquante</div>')
            . '<div><small>' . $label . ($exists ? '' : ' <span class="text-muted">(repli : image par défaut)</span>') . '</small></div>'
            . '<input type="file" form="' . $formId . '" name="image_file" required'
            . ' accept=".png,.jpg,.jpeg,.webp,.gif" style="width:120px;font-size:10px;">'
            . '<button type="submit" form="' . $formId . '" class="btn btn-sm btn-outline-primary mt-1">Importer</button>'
            . '</div>';
        $imageUploadForms .= '<form id="' . $formId . '" method="post" enctype="multipart/form-data"'
            . ' action="/admin/items-save.php?action=upload-image">'
            . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
            . '<input type="hidden" name="id" value="' . (int) $row->id . '">'
            . '<input type="hidden" name="slot" value="' . e($slot) . '">'
            . '</form>';
    }
    $imagesPanel = '<div class="card mb-3"><div class="card-header">Images</div>'
        . '<div class="card-body py-2">'
        . '<div class="d-flex flex-wrap" style="gap: 8px; align-items: flex-start;">' . $imagesPanel . '</div>'
        . '<div><small class="text-muted">L\'image importée est convertie au format de l\'emplacement'
        . ' (webp objet/vignette, png carte) sans redimensionnement.</small></div>'
        . '</div></div>';

    // Création : pas encore de ligne en base — champ nom éditable,
    // POST vers action=create, pas de panneau d'images (elles portent le nom).
    $isNew = (int) $row->id === 0;
    $nameField = $isNew
        ? formField('Nom technique',
            formInput('new_name', '', 'required maxlength="255" pattern="[a-z0-9_/-]+" placeholder="ex : hache_de_guerre"'),
            'form-group',
            'Minuscules, chiffres, _ / - ; sert de clé pour les images (img/items/{nom}_mini.webp) et les bundles.')
        : '<input type="hidden" name="id" value="' . (int) $row->id . '">';

    /* Type : un sélecteur fermé — les gestes du jeu ne connaissent que
     * ces valeurs, la saisie libre produisait des types morts. Les
     * valeurs héritées encore en base (matiere, monnaie…) restent
     * choisissables ; une valeur inconnue survivrait via la sentinelle ⚠
     * de renderSelectOptions. Le choix pilote aussi les sections liées
     * (Usure / À la consommation / Graine) via data-for-type. */
    $typeValue = (string) ($row->type ?? '');
    $typeOptions = [
        'equipement' => 'equipement',
        'consommable' => 'consommable',
        Item::TYPE_CONSTRUCTIBLE => Item::TYPE_CONSTRUCTIBLE,
        'graine' => 'graine',
    ];
    $typesInDb = (new \Classes\Db())->exe("SELECT DISTINCT type FROM items WHERE type != '' ORDER BY type");
    while ($typeRow = $typesInDb->fetch_object()) {
        $typeOptions[$typeRow->type] ??= $typeRow->type;
    }

    $identite = formField('Description', formTextarea('text', (string) ($row->text ?? ''), 5),
            'form-group', 'Texte montré au joueur (aperçu d\'inventaire, marchand).')
        . formField('Prix', formInput('price', (string) (int) ($row->price ?? 1), 'type="number" min="0"'),
            'form-group', 'Prix de référence du marchand et des contrats.')
        . formField('Type',
            formSelect('type', $typeOptions, $typeValue !== '' ? $typeValue : null,
                '— sans usage direct (matériau…)',
                'class="form-control" id="item-type-select"'),
            'form-group',
            'Décide du geste « Utiliser » : <b>equipement</b> se porte (1 Ae),'
            . ' <b>consommable</b> se consomme (1 A),'
            . ' <b>' . Item::TYPE_CONSTRUCTIBLE . '</b> se bâtit sur la carte,'
            . ' <b>graine</b> germe une fois posée au sol.'
            . ' Le choix ouvre la section correspondante ci-contre.')
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

    $flags = $flagBoxes
        . formField('Élément', formInput('element', (string) $row->element),
            'form-group mt-2', 'Élément porté par l\'objet (feu, eau…) — marque le nom et joue avec les règles élémentaires.')
        . formField('Sort lié', formInput('spell', (string) $row->spell),
            'form-group',
            'Objet à sort intégré : le sort est affiché sur l\'objet'
            . ' (l\'apprentissage des sorts passe par les écoles de guerre).')
        . formField('Exotique (race)', formInput('exotique', (string) $row->exotique),
            'form-group', 'Code de race : SEULE cette race peut équiper l\'objet.');

    $usure = '<div class="form-group">' . $triggerBoxes . '</div>'
        . formField('Points perdus par tour armé',
            formInput('wear_rate', (string) (int) $row->wear_rate, 'type="number" min="0"'),
            'form-group', '0 = ne s\'use jamais.')
        . formField('Durabilité max (vie de l\'objet)',
            formInput('durability_max', (string) (int) ($row->durability_max ?? 100), 'type="number" min="1"'),
            'form-group', 'Vie de départ des exemplaires individualisés — les instances déjà nées gardent la leur.');

    $caracsCol = '<p class="text-muted mb-2" style="font-size:88%">Double lecture selon le type :'
        . ' sur un <b>équipement</b>, modificateurs du porteur tant que l\'objet est porté ;'
        . ' sur un <b>consommable</b>, quantités RENDUES à la consommation (PV, PM, MVT, A, AE).</p>'
        . '<div class="row">' . $caracInputs . '</div>';

    $speciaux = '<p class="text-muted mb-2" style="font-size:88%">Modificateurs du porteur — sur un consommable,'
        . ' PR / PF / Malus s\'appliquent aussi à la consommation.</p>'
        . '<div class="row">' . $specialInputs . '</div>'
        . formField('Munitions (noms, séparés par des virgules)', formInput('munitions', $munitions),
            'form-group', 'Arme de tir : les objets-munitions qu\'elle accepte.');

    $consommation = item_effect_multiselect('effets_appliques', $effectsApplied, 'Effets appliqués',
            'Posés sur le buveur à la consommation (potion de poison, de régénération…). Ctrl+clic pour plusieurs.')
        . item_effect_multiselect('effets_retires', $effectsRemoved, 'Effets retirés',
            'Purgés du buveur à la consommation (antidote…). Catalogue : admin → Effets.');

    $graine = '<p class="text-muted mb-2" style="font-size:88%">Objet de type <b>graine</b> posé seul au sol :'
        . ' chaque jour, une pousse est tirée au hasard parmi les lignes ci-dessous, puis germe avec'
        . ' 1 chance sur N — la graine disparaît alors. Ligne au nom vidé = supprimée ;'
        . ' la ligne vierge sert à en ajouter une.</p>'
        . items_grow_rows($growTo)
        . formField('Z minimum de pousse',
            formInput('grow_z_min', $growZMin === null ? '' : (string) $growZMin, 'type="number"'),
            'form-group', 'La graine ne germe qu\'à partir de ce niveau Z — vide : partout.');

    $jsonAvance = formField('Effets d\'arme au coup porté (JSON)',
            formTextarea('add_effects', (string) ($row->add_effects ?? ''), 2),
            'form-group',
            'Arme équipée : effets posés quand le coup touche —'
            . ' <code>[{"name":"poison","on":"target","duration":86400}]</code>.')
        . formField('Interdits (JSON)', formTextarea('forbid', (string) ($row->forbid ?? ''), 2),
            'form-group', '<code>{"market":1}</code> : invendable au marché et aux contrats (ex : l\'or).')
        . formField('Extra (JSON, clés héritées — sans perte)', formTextarea('extra', $extraDisplay, 2),
            'form-group',
            'Clés historiques diverses, conservées telles quelles. Les effets de'
            . ' consommation s\'éditent dans leur section, plus dans cette zone.');

    /* Digests des sections : ce que chaque volet configure, lisible sans
     * l'ouvrir — seuls les volets qui portent quelque chose (ou que le
     * type de l'objet appelle) démarrent dépliés. */
    $emplacementValue = (string) ($row->emplacement ?? '');
    $flagCount = count(array_filter(Item::FLAG_KEYS, static fn (string $c): bool => !empty($row->$c)));
    $magie = array_filter([(string) $row->element, (string) $row->spell, (string) $row->exotique]);
    $wearRate = (int) $row->wear_rate;
    $caracsCount = count(array_filter(\App\Enum\Caracs::KEYS, static fn (string $k): bool => (int) ($row->$k ?? 0) !== 0));
    $specialCount = count(array_filter(Item::SPECIAL_KEYS, static fn (string $k): bool => (int) ($row->$k ?? 0) !== 0));
    $munitionsCount = $munitions === '' ? 0 : count(explode(',', $munitions));
    $jsonSet = array_keys(array_filter([
        'add_effects' => trim((string) ($row->add_effects ?? '')) !== '',
        'forbid' => trim((string) ($row->forbid ?? '')) !== '',
        'extra' => $extraDisplay !== '',
    ]));

    $flagsDigestParts = array_filter([
        $flagCount > 0 ? $flagCount . ' actif' . ($flagCount > 1 ? 's' : '') : '',
        implode(' · ', array_map('e', $magie)),
    ]);
    $speciauxDigestParts = array_filter([
        $specialCount > 0 ? $specialCount . ' modificateur' . ($specialCount > 1 ? 's' : '') : '',
        $munitionsCount > 0 ? $munitionsCount . ' munition' . ($munitionsCount > 1 ? 's' : '') : '',
    ]);
    $consoDigestParts = array_filter([
        $effectsApplied !== [] ? count($effectsApplied) . ' appliqué' . (count($effectsApplied) > 1 ? 's' : '') : '',
        $effectsRemoved !== [] ? count($effectsRemoved) . ' retiré' . (count($effectsRemoved) > 1 ? 's' : '') : '',
    ]);

    $sections = items_edit_section('Identité',
            e(trim($typeValue . ($emplacementValue !== '' ? ' · ' . $emplacementValue : ''))) ?: 'type non renseigné',
            true, $identite)
        . items_edit_section('Flags &amp; magie',
            $flagsDigestParts !== [] ? implode(' · ', $flagsDigestParts) : '—',
            $flagCount > 0 || $magie !== [], $flags)
        . items_edit_section('Usure <small class="text-muted">(par tour)</small>',
            $wearRate > 0 ? $wearRate . ' pt/tour · vie ' . (int) ($row->durability_max ?? 100) : 'ne s\'use pas',
            $wearRate > 0 || $triggers !== [], $usure, 'equipement',
            $wearRate > 0 || $triggers !== [],
            ($wearRate > 0 || $triggers !== []) && $typeValue !== 'equipement')
        . items_edit_section('Caractéristiques',
            $caracsCount > 0 ? $caracsCount . ' modificateur' . ($caracsCount > 1 ? 's' : '') : '—',
            $caracsCount > 0, $caracsCol)
        . items_edit_section('Spéciaux &amp; munitions',
            $speciauxDigestParts !== [] ? implode(' · ', $speciauxDigestParts) : '—',
            $specialCount > 0 || $munitionsCount > 0, $speciaux)
        . items_edit_section('À la consommation',
            $consoDigestParts !== [] ? implode(' · ', $consoDigestParts) : '—',
            $effectsApplied !== [] || $effectsRemoved !== [] || $typeValue === 'consommable', $consommation, 'consommable',
            $effectsApplied !== [] || $effectsRemoved !== [],
            ($effectsApplied !== [] || $effectsRemoved !== []) && $typeValue !== 'consommable')
        . items_edit_section('Graine <small class="text-muted">(pousse quotidienne)</small>',
            $growTo !== [] ? count($growTo) . ' pousse' . (count($growTo) > 1 ? 's' : '') : '—',
            $growTo !== [] || $growZMin !== null || $typeValue === 'graine', $graine, 'graine',
            $growTo !== [] || $growZMin !== null,
            ($growTo !== [] || $growZMin !== null) && $typeValue !== 'graine')
        . items_edit_section('JSON avancé',
            $jsonSet !== [] ? implode(', ', $jsonSet) : '—',
            $jsonSet !== [], $jsonAvance);

    $sectionStyles = '<style>
        .item-sections { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 10px; align-items: start; margin-bottom: 14px; }
        .item-section { border: 1px solid var(--rule); border-radius: var(--r-lg); background: var(--paper); }
        .item-section > summary { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; padding: 9px 12px; cursor: pointer; list-style: none; }
        .item-section > summary::-webkit-details-marker { display: none; }
        .item-section-title { font-weight: 600; color: var(--ink); white-space: nowrap; }
        .item-section-title::before { content: "▸ "; color: var(--mute); font-size: 11px; }
        .item-section[open] > summary .item-section-title::before { content: "▾ "; }
        .item-section-digest { color: var(--mute); font-size: 12px; text-align: right; }
        .item-section-body { padding: 8px 12px 12px; border-top: 1px solid var(--rule); }
        .item-section--off > summary { opacity: 0.55; }
    </style>';

    /* Le sélecteur Type pilote les sections liées (data-for-type) : au
     * changement, la section du type choisi s'ouvre ; une section liée
     * VIDE et hors type se replie et s'estompe ; une section liée
     * RENSEIGNÉE et hors type reste dépliée avec le badge « hors type »
     * (même convention que les warnings des plans) — une incohérence se
     * montre, ne se cache pas. Au chargement, seuls estompage et badge
     * s'appliquent, l'état ouvert/replié initial vient du serveur. */
    $sectionScript = '<script>
        (function () {
            var select = document.getElementById("item-type-select");
            if (!select) { return; }
            function apply(fold) {
                document.querySelectorAll(".item-section[data-for-type]").forEach(function (section) {
                    var match = section.dataset.forType === select.value;
                    var filled = section.dataset.filled === "1";
                    section.classList.toggle("item-section--off", !match && !filled);
                    var warn = section.querySelector(".item-section-warn");
                    if (warn) { warn.hidden = match || !filled; }
                    if (fold) { section.open = match || filled; }
                });
            }
            select.addEventListener("change", function () { apply(true); });
            apply(false);
        })();
    </script>';

    $body = $sectionStyles
        . '<form method="post" action="/admin/items-save.php?action=' . ($isNew ? 'create' : 'update') . '">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
        . $nameField
        . ($isNew ? '' : $notInDb . $imagesPanel)
        . '<div class="item-sections">' . $sections . '</div>'
        . '<button class="btn btn-primary" type="submit">Enregistrer</button> '
        . '<a class="btn btn-secondary" href="/admin/items.php">Retour</a>'
        . '</form>'
        . ($isNew ? '' : $imageUploadForms)
        . $sectionScript;

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
    $content = items_render_list(items_catalog(), $csrfToken);
}

echo admin_layout('Objets', renderFlashMessage() . $content);
