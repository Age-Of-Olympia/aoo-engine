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
        <div class="alert alert-danger mt-3" style="max-width: 640px;">
            <strong>Fouiller ne rapporte rien sur <?= count($missingYields) ?> plan(s).</strong>
            Ils portent des ressources récoltables sans aucun rendement réglé.
            <a href="/admin/harvest-seed.php" class="alert-link">Régler les rendements</a>.
        </div>
    <?php endif; ?>

    <?php
    /* Un reste de chantier ne se retient pas de mémoire : il se montre, et il
       s'efface tout seul le jour du dépôt. */
    $mapResources = (new \App\Service\Map\MapResourcesRetirement())->status();
    ?>
    <?php if ($mapResources['present'] || $mapResources['view']): ?>
        <div class="alert <?= $mapResources['droppable'] ? 'alert-info' : 'alert-warning' ?> mt-3" style="max-width: 640px;">
            <?php if ($mapResources['droppable']): ?>
                <strong>Reste de chantier : <code>map_resources</code><?= $mapResources['view'] ? ' et la vue <code>map_walls</code>' : '' ?>.</strong>
                Plus aucun lecteur, plus aucun écrivain, zéro ligne : les ressources sont des entités.
                <strong>Prêtes à être déposées</strong>, une fois le code qui a cessé de les lire déployé
                partout — migrations après code pour une suppression, l'inverse de l'habitude.
                Cet avertissement disparaîtra de lui-même.
            <?php else: ?>
                <strong><code>map_resources</code> n'est pas encore déposable.</strong>
                <?= e(implode(' ; ', $mapResources['blockers'])) ?>.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php $ownershipLink = (new \App\Service\OwnershipLinkRetirement())->status(); ?>
    <?php if ($ownershipLink['present']): ?>
        <div class="alert <?= $ownershipLink['droppable'] ? 'alert-info' : 'alert-warning' ?> mt-3" style="max-width: 640px;">
            <?php if ($ownershipLink['droppable']): ?>
                <strong>Reste de chantier : <code>players_items_instances</code>.</strong>
                Plus aucun lecteur, plus aucun écrivain : le porteur d'un exemplaire vit sur l'entité.
                <strong>Prête à être déposée</strong>, une fois le code qui a cessé de la lire déployé
                partout — migrations après code pour une suppression, l'inverse de l'habitude.
                Cet avertissement disparaîtra de lui-même.
            <?php else: ?>
                <strong><code>players_items_instances</code> n'est pas encore déposable.</strong>
                <?= e(implode(' ; ', $ownershipLink['blockers'])) ?>.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="card mt-3" style="max-width: 640px;">
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
                    La saison courante est celle que prennent par défaut les listes de plans (carte du monde,
                    pages Cartes). Le plan principal porte la carte du monde ; le plan des morts accueille
                    les personnages tombés. Un plan référencé ici ne peut pas être supprimé.
                </small>
            </form>
        </div>
    </div>

    <div class="card mt-3" style="max-width: 640px;">
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
                    Suivi par les affichages passés à <code style="display:inline">DateFormatService</code>
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
                    Combien de coups il faut pour abattre un arbre. Sert de valeur par défaut à la
                    <strong>création</strong> d'un type récoltable ; un type déjà réglé garde la sienne.
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
                    Ce que les <strong>joueurs</strong> ont bâti se dégrade ; ce que Tiled a posé, non.
                    S'en servir repousse l'échéance — marcher sur une route la répare même. Un mur,
                    lui, ne s'entretient qu'en le réparant. Un type peut porter ses propres valeurs.
                    À zéro, la construction est détruite.
                    <strong>Lu à chaque usage</strong> : changer ces valeurs déplace le monde,
                    progressivement, sans migration.
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
                    Les éditeurs plus anciens que ce numéro sont refusés par les endpoints Tiled, avec un
                    message qui dit quoi télécharger — une extension d'un autre âge parle un protocole
                    changé et se trompe en silence. À relever <strong>après</strong> la publication de la
                    <a href="<?= e(TiledExtensionService::DOWNLOAD_URL) ?>">release correspondante</a>,
                    jamais avant : la barre ferme la porte à tout le monde tant que le zip n'est pas en
                    ligne. Avant la v<?= e(TiledExtensionService::FIRST_VERSIONED) ?>, une extension
                    n'annonçait pas sa version : elle est refusée quoi qu'il arrive.
                </small>
            </form>
        </div>
    </div>

</div>

<?php
$content = ob_get_clean();
echo admin_layout('Tableau de bord', $content);
