<?php
/**
 * Gestion des plans (admin dashboard → Cartes → Plans).
 *
 * Trois vues, routées sur ?action :
 *   - list (défaut) : inventaire réconcilié des plans — union des configs
 *     (table plans) et des plans présents en base (coords) — avec statut de
 *     cohérence et bilan de validation.
 *   - new  : création d'un plan, vierge (config minimale + case d'amorce) ou
 *     par clonage d'un plan modèle (config + coords + couches map_*).
 *   - edit : édition de la configuration du plan (PLAN_CONFIG_KEYS + niveaux
 *     Z) ; zone dangereuse de suppression avec bilan préalable
 *     (PlanAdminService::deletePreflight).
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
 * Inventaire réconcilié : un plan peut exister côté config (table plans),
 * côté coords, ou les deux — les incohérences (moitié manquante) sont le
 * premier diagnostic de la page.
 *
 * @return array<string, object{id: string, name: string, season: ?int, hasConfig: bool,
 *                              hasCoords: bool, zLevels: int[], coordsCount: int}> par id, trié
 */
function plans_build_inventory(Db $db): array
{
    $byId = [];

    foreach ((new ViewService($db))->getAllPlans() as $p) {
        $byId[$p->id] = (object) [
            'id' => $p->id, 'name' => $p->name, 'season' => $p->season,
            'hasConfig' => true, 'hasCoords' => false, 'zLevels' => [], 'coordsCount' => 0,
        ];
    }

    foreach ((new TiledMapService())->listPlans() as $planId => $info) {
        $row = $byId[$planId] ?? (object) [
            // Coords without config: season unknown (null = shown under
            // every filter — an anomaly to see, not to hide)
            'id' => $planId, 'name' => $planId, 'season' => null,
            'hasConfig' => false, 'hasCoords' => false, 'zLevels' => [], 'coordsCount' => 0,
        ];
        $row->hasCoords = true;
        $row->zLevels = $info['zLevels'];
        $row->coordsCount = $info['coords'];
        $byId[$planId] = $row;
    }

    uasort($byId, 'plan_order');

    return $byId;
}

/** Badge de cohérence config/coords d'un plan de l'inventaire. */
function plans_status_badge(object $plan): string
{
    if ($plan->hasConfig && $plan->hasCoords) {
        return '<span class="badge badge-solid badge-success">cohérent</span>';
    }
    if ($plan->hasCoords) {
        return '<span class="badge badge-solid badge-danger" title="Des coordonnées existent en base'
            . ' mais la table plans n\'a pas de ligne « ' . e($plan->id) . ' » : aucune récolte, pas de bornes de carte.">'
            . 'coords sans config</span>';
    }
    return '<span class="badge badge-solid badge-warning" title="Une config existe mais aucune'
        . ' coordonnée en base : plan orphelin (jamais créé, ou base purgée).">config sans coords</span>';
}

/** Rapport PlanJsonValidator d'un plan, en chaîne (les helpers echo-ent). */
function plans_validation_html(string $planId, Db $db, bool $includeOk): string
{
    $raw = plans()->read($planId);
    if ($raw === null || $raw === false) {
        return '<div class="alert alert-danger py-1 my-1"><i class="fas fa-times-circle"></i>'
            . ' Config du plan absente, aucune récolte possible sur ce plan.</div>';
    }

    $validation = PlanJsonValidator::validate($raw, $planId, $db);
    if (count($validation['errors']) + count($validation['warnings']) + count($validation['ok']) === 0) {
        return '<p class="text-muted mb-0">Aucune validation applicable (pas de niveaux Z déclarés).</p>';
    }

    ob_start();
    render_validation_report($validation, $includeOk);

    return (string) ob_get_clean();
}

/**
 * The plan's map as the game composes it: its layers stacked, as a small
 * thumbnail and, on hover, a large preview. World plan: the world map.
 * Any other plan: the thumbnail shows level 0 (else the first generated
 * level), the preview every generated level side by side.
 *
 * @param array<int, array<string, string>> $localByZ z => layer => web path
 * @param array<string, string>|null $world layer => web path when this is the world plan
 */
function plans_map_thumb(array $localByZ, ?array $world): string
{
    /** @param array<string, string> $layers */
    $stack = function (array $layers, array $order): array {
        $tiles = $_SERVER['DOCUMENT_ROOT'] . ($layers['tiles'] ?? '');
        [$w, $h] = is_file($tiles) ? (getimagesize($tiles) ?: [1, 1]) : [1, 1];
        $imgs = '';
        foreach ($order as $layer) {
            if (isset($layers[$layer])) {
                $imgs .= '<img src="' . e($layers[$layer]) . '" alt="" loading="lazy">';
            }
        }

        return [$imgs, round($w / max(1, $h), 4)];
    };

    if ($world !== null) {
        $levels = ['Carte du monde' => $world];
        $order = ['tiles', 'elements', 'routes', 'buildings', 'locations'];
    } else {
        ksort($localByZ);
        $levels = [];
        foreach ($localByZ as $z => $files) {
            if (isset($files['tiles'])) {
                $levels['z = ' . $z] = $files;
            }
        }
        if ($levels === []) {
            return '<span class="text-muted">–</span>';
        }
        $order = ['tiles', 'elements', 'foregrounds', 'resources', 'routes', 'buildings'];
    }

    $thumbKey = isset($levels['z = 0']) ? 'z = 0' : array_key_first($levels);
    [$thumb, $thumbRatio] = $stack($levels[$thumbKey], $order);

    $preview = '';
    foreach ($levels as $label => $layers) {
        [$imgs, $ratio] = $stack($layers, $order);
        $preview .= '<span class="plan-map-level"><span class="plan-map-preview-stack" style="--ratio:' . $ratio . '">' . $imgs
            . '</span><small>' . e($label) . '</small></span>';
    }

    return '<span class="plan-map-thumb" style="--ratio:' . $thumbRatio . '" tabindex="0">' . $thumb
        . '<span class="plan-map-preview" role="tooltip">' . $preview . '</span></span>';
}

/**
 * Validator messages for the hover detail of a list badge. The messages
 * come escaped from PlanJsonValidator, as on the edit page.
 *
 * @param list<string> $messages
 */
function plans_validation_list(array $messages, string $color, string $title): string
{
    if ($messages === []) {
        return '';
    }
    $items = '';
    foreach ($messages as $message) {
        $items .= '<li>' . $message . '</li>';
    }

    return '<strong style="color:' . $color . '">' . e($title) . '</strong><ul>' . $items . '</ul>';
}

/** @param array<string, object> $inventory */
function plans_render_list(array $inventory, Db $db, string $csrfToken): string
{
    $seasonFilter = current_season_filter();
    $filtered = array_filter($inventory, fn(object $p) => plan_matches_season_filter($p, $seasonFilter));

    $localMaps = ViewService::localMapIndex();
    $worldPlan = plans()->worldPlan();
    $worldMap = (new ViewService($db))->getGlobalMap();
    $worldLayers = array_map(fn(array $layer): string => $layer['imagePath'], $worldMap);

    $rows = '';
    foreach ($filtered as $plan) {
        $validationCell = '<span class="text-muted">–</span>';
        if ($plan->hasConfig) {
            $raw = plans()->read($plan->id);
            if ($raw === null || $raw === false) {
                $validationCell = '<span class="badge badge-solid badge-danger">config absente</span>';
            } else {
                $v = PlanJsonValidator::validate($raw, $plan->id, $db);
                $badges = '';
                if (count($v['errors']) > 0) {
                    $badges .= '<span class="badge badge-solid badge-danger">' . count($v['errors']) . ' err.</span> ';
                }
                if (count($v['warnings']) > 0) {
                    $badges .= '<span class="badge badge-solid badge-warning">' . count($v['warnings']) . ' avert.</span>';
                }
                $validationCell = $badges !== ''
                    ? '<span class="plan-validation" tabindex="0">' . trim($badges)
                        . '<span class="plan-validation-detail" role="tooltip">'
                        . plans_validation_list($v['errors'], 'var(--danger)', 'Erreurs')
                        . plans_validation_list($v['warnings'], 'var(--warning)', 'Avertissements')
                        . '</span></span>'
                    : '<span class="badge badge-solid badge-success">OK</span>';
            }
        }

        $coordsCell = $plan->hasCoords
            ? number_format($plan->coordsCount, 0, ',', ' ') . ' cases · z : ' . implode(', ', $plan->zLevels)
            : '<span class="text-muted">–</span>';

        $rows .= '<tr id="plan-row-' . e($plan->id) . '">'
            . '<td><code>' . e($plan->id) . '</code></td>'
            . '<td>' . e($plan->name) . '</td>'
            . '<td>' . season_badge($plan->season) . '</td>'
            . '<td>' . plans_status_badge($plan) . '</td>'
            . '<td>' . $coordsCell . '</td>'
            . '<td>' . $validationCell . '</td>'
            . '<td>' . plans_map_thumb($localMaps[$plan->id] ?? [], $plan->id === $worldPlan ? $worldLayers : null) . '</td>'
            . '<td><div class="d-flex flex-wrap gap-1 align-items-center">'
            . '<a class="btn btn-sm btn-outline-primary" href="/admin/plans.php?action=edit&amp;plan='
            . e(urlencode($plan->id)) . '">Éditer</a>'
            . '<a class="btn btn-sm btn-outline-secondary" title="Exporter ce plan (bundle JSON : config + coords + couches)"'
            . ' href="/admin/action-export.php?type=plan&amp;plan=' . e(urlencode($plan->id)) . '">JSON</a>'
            . ($plan->hasConfig && $plan->hasCoords
                ? '<form method="post" action="/admin/plans-save.php?action=generate-map" class="m-0">'
                    . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
                    . '<input type="hidden" name="plan" value="' . e($plan->id) . '">'
                    . '<button type="submit" class="btn btn-sm btn-outline-secondary" title="Générer les images de la carte de ce plan">'
                    . '<i class="fas fa-sync-alt"></i> Générer carte</button></form>'
                : '')
            . '</div></td>'
            . '</tr>';
    }

    return '<div class="card mb-3"><div class="card-body py-2 d-flex align-items-center justify-content-between flex-wrap gap-3">'
        . render_season_filter($seasonFilter)
        /* The GLOBAL settings (current season, world and death plans) live
           on the dashboard: admin → Réglages du monde. */
        . '<a class="btn btn-sm btn-outline-secondary" href="/admin/index.php">'
        . '<i class="fas fa-globe"></i> Saison courante : ' . e((string) (new \App\Service\SeasonService())->current())
        . ' (régler)</a>'
        . '</div></div>'

        . '<table class="table table-striped table-sm" data-admin-list data-page-size="30"><thead><tr>'
        . '<th>Code</th><th>Nom</th><th>Saison</th><th>Statut</th><th>Coords</th>'
        . '<th title="Bilan PlanJsonValidator (niveaux Z)">Validation</th>'
        . '<th title="Carte telle que le jeu la compose ; survol pour l\'agrandir">Carte</th><th></th>'
        . '</tr></thead><tbody>' . $rows . '</tbody></table>'
        . '<style>'
        . '.plan-map-thumb{position:relative;display:inline-block;vertical-align:middle;height:32px;aspect-ratio:var(--ratio);'
        . 'background:#e7ded0;border:1px solid #ccc;cursor:zoom-in}'
        . '.plan-map-thumb>img,.plan-map-preview-stack>img{position:absolute;inset:0;width:100%;height:100%}'
        . '.plan-map-preview{display:none;position:absolute;z-index:1050;right:calc(100% + 8px);top:50%;transform:translateY(-50%);'
        . 'gap:8px;align-items:flex-start;flex-wrap:wrap;width:max-content;max-width:min(900px,calc(100vw - 360px));'
        . 'background:#fff;border:1px solid #999;box-shadow:0 6px 24px rgba(0,0,0,.3);padding:6px}'
        . '.plan-map-level{display:block}'
        . '.plan-map-preview-stack{position:relative;display:block;width:420px;max-width:60vw;aspect-ratio:var(--ratio);background:#e7ded0}'
        . '.plan-map-level:not(:only-child) .plan-map-preview-stack{width:200px}'
        . '.plan-map-preview small{display:block;margin-top:4px;color:#666;text-align:center}'
        . '.plan-map-thumb:hover .plan-map-preview,.plan-map-thumb:focus .plan-map-preview{display:flex}'
        . '.plan-validation{position:relative;display:inline-block;cursor:help}'
        . '.plan-validation-detail{display:none;position:absolute;z-index:1050;left:0;top:calc(100% + 6px);width:max-content;'
        . 'max-width:min(520px,70vw);background:#fff;border:1px solid #999;box-shadow:0 6px 24px rgba(0,0,0,.3);'
        . 'padding:8px 10px;font-size:12px;line-height:1.5;white-space:normal;text-align:left}'
        . '.plan-validation-detail ul{margin:2px 0 6px;padding-left:18px}'
        /* No room on the left of the thumbnail on a phone: open below it. */
        . '@media (max-width:900px){.plan-map-preview{right:auto;left:0;top:calc(100% + 6px);transform:none;max-width:calc(100vw - 40px)}'
        . '.plan-map-preview-stack{max-width:calc(100vw - 60px)}}'
        . '.plan-validation:hover .plan-validation-detail,.plan-validation:focus .plan-validation-detail{display:block}'
        . '.plan-generate-status{display:block;font-size:12px;margin-top:4px;white-space:normal}'
        . '</style>'
        . '<script>'
        /* Generating a map refreshes its row in place: no reload, the page
           stays where it is. Without JS the form posts and comes back to the
           row (anchor). */
        . 'document.addEventListener("submit", function (event) {'
        . '  var form = event.target.closest("form[action*=\\"generate-map\\"]");'
        . '  if (!form || !window.fetch) { return; }'
        . '  event.preventDefault();'
        . '  var row = form.closest("tr");'
        . '  var button = form.querySelector("button");'
        . '  button.disabled = true;'
        . '  button.lastChild.textContent = " Génération…";'
        . '  fetch(form.action, { method: "POST", body: new FormData(form), credentials: "same-origin" })'
        . '    .then(function (response) { return response.text(); })'
        . '    .then(function (html) {'
        . '      var doc = new DOMParser().parseFromString(html, "text/html");'
        . '      var fresh = doc.getElementById(row.id);'
        . '      var flash = doc.querySelector(".alert-success, .alert-warning, .alert-danger");'
        . '      if (fresh) { row.innerHTML = fresh.innerHTML; }'
        . '      var status = document.createElement("span");'
        . '      status.className = "plan-generate-status " + (flash && flash.classList.contains("alert-success") ? "text-success" : "text-danger");'
        . '      status.textContent = flash ? flash.textContent.replace("×", "").trim() : "Génération terminée.";'
        . '      row.lastElementChild.appendChild(status);'
        . '    })'
        . '    .catch(function () { button.disabled = false; button.lastChild.textContent = " Réessayer"; });'
        . '});'
        . '</script>';
}

/**
 * @param array<string, object> $inventory
 */
function plans_render_new_form(array $inventory, string $csrfToken): string
{
    $templates = array_filter($inventory, fn(object $p) => $p->hasConfig && $p->hasCoords);

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

    return '<form method="post" action="/admin/plans-save.php?action=create">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'

        . '<div class="card mb-3"><div class="card-header">Identité</div><div class="card-body"><div class="row">'
        . '<div class="form-group col-md-4"><label>Code du plan</label>'
        . '<input type="text" class="form-control" name="plan" required pattern="[a-z0-9_-]{1,64}"'
        . ' placeholder="ex: foret_noire">'
        . '<small class="form-text text-muted">Minuscules / chiffres / _ / - (64 max), valeur de'
        . ' <code>coords.plan</code>. Non modifiable ensuite.</small></div>'
        . '<div class="form-group col-md-4"><label>Nom affiché</label>'
        . '<input type="text" class="form-control" name="name" required placeholder="ex: Forêt Noire"></div>'
        . '<div class="form-group col-md-4"><label>Nom court</label>'
        . '<input type="text" class="form-control" name="shortName" placeholder="ex: Forêt">'
        . '<small class="form-text text-muted">Carte du monde, liste des lieux. Vide = code du plan.</small></div>'
        . '</div></div></div>'

        . '<div class="card mb-3"><div class="card-header">Mode de création</div><div class="card-body">'
        . '<div class="form-group">'
        . '<label class="d-block"><input type="radio" name="mode" value="blank" checked> '
        . '<strong>Plan vierge</strong> : crée le fichier JSON minimal et une case d\'amorce (0,0,0).'
        . ' Le contenu (tuiles, murs…) s\'authore ensuite via l\'extension Tiled.</label>'
        . '<label class="d-block mt-2"><input type="radio" name="mode" value="clone"> '
        . '<strong>Cloner un plan modèle</strong> : copie le JSON, toutes les coordonnées et les couches'
        . ' (tuiles, murs, éléments…). Les constructions de joueurs, les personnages et les objets au sol'
        . ' ne sont jamais copiés.</label>'
        . '</div>'
        . '<div class="form-group col-md-6 px-0"><label>Plan modèle</label>'
        . formSelect('template', $templateChoices, null, 'Choisir un plan modèle', 'class="form-control" disabled')
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
        'season'            => ['Saison', 'Numéro de saison du plan ; vide = toutes les saisons (olympia, enfers, tutoriel).'],
        'x'                 => ['X (carte monde)', 'Position du territoire sur la carte du monde ; vide = donjon hors grille.'],
        'y'                 => ['Y (carte monde)', 'Position du territoire sur la carte du monde ; vide = donjon hors grille.'],
        'player_visibility' => ['Visibilité des joueurs', 'false = les autres personnages sont masqués et ne bloquent pas les cases (mode tutoriel).'],
        'pnj'               => ['PNJ', 'Nombre de PNJ du plan (peuplement).'],
        'size'              => ['Taille', 'Taille de rendu des tuiles.'],
        'bg'                => ['Image de fond', 'Chemin img/… ; le fichier doit exister (repli : img/tiles/&lt;plan&gt;.webp).'],
        'mask'              => ['Masque', 'Superposition (brume, tempête…), niveaux z ≥ 0 uniquement.'],
        'scrollingMask'     => ['Défilement du masque', 'Durée d\'animation du masque (0/vide = statique).'],
        'verticalScrolling' => ['Défilement vertical', 'Direction du défilement du masque.'],
        'comment'           => ['Commentaire', 'Note pour les admins, jamais affichée en jeu.'],
    ];

    $renderField = function (string $key, string $type) use ($values, $fieldHelp): string {
        [$label, $help] = $fieldHelp[$key];
        $value = $values[$key];
        $hint = '<small class="form-text text-muted">' . $help . ' <em>Vide = clé retirée du JSON.</em></small>';

        $input = match ($type) {
            'int' => '<input type="number" step="1" class="form-control" name="config[' . e($key) . ']" value="' . e($value) . '">',
            'bool' => formSelect('config[' . $key . ']', ['true' => 'true', 'false' => 'false'],
                $value !== '' ? $value : null, '(défaut : clé absente)'),
            'image' => '<input type="text" class="form-control" name="config[' . e($key) . ']" value="' . e($value) . '"'
                . ' list="plans-bg-catalog" placeholder="img/tiles/…">',
            'text' => '<textarea class="form-control" name="config[' . e($key) . ']" rows="3">' . e($value) . '</textarea>',
            default => '<input type="text" class="form-control" name="config[' . e($key) . ']" value="' . e($value) . '">',
        };

        $width = $type === 'text' ? 'col-12' : 'col-md-3 col-6';

        return '<div class="form-group ' . $width . '"><label>' . e($label)
            . ' <code>' . e($key) . '</code></label>'
            . $input . $hint . '</div>';
    };

    // Cartes de champs par thème (l'ordre suit l'usage, pas PLAN_CONFIG_KEYS)
    $identityKeys = ['name', 'shortName', 'season', 'x', 'y', 'size', 'pnj', 'comment'];
    $displayKeys = ['player_visibility', 'bg', 'mask', 'scrollingMask', 'verticalScrolling'];

    $identityFields = '';
    foreach ($identityKeys as $key) {
        $identityFields .= $renderField($key, PlanConfigService::PLAN_CONFIG_KEYS[$key]);
    }
    $displayFields = '';
    foreach ($displayKeys as $key) {
        $displayFields .= $renderField($key, PlanConfigService::PLAN_CONFIG_KEYS[$key]);
    }
    // Yields live in race_harvest; a row there is a per-plan exception
    $harvest = new \App\Service\Map\HarvestCatalogService();
    $poured = count(array_filter(
        $harvest->configured(),
        static fn(array $row): bool => $row['plan'] === $planId
    ));

    $harvestField = '<div class="col-12"><p class="mb-1">'
        . ($poured > 0
            ? '<strong>' . $poured . ' rendement(s)</strong> propre(s) à ce plan, prioritaires sur le catalogue des types.'
            : 'Aucune exception : les ressources de ce plan ont le rendement de <strong>leur type</strong>.')
        . '</p><a class="btn btn-sm btn-outline-primary" href="/admin/harvest-seed.php">Régler les rendements</a></div>';

    // Niveaux Z : union coords ∪ config, pour rendre la dérive visible ici aussi
    $raw = plans()->read($planId);
    $jsonZ = [];
    foreach (($raw->z_levels ?? []) as $level) {
        if (isset($level->z)) {
            $jsonZ[] = (int) $level->z;
        }
    }
    $allZ = array_values(array_unique(array_merge($plan->zLevels, $jsonZ)));
    sort($allZ);

    $zRows = '';
    $zDeleteForms = '';
    foreach ($allZ as $z) {
        $zConfig = $configService->readZLevel($planId, $z);
        $origin = '';
        if (!in_array($z, $jsonZ, true)) {
            $origin = ' <span class="badge badge-solid badge-warning"'
                . ' title="Des coords existent pour ce niveau mais le JSON ne le déclare pas : enregistrer corrige.">en base seulement</span>';
        } elseif (!in_array($z, $plan->zLevels, true)) {
            $origin = ' <span class="badge badge-solid badge-warning"'
                . ' title="Déclaré dans le JSON mais aucune coordonnée en base pour ce niveau.">JSON seulement</span>';
        }

        $zRows .= '<tr>'
            . '<td class="align-middle"><strong>z = ' . $z . '</strong>' . $origin . '</td>'
            . '<td><input type="text" class="form-control form-control-sm" name="z[' . $z . '][name]"'
            . ' value="' . e($zConfig['name']) . '" placeholder="Niveau ' . $z . '"></td>'
            . '<td class="align-middle text-center"><input type="checkbox" name="z[' . $z . '][mapUnavailable]" '
            . checked($zConfig['mapUnavailable'] === 'true') . '></td>'
            . '<td class="align-middle text-center"><input type="checkbox" name="z[' . $z . '][chestsAllowed]" '
            . checked($zConfig['chestsAllowed'] === 'true') . '></td>'
            . '<td><input type="text" class="form-control form-control-sm" name="z[' . $z . '][bounds]"'
            . ' value="' . e($zConfig['bounds']) . '" placeholder="auto">'
            . '</td>'
            /* Bouton hors du <form> principal (attribut form) : un formulaire
             * imbriqué serait invalide. Garde serveur : refus si une entité
             * occupe le niveau. */
            . '<td class="align-middle text-center">'
            . '<button type="submit" form="plan-delz-' . $z . '" class="btn btn-sm btn-outline-danger"'
            . ' title="Supprimer les cases et couches du niveau z=' . $z . '">Supprimer</button>'
            . '</td>'
            . '</tr>';

        $zDeleteForms .= '<form id="plan-delz-' . $z . '" method="post"'
            . ' action="/admin/plans-save.php?action=delete-z"'
            . ' data-confirm="' . e('Supprimer le niveau z=' . $z . ' du plan « ' . $planId
                . ' » : toutes ses cases et couches ?') . '">'
            . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
            . '<input type="hidden" name="plan" value="' . e($planId) . '">'
            . '<input type="hidden" name="z" value="' . $z . '">'
            . '</form>';
    }

    $zCard = $zRows === ''
        ? '<p class="text-muted mb-0">Aucun niveau Z (ni en base, ni dans le JSON). La première édition Tiled en créera.</p>'
        : '<table class="table table-sm mb-1"><thead><tr>'
            . '<th style="width:22%">Niveau</th><th>Nom affiché</th>'
            . '<th style="width:12%" class="text-center" title="Niveau volontairement sans carte (MapUnavailable)">Sans carte</th>'
            . '<th style="width:10%" class="text-center" title="Un coffre peut-il être posé à ce niveau ? (condition ChestSite de l\'action construire)">Coffres</th>'
            . '<th style="width:26%" title="Bornes visibles « minX,maxX,minY,maxY », ou « auto » (recalculées au prochain push Tiled)">Bornes visibles</th>'
            . '<th style="width:10%"></th>'
            . '</tr></thead><tbody>' . $zRows . '</tbody></table>'
            . '<small class="text-muted">Bornes : « minX,maxX,minY,maxY » explicites, ou « auto », recalculées'
            . ' sur l\'étendue réelle au prochain push Tiled du niveau.</small>';

    $bgChoices = (new TileCatalogService())->backgroundChoices();
    $bgCatalog = renderDatalist('plans-bg-catalog', array_combine($bgChoices, $bgChoices) ?: []);

    return (!$plan->hasCoords
            ? '<div class="alert alert-warning">Plan orphelin : config sans aucune'
                . ' coordonnée en base. Importez un bundle, ou créez la case d\'amorce en le recréant après suppression.</div>'
            : '')
        . (!$plan->hasConfig
            ? '<div class="alert alert-danger">Ce plan n\'a pas de config'
                . ' (table plans). Enregistrer ce formulaire la créera.</div>'
            : '')

        . '<form method="post" action="/admin/plans-save.php?action=update">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
        . '<input type="hidden" name="plan" value="' . e($planId) . '">'

        . '<div class="card mb-3"><div class="card-header">Identité &amp; carte du monde</div>'
        . '<div class="card-body"><div class="row">' . $identityFields . '</div></div></div>'

        . '<div class="card mb-3"><div class="card-header">Affichage</div>'
        . '<div class="card-body"><div class="row">' . $displayFields . '</div></div></div>'

        . '<div class="card mb-3"><div class="card-header">Rendements (ressources récoltables)</div>'
        . '<div class="card-body"><div class="row">' . $harvestField . '</div></div></div>'

        . '<div class="card mb-3"><div class="card-header">Niveaux Z</div>'
        . '<div class="card-body">' . $zCard . '</div></div>'

        . '<button type="submit" class="btn btn-primary">Enregistrer</button>'
        . '</form>'

        . '<div class="card mt-4"><div class="card-header">Validation du plan</div>'
        . '<div class="card-body">' . plans_validation_html($planId, $db, true) . '</div></div>'

        . plans_render_delete_zone($planId, $csrfToken)
        . $zDeleteForms
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
        $warningsHtml = '<ul class="mb-2 text-muted small">';
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
            $forceField = '<ul class="mb-2 text-muted small">' . $items . '</ul>'
                . '<label class="d-block mb-3"><input type="checkbox" name="force"> '
                . 'Forcer : supprimer les PNJ du plan (et leurs données) et détacher les logs.</label>';
        }

        $body = $warningsHtml
            . '<form method="post" action="/admin/plans-save.php?action=delete"'
            . ' data-confirm="' . e('Supprimer définitivement le plan « ' . $planId
                . ' » : fichier JSON, coordonnées, toutes les couches et les PNG générés ?') . '">'
            . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
            . '<input type="hidden" name="plan" value="' . e($planId) . '">'
            . $forceField
            . plans_typed_confirm_field($planId)
            . '<div class="d-flex align-items-center gap-3">'
            . '<button type="submit" class="btn btn-outline-danger">Supprimer le plan</button>'
            . '<small class="text-muted">Pensez à exporter un bundle JSON avant : il permet de restaurer le plan à l\'identique.</small>'
            . '</div></form>';
    }

    // Vider les cases : mêmes gardes que la suppression, mais la
    // configuration JSON (et les PNG) restent — le plan se re-peuple.
    $clearBody = '<form method="post" action="/admin/plans-save.php?action=clear"'
        . ' data-confirm="' . e('Vider toutes les cases du plan « ' . $planId
            . ' » (coordonnées et couches) ? La configuration JSON est conservée.') . '">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
        . '<input type="hidden" name="plan" value="' . e($planId) . '">'
        . ($hard === [] && $soft !== []
            ? '<label class="d-block mb-3"><input type="checkbox" name="force"> '
                . 'Forcer : supprimer les PNJ du plan (et leurs données) et détacher les logs.</label>'
            : '')
        . plans_typed_confirm_field($planId)
        . '<button type="submit" class="btn btn-outline-danger"' . ($hard !== [] ? ' disabled title="Blocages ci-dessus"' : '') . '>'
        . 'Vider les cases du plan</button>'
        . '</form>';

    // Renommage : code technique + coords + références par nom + fichiers.
    $renameBody = '<form method="post" action="/admin/plans-save.php?action=rename"'
        . ' data-confirm="' . e('Renommer le plan « ' . $planId
            . ' » partout (coordonnées, respawn de factions, races, téléporteurs, fichiers) ?') . '">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
        . '<input type="hidden" name="plan" value="' . e($planId) . '">'
        . '<div class="form-group"><label>Nouveau code technique</label>'
        . '<input type="text" class="form-control" name="new_plan" required'
        . ' pattern="[a-z0-9_-]{1,64}" placeholder="nouveau_code" style="max-width:20rem;"></div>'
        . plans_typed_confirm_field($planId)
        . '<button type="submit" class="btn btn-outline-danger">Renommer le plan</button>'
        . '</form>';

    return dangerZone('<h6>Renommer</h6>' . $renameBody
        . '<hr><h6>Vider les cases</h6>' . $clearBody
        . '<hr><h6>Supprimer</h6>' . $body);
}

/**
 * Champ de DOUBLE validation des opérations destructives : le code du
 * plan doit être retapé, vérifié côté serveur (plans-save.php) — la
 * boîte confirm() seule ne protège pas d'un clic réflexe.
 */
function plans_typed_confirm_field(string $planId): string
{
    return '<div class="form-group"><label>Confirmation : retapez le code du plan'
        . ' (<code>' . e($planId) . '</code>)</label>'
        . '<input type="text" class="form-control" name="confirm_code" required autocomplete="off"'
        . ' placeholder="' . e($planId) . '" style="max-width:20rem;"></div>';
}

/* -------------------------------------------------------------------------
 * Route
 * ---------------------------------------------------------------------- */
$db = new Db();
$csrfToken = (new CsrfProtectionService())->generateToken();
$inventory = plans_build_inventory($db);

$action = $_GET['action'] ?? 'list';

$back = ['/admin/plans.php', 'Plans'];
if ($action === 'new') {
    $title = 'Nouveau plan';
    $options = ['back' => $back];
    $content = plans_render_new_form($inventory, $csrfToken);
} elseif ($action === 'edit') {
    $planId = strtolower(trim((string) ($_GET['plan'] ?? '')));
    if (!preg_match(TiledMapService::PLAN_NAME_PATTERN, $planId) || !isset($inventory[$planId])) {
        setFlash('warning', 'Plan introuvable.');
        redirectTo('/admin/plans.php');
    }
    $plan = $inventory[$planId];
    $title = 'Plan : ' . e($plan->name) . ' <span class="text-muted">(' . e($planId) . ')</span>';
    $options = [
        'back' => $back,
        'actions' => '<a class="btn btn-sm btn-outline-secondary" title="Exporter ce plan (bundle JSON : config + coords + couches)"'
            . ' href="/admin/action-export.php?type=plan&amp;plan=' . e(urlencode($planId)) . '">'
            . '<i class="fas fa-download"></i> JSON</a>'
            . '<a class="btn btn-sm btn-outline-secondary" href="/admin/local_maps.php" title="Génération des PNG de carte">Cartes locales</a>',
    ];
    $content = plans_render_edit_form($plan, $csrfToken, $db);
} else {
    $title = 'Plans';
    $options = [
        'actions' => '<a class="btn btn-outline-secondary" href="/admin/action-import.php"'
            . ' title="Importer un bundle JSON de plan (avec prévisualisation avant application)">'
            . '<i class="fas fa-upload"></i> Importer</a>'
            . '<a class="btn btn-primary" href="/admin/plans.php?action=new">+ Nouveau plan</a>',
        'intro' => '<strong>Qu\'est-ce qu\'un plan ?</strong> Deux parties qui doivent rester cohérentes : '
            . '<strong>une config</strong> (tables <code>plans</code> / <code>plan_z_levels</code>'
            . ' : nom, niveaux Z, bornes visibles…) et <strong>des coordonnées en base</strong>'
            . ' (table <code>coords</code> + couches <code>map_*</code>).'
            . ' Le contenu des cartes s\'édite via l\'extension Tiled ; la génération des PNG se fait sur'
            . ' <a href="/admin/local_maps.php">Cartes locales</a>.',
    ];
    $content = plans_render_list($inventory, $db, $csrfToken);
}

echo admin_layout($title, $content, $options);
