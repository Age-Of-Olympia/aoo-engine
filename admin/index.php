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

use App\Service\CsrfProtectionService;
use App\Service\DateFormatService;
use App\Service\Map\HarvestDefaultsService;

$csrf = new CsrfProtectionService();
$dateFormat = new DateFormatService();
$harvestDefaults = new HarvestDefaultsService();

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
