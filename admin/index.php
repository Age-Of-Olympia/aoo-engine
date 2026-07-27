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
 * Le POST est traité ici même (CSRF, PRG), comme admin/tile-assets.php.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CellShadeService;
use App\Service\CsrfProtectionService;
use App\Service\DateFormatService;

$csrf = new CsrfProtectionService();
$dateFormat = new DateFormatService();
$shade = new CellShadeService();

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['shade_step'])) {
    try {
        $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
        $shade->save(
            (float) str_replace(',', '.', (string) $_POST['shade_step']),
            (int) ($_POST['shade_max'] ?? CellShadeService::DEFAULT_MAX),
            (string) ($_POST['shade_color'] ?? CellShadeService::DEFAULT_COLOR)
        );
        setFlash('success', 'Réglages des ombres enregistrés.');
    } catch (\Throwable $e) {
        setFlash('danger', $e->getMessage());
    }
    redirectTo('/admin/index.php');
}

$today = date('Y-m-d');

ob_start();
?>

<div class="container">
    <h2 class="section-title">Tableau de bord</h2>
    <p class="text-content">Choisissez une entrée dans le menu latéral, ou réglez les options générales ci-dessous.</p>

    <?= renderFlashMessage() ?>

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
        </div>
    </div>

    <div class="card mt-3" style="max-width: 640px;">
        <div class="card-header"><strong>Ombres des cases</strong></div>
        <div class="card-body">
            <form method="post" action="index.php">
                <?= $csrf->renderTokenField() ?>

                <p class="text-muted" style="font-size: 13px;">
                    Une case porte un <em>niveau</em> d'ombre (0 à
                    <?= (int) $shade->maxLevel() ?>), posé au pinceau dans
                    l'éditeur — re-cliquer fonce. Ces réglages disent ce qu'un
                    niveau vaut à l'écran : les changer n'oblige pas à
                    reprendre les cases.
                </p>
                <p class="text-muted" style="font-size: 13px;">
                    <strong>Ce sont les valeurs par défaut.</strong> Chaque plan
                    peut les surcharger — une grotte plus sombre, un plan de
                    glace plus bleu — depuis
                    <a href="plans.php">Plans</a> ou depuis Tiled, avec les
                    propriétés <code style="display:inline">shade_step</code>,
                    <code style="display:inline">shade_max</code> et
                    <code style="display:inline">shade_color</code>.
                </p>

                <label class="form-label mb-0">Opacité d'un niveau (entre 0 et 1)</label>
                <input type="text" name="shade_step" class="form-select" style="max-width: 160px;"
                       value="<?= e((string) $shade->step()) ?>" />

                <label class="form-label mb-0 mt-2">Niveau maximal au pinceau</label>
                <input type="number" name="shade_max" min="1" max="255" class="form-select"
                       style="max-width: 160px;" value="<?= (int) $shade->maxLevel() ?>" />

                <label class="form-label mb-0 mt-2">Couleur</label>
                <input type="color" name="shade_color" class="form-select" style="max-width: 100px;"
                       value="<?= e($shade->color()) ?>" />

                <div class="mt-2">
                    <button type="submit" class="btn btn-sm btn-primary">Enregistrer</button>
                </div>

                <div class="text-muted mt-2" style="font-size: 13px;">
                    Rendu des niveaux :
                    <?php foreach (range(1, min(5, $shade->maxLevel())) as $level): ?>
                        <span style="display:inline-block;width:34px;height:18px;vertical-align:middle;
                                     border:1px solid #999;background:#e7ded0;">
                            <span style="display:block;width:100%;height:100%;
                                         background:<?= e($shade->color()) ?>;
                                         opacity:<?= $shade->opacityFor($level) ?>;"></span>
                        </span>
                        <span style="font-size:12px;"><?= $level ?> — <?= round($shade->opacityFor($level) * 100, 1) ?> %</span>
                    <?php endforeach; ?>
                </div>
                <div class="text-muted mt-1" style="font-size: 13px;">
                    Le défaut (<?= CellShadeService::DEFAULT_STEP ?>) reproduit exactement l'ancien
                    décor <code style="display:inline">ombre</code> ; le modifier éclaircit ou
                    fonce toute la carte d'un coup.
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
echo admin_layout('Tableau de bord', $content);
