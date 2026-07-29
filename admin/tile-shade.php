<?php
/**
 * Ombres des cases (admin → Cartes → Ombres).
 *
 * Un réglage de carte, pas une option générale du jeu : il vivait sur le
 * tableau de bord, où l'on ne pense pas à le chercher.
 *
 * POST traité ici même (CSRF, PRG), comme admin/tile-assets.php.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CellShadeService;
use App\Service\CsrfProtectionService;

$csrf = new CsrfProtectionService();
$shade = new CellShadeService();

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
    redirectTo('/admin/tile-shade.php');
}

ob_start();
?>

<div class="container">
    <h2 class="section-title">Ombres des cases</h2>

    <?= renderFlashMessage() ?>

    <div class="card mt-3" style="max-width: 640px;">
        <div class="card-header"><strong>Ombres des cases</strong></div>
        <div class="card-body">
            <form method="post" action="tile-shade.php">
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

echo admin_layout('Ombres des cases', $content);
