<?php
/**
 * Tableau de bord admin : accueil du dashboard + options générales du jeu.
 *
 * Options générales — réglages globaux d'affichage, stockés dans
 * admin_settings (AdminSettingsService) :
 *   - Format des dates (DateFormatService) : suivi par tout affichage
 *     de date passant par DateFormatService::format() — aujourd'hui les
 *     chroniques de l'accueil ; les date() hérités migrent au fil de l'eau.
 *
 * L'ombre des cases est partie dans Cartes → Ombres (admin/tile-shade.php) :
 * c'est un réglage de CARTE, pas une option générale du jeu.
 *
 * Le POST est traité ici même (CSRF, PRG), comme admin/tile-assets.php.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\AdminSettingsService;
use App\Service\CsrfProtectionService;
use App\Service\DateFormatService;
use App\Service\Map\HarvestDefaultsService;
use App\Service\PlanService;
use App\Service\SeasonService;

$csrf = new CsrfProtectionService();
$dateFormat = new DateFormatService();
$harvestDefaults = new HarvestDefaultsService();
$seasonService = new SeasonService();

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
        </div>
    </div>

</div>

<?php
$content = ob_get_clean();
echo admin_layout('Tableau de bord', $content);
