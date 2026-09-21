<?php
/**
 * Admin dashboard: the entry page, plus the settings that hold for the
 * whole game.
 *
 * Each setting is one form, posted here and redirected after (CSRF, PRG:
 * a refresh must not resubmit), and stored in admin_settings through
 * AdminSettingsService:
 *   - World (SeasonService, PlanService): current season, main plan
 *     carrying the world map, plan the dead land on.
 *   - Date format (DateFormatService): followed by every display going
 *     through format(); legacy date() calls move over as they are touched.
 *   - Life of a harvestable resource (HarvestDefaultsService): a default
 *     read at creation, never applied back to placed types.
 *   - Minimum Tiled extension version (TiledExtensionService): raising it
 *     after an extension release is an edit here, not a deployment.
 *
 * A setting that belongs to a map goes on that map's page, not here: cell
 * shade lives in Cartes → Ombres (admin/tile-shade.php).
 *
 * The banners above the forms watch what would otherwise break in
 * silence — a plan carrying resources with no yields, a table left over
 * from a finished chantier.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\AdminSettingsService;
use App\Service\CsrfProtectionService;
use App\Service\DateFormatService;
use App\Service\Map\HarvestDefaultsService;
use App\Service\PlanService;
use App\Service\SeasonService;
use App\Service\TiledExtensionService;

$csrf = new CsrfProtectionService();
$dateFormat = new DateFormatService();
$harvestDefaults = new HarvestDefaultsService();
$decayDefaults = new \App\Service\Decay\DecayDefaultsService();
$repairSettings = new \App\Service\AdminSettingsService();
$seasonService = new SeasonService();
$tiledExtension = new TiledExtensionService();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['world_settings'])) {
    try {
        $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);

        $season = (int) ($_POST['game_season'] ?? 0);
        if ($season < 1) {
            throw new \RuntimeException('Saison invalide (numéro attendu, 1 ou plus).');
        }

        // Both slugs must be real plans: the world map and the death
        // teleport point at them.
        $settings = new AdminSettingsService();
        foreach ([PlanService::SETTING_WORLD => 'plan principal', PlanService::SETTING_DEATH => 'plan des morts'] as $key => $label) {
            $slug = trim((string) ($_POST[$key] ?? ''));
            if (!plans()->exists($slug)) {
                throw new \RuntimeException("Plan inconnu pour le {$label} : {$slug}");
            }
            $settings->set($key, $slug);
        }

        $seasonService->setCurrent($season);
        PlanService::forget();

        setFlash('success', 'Réglages du monde enregistrés.');
    } catch (\Throwable $e) {
        setFlash('danger', 'Échec : ' . $e->getMessage());
    }
    redirectTo('/admin/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['repair_full_share'])) {
    try {
        $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
        foreach (array_keys(\App\Service\RepairService::SETTINGS) as $name) {
            $repairSettings->set($name, (string) max(0, (int) ($_POST[$name] ?? 0)));
        }
        setFlash('success', 'Atelier : réglages enregistrés.');
    } catch (\Throwable $e) {
        setFlash('danger', 'Échec : ' . $e->getMessage());
    }
    redirectTo('/admin/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['harvest_default_pv'])) {
    try {
        $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
        $harvestDefaults->setPv((int) $_POST['harvest_default_pv']);
        setFlash('success', 'Points de vie par défaut d\'une ressource : ' . $harvestDefaults->pv() . '.');
    } catch (\Throwable $e) {
        setFlash('danger', 'Échec : ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['decay_rate_default'])) {
    try {
        $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
        $decayDefaults->setRate((int) $_POST['decay_rate_default']);
        $decayDefaults->setGraceTurns((int) ($_POST['decay_grace_turns'] ?? 0));
        setFlash('success', 'Décrépitude : ' . $decayDefaults->rate() . ' PV par tour après '
            . $decayDefaults->graceTurns() . ' tour(s) sans usage.');
    } catch (\Throwable $e) {
        setFlash('danger', 'Échec : ' . $e->getMessage());
    }
    redirectTo('/admin/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tiled_min_extension'])) {
    try {
        $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
        $tiledExtension->setMinimum((string) $_POST['tiled_min_extension']);
        setFlash('success', 'Extension Tiled : version minimale portée à ' . $tiledExtension->minimum() . '.');
    } catch (\Throwable $e) {
        setFlash('danger', 'Échec : ' . $e->getMessage());
    }
    redirectTo('/admin/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_bundle_max_mb'])) {
    try {
        $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
        $mb = (int) $_POST['import_bundle_max_mb'];
        if ($mb < 1) {
            throw new \RuntimeException('Taille invalide (1 Mo ou plus).');
        }
        (new AdminSettingsService())->set('import_bundle_max_mb', (string) $mb);
        setFlash('success', 'Import de bundle : taille maximale portée à ' . $mb . ' Mo.');
    } catch (\Throwable $e) {
        setFlash('danger', 'Échec : ' . $e->getMessage());
    }
    redirectTo('/admin/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['date_format'])) {
    try {
        $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
        $dateFormat->set((string) $_POST['date_format']);
        setFlash('success', 'Format des dates enregistré.');
    } catch (\Throwable $e) {
        setFlash('danger', $e->getMessage());
    }
    redirectTo('/admin/index.php'); /* PRG : pas de re-soumission au refresh */
}

$today = date('Y-m-d');

ob_start();
?>

<div class="container">
    <h2 class="section-title">Tableau de bord</h2>
    <p class="text-content">Choisissez une entrée dans le menu latéral, ou réglez les options générales ci-dessous.</p>

    <?= renderFlashMessage() ?>

    <?php
    /* Ce qui casse en silence si personne ne regarde : un plan qui porte des
       ressources sans rendement réglé n'en donne aucune. Le tableau de bord le
       dit, faute de quoi il faudrait penser à ouvrir la bonne page. */
    $missingYields = (new \App\Service\Map\HarvestCatalogService())->plansMissingYields();
    ?>
    <?php if ($missingYields !== []): ?>
        <div class="alert alert-danger mt-3">
            <strong>Fouiller ne rapporte rien sur <?= count($missingYields) ?> plan(s).</strong>
            Ils contiennent des ressources récoltables dont le type n'a aucun rendement défini.
            <a href="/admin/harvest-seed.php" class="alert-link">Régler les rendements</a>.
        </div>
    <?php endif; ?>

    <?php
    /* Un reste de chantier ne se retient pas de mémoire : il se montre, et il
       s'efface tout seul le jour du dépôt. */
    $mapResources = (new \App\Service\Map\MapResourcesRetirement())->status();
    ?>
    <?php if ($mapResources['present'] || $mapResources['view']): ?>
        <div class="alert <?= $mapResources['droppable'] ? 'alert-info' : 'alert-warning' ?> mt-3">
            <?php if ($mapResources['droppable']): ?>
                <strong>Reste de chantier : <code>map_resources</code><?= $mapResources['view'] ? ' et la vue <code>map_walls</code>' : '' ?>.</strong>
                Plus aucun code ne les lit ni ne les écrit, et la table est vide : les ressources sont des entités.
                <strong>À supprimer</strong> une fois ce code déployé sur tous les serveurs (pour une
                suppression, la migration passe après le code, à l'inverse de l'habitude).
                Cet avertissement disparaîtra alors.
            <?php else: ?>
                <strong><code>map_resources</code> ne peut pas encore être supprimée.</strong>
                <?= e(implode(' ; ', $mapResources['blockers'])) ?>.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php $ownershipLink = (new \App\Service\OwnershipLinkRetirement())->status(); ?>
    <?php if ($ownershipLink['present']): ?>
        <div class="alert <?= $ownershipLink['droppable'] ? 'alert-info' : 'alert-warning' ?> mt-3">
            <?php if ($ownershipLink['droppable']): ?>
                <strong>Reste de chantier : <code>players_items_instances</code>.</strong>
                Plus aucun code ne la lit ni ne l'écrit : le porteur d'un exemplaire est enregistré sur l'entité.
                <strong>À supprimer</strong> une fois ce code déployé sur tous les serveurs (pour une
                suppression, la migration passe après le code, à l'inverse de l'habitude).
                Cet avertissement disparaîtra alors.
            <?php else: ?>
                <strong><code>players_items_instances</code> ne peut pas encore être supprimée.</strong>
                <?= e(implode(' ; ', $ownershipLink['blockers'])) ?>.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="row mt-3">
    <div class="col-md-6">
    <div class="card">
        <div class="card-header"><strong>Réglages du monde</strong></div>
        <div class="card-body">
            <form method="post" action="index.php">
                <?= $csrf->renderTokenField() ?>
                <input type="hidden" name="world_settings" value="1">
                <?php
                // Every configured plan except the ephemeral tutorial
                // instances, labeled with its season.
                $planChoices = [];
                foreach (plans()->all() as $slug => $planData) {
                    if (str_starts_with($slug, 'tut_')) {
                        continue;
                    }
                    $seasonLabel = isset($planData->season) ? 'S' . $planData->season : 'toutes saisons';
                    $planChoices[$slug] = ($planData->name ?? $slug) . ' (' . $slug . ') — ' . $seasonLabel;
                }
                ?>
                <div class="d-flex gap-3 flex-wrap align-items-end">
                    <div>
                        <label class="form-label mb-0">Saison courante</label><br />
                        <input type="number" name="game_season" min="1" step="1" class="form-select" style="max-width: 100px;"
                               value="<?= (int) $seasonService->current() ?>" />
                    </div>
                    <div>
                        <label class="form-label mb-0">Plan principal (carte du monde)</label>
                        <select name="<?= e(PlanService::SETTING_WORLD) ?>" class="form-select" style="max-width: 340px;">
                            <?= renderSelectOptions($planChoices, plans()->worldPlan()) ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label mb-0">Plan des morts</label>
                        <select name="<?= e(PlanService::SETTING_DEATH) ?>" class="form-select" style="max-width: 340px;">
                            <?= renderSelectOptions($planChoices, plans()->deathPlan()) ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary">Enregistrer</button>
                </div>
                <small class="form-text text-muted">
                    La saison courante est la saison par défaut des listes de plans (carte du monde,
                    pages Cartes). Le plan principal est celui de la carte du monde ; les personnages
                    morts sont envoyés sur le plan des morts. Un plan référencé ici ne peut pas être supprimé.
                </small>
            </form>
        </div>
    </div>

    </div>
    <div class="col-md-6">
    <div class="card">
        <div class="card-header"><strong>Options générales</strong></div>
        <div class="card-body">
            <form method="post" action="index.php">
                <?= $csrf->renderTokenField() ?>
                <label class="form-label mb-0">Format d'affichage des dates dans le jeu</label>
                <div class="d-flex gap-2 align-items-center">
                    <select name="date_format" class="form-select" style="max-width: 340px;">
                        <?= renderSelectOptions(DateFormatService::FORMATS, $dateFormat->current()) ?>
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary">Enregistrer</button>
                </div>
                <div class="text-muted mt-2" style="font-size: 13px;">
                    Aujourd'hui s'affiche : « <?= e($dateFormat->format($today)) ?> ».
                    Utilisé par les affichages qui passent par <code style="display:inline">DateFormatService</code>
                    (chroniques de l'accueil…) ; la saisie admin reste en JJ/MM/AAAA.
                </div>
            </form>

            <hr />

            <form method="post" action="index.php">
                <?= $csrf->renderTokenField() ?>
                <label class="form-label mb-0">Points de vie d'une ressource récoltable</label>
                <div class="d-flex gap-2 align-items-center">
                    <input type="number" name="harvest_default_pv" min="1" max="10000"
                           class="form-select" style="max-width: 120px;"
                           value="<?= (int) $harvestDefaults->pv() ?>" />
                    <button type="submit" class="btn btn-sm btn-primary">Enregistrer</button>
                </div>
                <small class="form-text text-muted">
                    Nombre de coups pour abattre un arbre. Valeur par défaut à la
                    <strong>création</strong> d'un type récoltable ; un type existant conserve sa valeur.
                </small>
            </form>

            <hr />

            <form method="post" action="index.php">
                <?= $csrf->renderTokenField() ?>
                <label class="form-label mb-0">Décrépitude des constructions</label>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <input type="number" name="decay_rate_default" min="1" max="1000"
                           class="form-select" style="max-width: 100px;"
                           value="<?= (int) $decayDefaults->rate() ?>" />
                    <span class="text-muted">PV par tour, après</span>
                    <input type="number" name="decay_grace_turns" min="0" max="1000"
                           class="form-select" style="max-width: 100px;"
                           value="<?= (int) $decayDefaults->graceTurns() ?>" />
                    <span class="text-muted">tour(s) sans usage</span>
                    <button type="submit" class="btn btn-sm btn-primary">Enregistrer</button>
                </div>
                <small class="form-text text-muted">
                    Les constructions des <strong>joueurs</strong> se dégradent ; celles posées avec Tiled, non.
                    Utiliser une construction repousse le début de l'usure ; marcher sur une route la
                    répare. Un mur ne s'entretient qu'en le réparant. Un type peut définir ses propres
                    valeurs. À zéro PV, la construction est détruite.
                    <strong>Lu à chaque usage</strong> : un changement s'applique progressivement,
                    sans migration.
                </small>
            </form>

            <hr />

            <form method="post" action="index.php">
                <?= $csrf->renderTokenField() ?>
                <label class="form-label mb-0">Atelier : réparation et recyclage</label>
                <?php $repairField = static function (string $name, string $label) use ($repairSettings): void { ?>
                    <div class="d-flex gap-2 align-items-center">
                        <input type="number" name="<?= $name ?>" min="0" max="1000"
                               class="form-select" style="max-width: 100px;"
                               value="<?= (int) $repairSettings->get($name, (string) \App\Service\RepairService::SETTINGS[$name]) ?>" />
                        <span class="text-muted"><?= $label ?></span>
                    </div>
                <?php }; ?>
                <?php $repairField('repair_full_share', '% de la recette pour réparer un objet à 1 PV (au prorata des PV manquants)'); ?>
                <?php $repairField('repair_labour_share', '% de la valeur des ressources en main-d\'œuvre (1 PO minimum)'); ?>
                <?php $repairField('repair_gold_margin', '% du prix des ressources quand tout est payé en or'); ?>
                <?php $repairField('recycle_share', '% des ingrédients rendus au recyclage d\'un objet brisé'); ?>
                <button type="submit" class="btn btn-sm btn-primary mt-2">Enregistrer</button>
                <small class="form-text text-muted">
                    Prix des ressources : colonne <code>price</code> de chaque objet. Points de vie d'un
                    objet : sa colonne <code>durability_max</code>. <strong>Lu à chaque devis.</strong>
                </small>
            </form>

            <hr />

            <form method="post" action="index.php">
                <?= $csrf->renderTokenField() ?>
                <label class="form-label mb-0">Version minimale de l'extension Tiled</label>
                <div class="d-flex gap-2 align-items-center">
                    <input type="text" name="tiled_min_extension" pattern="v?[0-9]+(\.[0-9]+){0,2}"
                           class="form-select" style="max-width: 120px;"
                           value="<?= e($tiledExtension->minimum()) ?>" />
                    <button type="submit" class="btn btn-sm btn-primary">Enregistrer</button>
                </div>
                <small class="form-text text-muted">
                    Les extensions plus anciennes que ce numéro sont refusées par les endpoints Tiled, avec un
                    message qui indique quoi télécharger : une extension trop ancienne utilise un protocole
                    différent et produit des erreurs silencieuses. À relever <strong>après</strong> la publication de la
                    <a href="<?= e(TiledExtensionService::DOWNLOAD_URL) ?>">release correspondante</a>,
                    jamais avant : tant que le zip n'est pas en ligne, tout le monde est bloqué.
                    Avant la v<?= e(TiledExtensionService::FIRST_VERSIONED) ?>, une extension
                    n'annonçait pas sa version : elle est refusée dans tous les cas.
                </small>
            </form>

            <hr />

            <form method="post" action="index.php">
                <?= $csrf->renderTokenField() ?>
                <label class="form-label mb-0">Taille maximale d'un bundle importé (Mo)</label>
                <div class="d-flex gap-2 align-items-center">
                    <input type="number" name="import_bundle_max_mb" min="1" step="1"
                           class="form-select" style="max-width: 120px;"
                           value="<?= (int) (new AdminSettingsService())->get('import_bundle_max_mb', '200') ?>" />
                    <button type="submit" class="btn btn-sm btn-primary">Enregistrer</button>
                </div>
                <small class="form-text text-muted">
                    Taille maximale du fichier .json accepté par Actions → Import. Le serveur limite aussi la
                    requête : ici <?= e(ini_get('upload_max_filesize')) ?> (upload_max_filesize),
                    <?= e(ini_get('post_max_size')) ?> (post_max_size) ; un réglage plus élevé n'a
                    pas d'effet.
                </small>
            </form>
        </div>
    </div>
    </div>
    </div>

</div>

<?php
$content = ob_get_clean();
echo admin_layout('Tableau de bord', $content);
