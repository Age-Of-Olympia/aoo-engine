<?php
/**
 * Gestion des plans (admin dashboard → Cartes → Plans).
 *
 * Trois vues, routées sur ?action :
 *   - list (défaut) : inventaire réconcilié des plans — union des fichiers
 *     JSON (datas/private/plans) et des plans présents en base (coords) —
 *     avec statut de cohérence et bilan de validation.
 *   - new  : création d'un plan, vierge (JSON minimal + case d'amorce) ou
 *     par clonage d'un plan modèle (JSON + coords + couches map_*).
 *   - edit : édition de la configuration du plan (PLAN_CONFIG_KEYS + niveaux
 *     Z), remplaçant l'édition du JSON brut via tools.php ; zone dangereuse
 *     de suppression avec bilan préalable (PlanAdminService::deletePreflight).
 *
 * Le contenu des cartes (tuiles, murs…) reste authoré via l'extension Tiled
 * (docs/tiled-editor-guide.md) ; cette page gère le cycle de vie et la
 * configuration. Export/import par bundles JSON via le registre ImportExport
 * (PlanExporter / PlanImporter).
 *
 * Toutes les mutations POSTent vers plans-save.php (CSRF, PRG). Cette page ne
 * fait que rendre. Accès via layout.php (AdminMenuAccessService, superadmin
 * par défaut).
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\PlanAdminService;
use App\Service\PlanConfigService;
use App\Service\PlanJsonValidator;
use App\Service\TileCatalogService;
use App\Service\TiledMapService;
use App\Service\ViewService;
use Classes\Db;

/**
 * Inventaire réconcilié : un plan peut exister côté fichier JSON, côté coords
 * en base, ou les deux — les incohérences (moitié manquante) sont le premier
 * diagnostic de la page.
 *
 * @return array<string, object{id: string, name: string, isS2: bool, hasJson: bool,
 *                              hasCoords: bool, zLevels: int[], coordsCount: int}> par id, trié
 */
function plans_build_inventory(Db $db): array
{
    $byId = [];

    foreach ((new ViewService($db, 0, 0, 0, 0, 'olympia'))->getAllPlans() as $p) {
        $byId[$p->id] = (object) [
            'id' => $p->id, 'name' => $p->name, 'isS2' => $p->isS2,
            'hasJson' => true, 'hasCoords' => false, 'zLevels' => [], 'coordsCount' => 0,
        ];
    }

    foreach ((new TiledMapService())->listPlans() as $planId => $info) {
        $row = $byId[$planId] ?? (object) [
            'id' => $planId, 'name' => $planId, 'isS2' => str_contains($planId, '_s2'),
            'hasJson' => false, 'hasCoords' => false, 'zLevels' => [], 'coordsCount' => 0,
        ];
        $row->hasCoords = true;
        $row->zLevels = $info['zLevels'];
        $row->coordsCount = $info['coords'];
        $byId[$planId] = $row;
    }

    // Même tri que local_maps.php : nom de base, la variante _s2 d'abord
    uasort($byId, function (object $a, object $b): int {
        $nameA = str_replace('_s2', '', $a->id);
        $nameB = str_replace('_s2', '', $b->id);
        if ($nameA === $nameB) {
            return $b->isS2 <=> $a->isS2;
        }
        return strcasecmp($nameA, $nameB);
    });

    return $byId;
}

/** Badge de cohérence JSON/coords d'un plan de l'inventaire. */
function plans_status_badge(object $plan): string
{
    if ($plan->hasJson && $plan->hasCoords) {
        return '<span class="badge" style="background-color:#198754;color:#fff;">cohérent</span>';
    }
    if ($plan->hasCoords) {
        return '<span class="badge" style="background-color:#dc3545;color:#fff;" title="Des coordonnées existent en base'
            . ' mais le fichier datas/private/plans/' . e($plan->id) . '.json manque : aucune récolte, pas de bornes de carte.">'
            . 'coords sans JSON</span>';
    }
    return '<span class="badge" style="background-color:#f0ad4e;color:#fff;" title="Un fichier JSON existe mais aucune'
        . ' coordonnée en base : plan orphelin (jamais créé, ou base purgée).">JSON sans coords</span>';
}

/** Rapport PlanJsonValidator d'un plan, en chaîne (les helpers echo-ent). */
function plans_validation_html(string $planId, Db $db, bool $includeOk): string
{
    $raw = json()->decode('plans', $planId);
    if ($raw === null || $raw === false) {
        return '<div class="alert alert-danger py-1 my-1"><i class="fas fa-times-circle"></i>'
            . ' Fichier JSON du plan vide ou invalide, aucune récolte possible sur ce plan.</div>';
    }

    $validation = PlanJsonValidator::validate($raw, $planId, $db);
    if (count($validation['errors']) + count($validation['warnings']) + count($validation['ok']) === 0) {
        return '<p class="text-muted mb-0">Aucune validation applicable (pas de niveaux Z ni de biomes déclarés).</p>';
    }

    ob_start();
    render_validation_report($validation, $includeOk);

    return (string) ob_get_clean();
}

/**
 * @param array<string, object> $inventory
 */
function plans_render_list(array $inventory, Db $db): string
{
    $seasonFilter = current_season_filter();
    $filtered = array_filter($inventory, fn(object $p) => plan_matches_season_filter($p, $seasonFilter));

    // Noms d'items préchargés une fois pour le validator (sinon une requête
    // par biome sur tout le catalogue) — même pattern que local_maps.php
    $knownItemNames = [];
    $res = $db->exe('SELECT name FROM items');
    while ($row = $res->fetch_object()) {
        $knownItemNames[] = $row->name;
    }

    $rows = '';
    foreach ($filtered as $plan) {
        $validationCell = '<span class="text-muted">—</span>';
        if ($plan->hasJson) {
            $raw = json()->decode('plans', $plan->id);
            if ($raw === null || $raw === false) {
                $validationCell = '<span class="badge" style="background-color:#dc3545;color:#fff;">JSON invalide</span>';
            } else {
                $v = PlanJsonValidator::validate($raw, $plan->id, $db, $knownItemNames);
                $badges = '';
                if (count($v['errors']) > 0) {
                    $badges .= '<span class="badge" style="background-color:#dc3545;color:#fff;">' . count($v['errors']) . ' err.</span> ';
                }
                if (count($v['warnings']) > 0) {
                    $badges .= '<span class="badge" style="background-color:#f0ad4e;color:#fff;">' . count($v['warnings']) . ' avert.</span>';
                }
                $validationCell = $badges !== '' ? trim($badges)
                    : '<span class="badge" style="background-color:#198754;color:#fff;">OK</span>';
            }
        }

        $coordsCell = $plan->hasCoords
            ? number_format($plan->coordsCount, 0, ',', ' ') . ' cases · z : ' . implode(', ', $plan->zLevels)
            : '<span class="text-muted">—</span>';

        $rows .= '<tr>'
            . '<td><code>' . e($plan->id) . '</code></td>'
            . '<td>' . e($plan->name) . '</td>'
            . '<td>' . ($plan->isS2 ? '<span class="badge bg-success">S2</span>' : '<span class="badge bg-secondary">S1</span>') . '</td>'
            . '<td>' . plans_status_badge($plan) . '</td>'
            . '<td>' . $coordsCell . '</td>'
            . '<td>' . $validationCell . '</td>'
            . '<td><a class="btn btn-sm btn-outline-primary" href="/admin/plans.php?action=edit&amp;plan='
            . e(urlencode($plan->id)) . '">Éditer</a> '
            . '<a class="btn btn-sm btn-outline-secondary" title="Exporter ce plan (bundle JSON : config + coords + couches)"'
            . ' href="/admin/action-export.php?type=plan&amp;plan=' . e(urlencode($plan->id)) . '">JSON</a></td>'
            . '</tr>';
    }

    return '<div class="d-flex justify-content-between align-items-center mb-3">'
        . '<h1 class="mb-0">Plans</h1>'
        . '<div class="d-flex gap-2">'
        . '<a class="btn btn-outline-secondary" href="/admin/action-import.php"'
        . ' title="Importer un bundle JSON de plan (avec prévisualisation avant application)">'
        . '<i class="fas fa-upload"></i> Importer</a>'
        . '<a class="btn btn-primary" href="/admin/plans.php?action=new">+ Nouveau plan</a>'
        . '</div></div>'

        . '<div class="alert alert-info" style="font-size:13px;line-height:1.5;">'
        . '<strong>Qu\'est-ce qu\'un plan ?</strong> Deux moitiés qui doivent rester cohérentes : '
        . '<strong>un fichier JSON</strong> (<code style="display:inline;white-space:nowrap">private/plans/&lt;id&gt;.json</code>'
        . ' — nom, niveaux Z, bornes visibles, biomes…) et <strong>des coordonnées en base</strong>'
        . ' (table <code style="display:inline;white-space:nowrap">coords</code> + couches'
        . ' <code style="display:inline;white-space:nowrap">map_*</code>).'
        . ' Le contenu des cartes s\'édite via l\'extension Tiled ; la génération des PNG se fait sur'
        . ' <a href="/admin/local_maps.php">Cartes locales</a>.</div>'

        . '<div class="card mb-3"><div class="card-body py-2">' . render_season_filter($seasonFilter) . '</div></div>'

        . '<table class="table table-striped table-sm" data-admin-list data-page-size="30"><thead><tr>'
        . '<th>Code</th><th>Nom</th><th>Saison</th><th>Statut</th><th>Coords</th>'
        . '<th title="Bilan PlanJsonValidator (niveaux Z, biomes)">Validation</th><th></th>'
        . '</tr></thead><tbody>' . $rows . '</tbody></table>';
}

/**
 * @param array<string, object> $inventory
 */
function plans_render_new_form(array $inventory, string $csrfToken): string
{
    $templates = array_filter($inventory, fn(object $p) => $p->hasJson && $p->hasCoords);

    $templateChoices = [];
    foreach ($templates as $p) {
        $templateChoices[$p->id] = $p->name . ' (' . $p->id . ', '
            . number_format($p->coordsCount, 0, ',', ' ') . ' cases)';
    }

    $modeScript = <<<HTML
<script>
(function () {
    /* Le select de plan modèle n'a de sens qu'en mode clonage */
    var radios = document.querySelectorAll('input[name="mode"]');
    var template = document.querySelector('select[name="template"]');
    function sync() {
        var clone = document.querySelector('input[name="mode"]:checked').value === 'clone';
        template.disabled = !clone;
        template.required = clone;
    }
    radios.forEach(function (r) { r.addEventListener('change', sync); });
    sync();
})();
</script>
HTML;

    return '<div class="d-flex justify-content-between align-items-center mb-3">'
        . '<h1 class="mb-0">Nouveau plan</h1>'
        . '<a class="btn btn-sm btn-outline-secondary" href="/admin/plans.php">← Retour à la liste</a></div>'

        . '<form method="post" action="/admin/plans-save.php?action=create">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'

        . '<div class="card mb-3"><div class="card-header">Identité</div><div class="card-body"><div class="row">'
        . '<div class="form-group col-md-4"><label>Code du plan</label>'
        . '<input type="text" class="form-control" name="plan" required pattern="[a-z0-9_-]{1,64}"'
        . ' placeholder="ex: foret_noire_s2">'
        . '<small class="form-text text-muted">Minuscules / chiffres / _ / - (64 max) — nom du fichier JSON et de'
        . ' <code style="display:inline">coords.plan</code>. Non modifiable ensuite. Suffixe'
        . ' <code style="display:inline">_s2</code> = plan de saison 2.</small></div>'
        . '<div class="form-group col-md-4"><label>Nom affiché</label>'
        . '<input type="text" class="form-control" name="name" required placeholder="ex: Forêt Noire"></div>'
        . '<div class="form-group col-md-4"><label>Nom court</label>'
        . '<input type="text" class="form-control" name="shortName" placeholder="ex: Forêt">'
        . '<small class="form-text text-muted">Carte du monde, liste des lieux. Vide = code du plan.</small></div>'
        . '</div></div></div>'

        . '<div class="card mb-3"><div class="card-header">Mode de création</div><div class="card-body">'
        . '<div class="form-group">'
        . '<label class="d-block" style="cursor:pointer;"><input type="radio" name="mode" value="blank" checked> '
        . '<strong>Plan vierge</strong> — crée le fichier JSON minimal et une case d\'amorce (0,0,0).'
        . ' Le contenu (tuiles, murs…) s\'authore ensuite via l\'extension Tiled.</label>'
        . '<label class="d-block mt-2" style="cursor:pointer;"><input type="radio" name="mode" value="clone"> '
        . '<strong>Cloner un plan modèle</strong> — copie le JSON, toutes les coordonnées et les couches'
        . ' (tuiles, murs, éléments…). Les constructions de joueurs, les personnages et les objets au sol'
        . ' ne sont jamais copiés.</label>'
        . '</div>'
        . '<div class="form-group col-md-6 px-0"><label>Plan modèle</label>'
        . formSelect('template', $templateChoices, null, '— choisir un plan modèle —', 'class="form-control" disabled')
        . '<small class="form-text text-muted">Seuls les plans cohérents (JSON + coords) sont proposés.</small></div>'
        . '</div></div>'

        . '<button type="submit" class="btn btn-primary">Créer le plan</button>'
        . '</form>'
        . $modeScript;
}

/**
 * Formulaire d'édition de la configuration (PLAN_CONFIG_KEYS + niveaux Z).
 * Convention PlanConfigService : toutes les valeurs voyagent en chaînes,
 * '' = clé absente/retirée du JSON.
 */
function plans_render_edit_form(object $plan, string $csrfToken, Db $db): string
{
    $planId = $plan->id;
    $configService = new PlanConfigService();
    $values = $configService->read($planId);

    // Libellé + aide par clé — le type (PLAN_CONFIG_KEYS) pilote le rendu
    $fieldHelp = [
        'name'              => ['Nom affiché', 'Nom complet du plan (cartes, journaux).'],
        'shortName'         => ['Nom court', 'Carte du monde, liste des lieux.'],
        'x'                 => ['X (carte monde)', 'Position du territoire sur la carte du monde ; vide = donjon hors grille.'],
        'y'                 => ['Y (carte monde)', 'Position du territoire sur la carte du monde ; vide = donjon hors grille.'],
        'player_visibility' => ['Visibilité des joueurs', 'false = les autres personnages sont masqués et ne bloquent pas les cases (mode tutoriel).'],
        'pnj'               => ['PNJ', 'Nombre de PNJ du plan (peuplement).'],
        'size'              => ['Taille', 'Taille de rendu des tuiles.'],
        'bg'                => ['Image de fond', 'Chemin img/… ; le fichier doit exister (repli : img/tiles/&lt;plan&gt;.webp).'],
        'mask'              => ['Masque', 'Superposition (brume, tempête…), niveaux z ≥ 0 uniquement.'],
        'scrollingMask'     => ['Défilement du masque', 'Durée d\'animation du masque (0/vide = statique).'],
        'verticalScrolling' => ['Défilement vertical', 'Direction du défilement du masque.'],
        'biomes'            => ['Biomes (JSON)', 'Ressources récoltables : [{"wall": "arbre1", "ressource": "bois", "exhaust": 75, "regrow": 20}]. Le mur doit valoir -1 dans WALLS_PV, la ressource exister dans items.'],
    ];

    $renderField = function (string $key, string $type) use ($values, $fieldHelp): string {
        [$label, $help] = $fieldHelp[$key];
        $value = $values[$key];
        $hint = '<small class="form-text text-muted">' . $help . ' <em>Vide = clé retirée du JSON.</em></small>';

        $input = match ($type) {
            'int' => '<input type="number" step="1" class="form-control" name="config[' . e($key) . ']" value="' . e($value) . '">',
            'bool' => formSelect('config[' . $key . ']', ['true' => 'true', 'false' => 'false'],
                $value !== '' ? $value : null, '(défaut — clé absente)'),
            'image' => '<input type="text" class="form-control" name="config[' . e($key) . ']" value="' . e($value) . '"'
                . ' list="plans-bg-catalog" placeholder="img/tiles/…">',
            'json' => '<textarea class="form-control" name="config[' . e($key) . ']" rows="10" spellcheck="false"'
                . ' style="font-family:monospace;font-size:12px;">' . e($value) . '</textarea>',
            default => '<input type="text" class="form-control" name="config[' . e($key) . ']" value="' . e($value) . '">',
        };

        $width = $type === 'json' ? 'col-12' : 'col-md-3 col-6';

        return '<div class="form-group ' . $width . '"><label>' . e($label)
            . ' <code style="display:inline;font-size:11px;">' . e($key) . '</code></label>'
            . $input . $hint . '</div>';
    };

    // Cartes de champs par thème (l'ordre suit l'usage, pas PLAN_CONFIG_KEYS)
    $identityKeys = ['name', 'shortName', 'x', 'y', 'size', 'pnj'];
    $displayKeys = ['player_visibility', 'bg', 'mask', 'scrollingMask', 'verticalScrolling'];

    $identityFields = '';
    foreach ($identityKeys as $key) {
        $identityFields .= $renderField($key, PlanConfigService::PLAN_CONFIG_KEYS[$key]);
    }
    $displayFields = '';
    foreach ($displayKeys as $key) {
        $displayFields .= $renderField($key, PlanConfigService::PLAN_CONFIG_KEYS[$key]);
    }
    $biomesField = $renderField('biomes', 'json');

    // Niveaux Z : union base ∪ JSON, pour rendre la dérive visible ici aussi
    $raw = json()->decode('plans', $planId);
    $jsonZ = [];
    foreach (($raw->z_levels ?? []) as $level) {
        if (isset($level->z)) {
            $jsonZ[] = (int) $level->z;
        }
    }
    $allZ = array_values(array_unique(array_merge($plan->zLevels, $jsonZ)));
    sort($allZ);

    $zRows = '';
    foreach ($allZ as $z) {
        $zConfig = $configService->readZLevel($planId, $z);
        $origin = '';
        if (!in_array($z, $jsonZ, true)) {
            $origin = ' <span class="badge" style="background-color:#f0ad4e;color:#fff;"'
                . ' title="Des coords existent pour ce niveau mais le JSON ne le déclare pas — enregistrer corrige.">en base seulement</span>';
        } elseif (!in_array($z, $plan->zLevels, true)) {
            $origin = ' <span class="badge" style="background-color:#f0ad4e;color:#fff;"'
                . ' title="Déclaré dans le JSON mais aucune coordonnée en base pour ce niveau.">JSON seulement</span>';
        }

        $zRows .= '<tr>'
            . '<td class="align-middle"><strong>z = ' . $z . '</strong>' . $origin . '</td>'
            . '<td><input type="text" class="form-control form-control-sm" name="z[' . $z . '][name]"'
            . ' value="' . e($zConfig['name']) . '" placeholder="Niveau ' . $z . '"></td>'
            . '<td class="align-middle text-center"><input type="checkbox" name="z[' . $z . '][mapUnavailable]" '
            . checked($zConfig['mapUnavailable'] === 'true') . '></td>'
            . '<td><input type="text" class="form-control form-control-sm" name="z[' . $z . '][bounds]"'
            . ' value="' . e($zConfig['bounds']) . '" placeholder="auto">'
            . '</td>'
            . '</tr>';
    }

    $zCard = $zRows === ''
        ? '<p class="text-muted mb-0">Aucun niveau Z (ni en base, ni dans le JSON). La première édition Tiled en créera.</p>'
        : '<table class="table table-sm mb-1"><thead><tr>'
            . '<th style="width:22%">Niveau</th><th>Nom affiché</th>'
            . '<th style="width:12%" class="text-center" title="Niveau volontairement sans carte (MapUnavailable)">Sans carte</th>'
            . '<th style="width:30%" title="Bornes visibles « minX,maxX,minY,maxY », ou « auto » (recalculées au prochain push Tiled)">Bornes visibles</th>'
            . '</tr></thead><tbody>' . $zRows . '</tbody></table>'
            . '<small class="text-muted">Bornes : « minX,maxX,minY,maxY » explicites, ou « auto » — recalculées'
            . ' sur l\'étendue réelle au prochain push Tiled du niveau.</small>';

    $bgChoices = (new TileCatalogService())->backgroundChoices();
    $bgCatalog = renderDatalist('plans-bg-catalog', array_combine($bgChoices, $bgChoices) ?: []);

    return '<div class="d-flex justify-content-between align-items-center mb-3">'
        . '<h1 class="mb-0">Plan : ' . e($plan->name) . ' <span class="text-muted">(' . e($planId) . ')</span></h1>'
        . '<div class="d-flex gap-2">'
        . '<a class="btn btn-sm btn-outline-secondary" href="/admin/plans.php">← Retour</a>'
        . '<a class="btn btn-sm btn-outline-secondary" title="Exporter ce plan (bundle JSON : config + coords + couches)"'
        . ' href="/admin/action-export.php?type=plan&amp;plan=' . e(urlencode($planId)) . '">'
        . '<i class="fas fa-download"></i> JSON</a>'
        . '<a class="btn btn-sm btn-outline-secondary" href="/admin/local_maps.php" title="Génération des PNG de carte">Cartes locales</a>'
        . '<a class="btn btn-sm btn-outline-secondary" target="_blank" title="Éditer le fichier JSON brut (tools.php)"'
        . ' href="/tools.php?edit&amp;dir=private&amp;subDir=plans&amp;finalDir=' . e(urlencode($planId)) . '">JSON brut</a>'
        . '</div></div>'

        . (!$plan->hasCoords
            ? '<div class="alert alert-warning" style="font-size:13px;">Plan orphelin : fichier JSON sans aucune'
                . ' coordonnée en base. Importez un bundle, ou créez la case d\'amorce en le recréant après suppression.</div>'
            : '')
        . (!$plan->hasJson
            ? '<div class="alert alert-danger" style="font-size:13px;">Ce plan n\'a pas de fichier JSON'
                . ' (datas/private/plans/' . e($planId) . '.json). Enregistrer ce formulaire le créera.</div>'
            : '')

        . '<form method="post" action="/admin/plans-save.php?action=update">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
        . '<input type="hidden" name="plan" value="' . e($planId) . '">'

        . '<div class="card mb-3"><div class="card-header">Identité &amp; carte du monde</div>'
        . '<div class="card-body"><div class="row">' . $identityFields . '</div></div></div>'

        . '<div class="card mb-3"><div class="card-header">Affichage</div>'
        . '<div class="card-body"><div class="row">' . $displayFields . '</div></div></div>'

        . '<div class="card mb-3"><div class="card-header">Biomes (ressources récoltables)</div>'
        . '<div class="card-body"><div class="row">' . $biomesField . '</div></div></div>'

        . '<div class="card mb-3"><div class="card-header">Niveaux Z</div>'
        . '<div class="card-body">' . $zCard . '</div></div>'

        . '<button type="submit" class="btn btn-primary">Enregistrer</button>'
        . '</form>'

        . '<div class="card mt-4"><div class="card-header">Validation du plan</div>'
        . '<div class="card-body">' . plans_validation_html($planId, $db, true) . '</div></div>'

        . plans_render_delete_zone($planId, $csrfToken)
        . $bgCatalog;
}

/**
 * Zone dangereuse : suppression avec bilan préalable. Les blocages absolus
 * (joueurs réels, FK tutoriel, respawn de faction) désactivent la
 * suppression ; les blocages forçables (PNJ, logs) exigent la case
 * « forcer ». Le garde-fou fait foi côté serveur (PlanAdminService).
 */
function plans_render_delete_zone(string $planId, string $csrfToken): string
{
    $preflight = (new PlanAdminService())->deletePreflight($planId);
    $hard = array_values(array_filter($preflight['blockers'], fn(array $b) => !$b['forceable']));
    $soft = array_values(array_filter($preflight['blockers'], fn(array $b) => $b['forceable']));

    $warningsHtml = '';
    if ($preflight['warnings'] !== []) {
        $warningsHtml = '<ul class="mb-2 text-muted" style="font-size:13px;">';
        foreach ($preflight['warnings'] as $w) {
            $warningsHtml .= '<li>' . e($w['detail']) . '</li>';
        }
        $warningsHtml .= '</ul>';
    }

    if ($hard !== []) {
        $items = '';
        foreach ($hard as $b) {
            $items .= '<li>' . e($b['detail']) . '</li>';
        }
        $body = '<p class="mb-1 text-muted">Suppression impossible :</p><ul class="mb-0 text-muted">' . $items . '</ul>';
    } else {
        $forceField = '';
        if ($soft !== []) {
            $items = '';
            foreach ($soft as $b) {
                $items .= '<li>' . e($b['detail']) . '</li>';
            }
            $forceField = '<ul class="mb-2 text-muted" style="font-size:13px;">' . $items . '</ul>'
                . '<label class="d-block mb-3" style="cursor:pointer;"><input type="checkbox" name="force"> '
                . 'Forcer : supprimer les PNJ du plan (et leurs données) et détacher les logs.</label>';
        }

        $body = $warningsHtml
            . '<form method="post" action="/admin/plans-save.php?action=delete"'
            . ' onsubmit="return confirm(\'Supprimer définitivement le plan « ' . e($planId)
            . ' » : fichier JSON, coordonnées, toutes les couches et les PNG générés ?\');">'
            . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
            . '<input type="hidden" name="plan" value="' . e($planId) . '">'
            . $forceField
            . '<div class="d-flex align-items-center gap-3">'
            . '<button type="submit" class="btn btn-outline-danger">Supprimer le plan</button>'
            . '<small class="text-muted">Pensez à exporter un bundle JSON avant — il permet de restaurer le plan à l\'identique.</small>'
            . '</div></form>';
    }

    return '<div class="card mt-4 border-danger"><div class="card-header text-danger">Zone dangereuse</div>'
        . '<div class="card-body">' . $body . '</div></div>';
}

/* -------------------------------------------------------------------------
 * Route
 * ---------------------------------------------------------------------- */
$db = new Db();
$csrfToken = (new CsrfProtectionService())->generateToken();
$inventory = plans_build_inventory($db);

$action = $_GET['action'] ?? 'list';

if ($action === 'new') {
    $content = plans_render_new_form($inventory, $csrfToken);
} elseif ($action === 'edit') {
    $planId = strtolower(trim((string) ($_GET['plan'] ?? '')));
    if (!preg_match(TiledMapService::PLAN_NAME_PATTERN, $planId) || !isset($inventory[$planId])) {
        setFlash('warning', 'Plan introuvable.');
        redirectTo('/admin/plans.php');
    }
    $content = plans_render_edit_form($inventory[$planId], $csrfToken, $db);
} else {
    $content = plans_render_list($inventory, $db);
}

echo admin_layout('Plans', renderFlashMessage() . $content);
