<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/helpers.php';

/* Sans génération : pas d'aperçu — la vue plus bas teste ces deux-là. */
$imageUrl = '';
$showPreview = false;

use Classes\Db;
use App\Service\CsrfProtectionService;
use App\Service\ViewService;
use App\Service\ScreenshotService;

$database = new Db();
$screenshotService = new ScreenshotService();
$csrf = new CsrfProtectionService();

$viewService = new ViewService($database);
$allPlans = $viewService->getAllPlans('all');

// Filtre de saison partagé de la section « Cartes » (défaut : s2)
$seasonFilter = current_season_filter();
$filteredPlans = array_values(array_filter(
    $allPlans,
    fn(object $p) => plan_matches_season_filter($p, $seasonFilter)
));

$selectedPlanId = stringWithDefault('plan_id', '');
$selectedX      = intWithDefault('x', 0);
$selectedY      = intWithDefault('y', 0);
$selectedZ      = intWithDefault('z', 0);
$selectedRange  = intWithDefault('range', 5);

/* The POST renders the preview on the same request: no redirect. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_screenshot'])) {
    requireAdminPost('screenshots.php');
    try {

        $coords = [
            'x'    => $selectedX,
            'y'    => $selectedY,
            'z'    => $selectedZ,
            'plan' => $selectedPlanId,
        ];
        $timestamp = date('Y-m-d_H-i-s');
        $filename  = "screenshot_{$selectedPlanId}_{$selectedX}_{$selectedY}_{$selectedZ}_{$timestamp}";
        // selfContained: the preview below goes through <img src="….svg">,
        // which parses strict XML and loads no external resource.
        $result    = $screenshotService->generateScreenshot(
            $coords,
            $selectedRange,
            $filename,
            selfContained: true
        );

        if ($result['success']) {
            setFlash('success', "Capture d'écran générée avec succès : " . basename($result['filepath']));
            $imageUrl    = str_replace($_SERVER['DOCUMENT_ROOT'], '', $result['filepath']);
            $showPreview = true;
        } else {
            setFlash('danger', 'Erreur lors de la génération : ' . $result['error']);
        }
    } catch (RuntimeException $e) {
        setFlash('danger', $e->getMessage());
    }
}

ob_start();
?>

<div class="container">
    <div class="card mb-3">
        <div class="card-body py-2">
            <?= render_season_filter($seasonFilter) ?>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="post">
                <?= $csrf->renderTokenField() ?>
                <div class="form-group">
                    <label for="plan_id">Plan (<?= e(season_filter_label($seasonFilter)) ?>) :</label>
                    <?php
                    $planChoices = [];
                    foreach ($filteredPlans as $plan) {
                        $planChoices[$plan->id] = $plan->name . ' (' . $plan->id . ')';
                    }
                    echo formSelect('plan_id', $planChoices,
                        $selectedPlanId !== '' ? $selectedPlanId : null, null,
                        'class="form-control" id="plan_id" required');
                    ?>
                    <?php if (empty($filteredPlans)): ?>
                        <small class="text-muted">Aucun plan ne correspond au filtre « <?= e(season_filter_label($seasonFilter)) ?> » — élargir le filtre ci-dessus.</small>
                    <?php endif; ?>
                </div>

                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="x">X :</label>
                            <input type="number" class="form-control" id="x" name="x"
                                value="<?= e($selectedX) ?>" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="y">Y :</label>
                            <input type="number" class="form-control" id="y" name="y"
                                value="<?= e($selectedY) ?>" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="z">Z :</label>
                            <input type="number" class="form-control" id="z" name="z"
                                value="<?= e($selectedZ) ?>" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="range">Portée :</label>
                            <input type="number" class="form-control" id="range" name="range"
                                value="<?= e($selectedRange) ?>" min="1" max="30" required>
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <button type="submit" name="generate_screenshot" class="btn btn-primary">
                        <i class="fas fa-camera"></i> Générer la capture
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($showPreview): ?>
    <div class="card mt-4">
        <div class="card-header">Aperçu de la capture</div>
        <div class="card-body text-center">
            <div style="overflow: auto;">
                <img src="<?= e($imageUrl) ?>" alt="Capture d'écran" class="img-fluid">
            </div>
            <div class="mt-3">
                <a href="<?= e($imageUrl) ?>" class="btn btn-primary" download>
                    <i class="fas fa-download"></i> Télécharger l'image
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>

<?php
$content = ob_get_clean();
echo admin_layout('Captures', $content);
