<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/helpers.php';
use Classes\Db;
use App\Service\CsrfProtectionService;
use App\Service\ViewService;
use App\Service\PlanJsonValidator;

// render_validation_group / render_validation_report : admin/helpers.php
// (partagés avec admin/plans.php), comme le tri des plans et le filtre de saison

// Clear any world map layers when loading local maps
if (isset($_SESSION['generated_layers']) && strpos(json_encode($_SESSION['generated_layers']), 'world_') !== false) {
    unset($_SESSION['generated_layers']);
}

$database = new Db();
$csrf = new CsrfProtectionService();
$selectedPlan   = optionalString('selected_plan');
$selectedZLevel = optionalString('selected_z_level');
$viewService = new ViewService($database, 0, 0, 0, 0, $selectedPlan);

// Get all available plans (local maps)
$allPlans = $viewService->getAllPlans('all');

$isStateChangingPost = $_SERVER['REQUEST_METHOD'] === 'POST'
    && (isset($_POST['cleanup_local']) || isset($_POST['generate_local']) || isset($_POST['autodetect_bounds']));
if ($isStateChangingPost) {
    requireAdminPost('local_maps.php');
}

usort($allPlans, 'plan_order');

// Filtre de saison (défaut : saison courante s2) — vue d'ensemble et liste
// déroulante ; $allPlans reste complet pour les traitements internes
// (nettoyage des PNG, plan sélectionné).
$seasonFilter = current_season_filter();
$filteredPlans = array_values(array_filter(
    $allPlans,
    fn(object $p) => plan_matches_season_filter($p, $seasonFilter)
));

if (isset($_POST['autodetect_bounds'])) {
    $filled = (new \App\Service\PlanConfigService())->fillMissingBounds();
    setFlash('success', $filled === []
        ? 'Aucune borne manquante.'
        : 'Bornes détectées : ' . implode(', ', $filled) . '.');
    redirectTo('local_maps.php');
}

// Cleanup button at top
if (isset($_POST['cleanup_local'])) {
    $deleted = [];
    $kept = [];
    $localMapsDir = $_SERVER['DOCUMENT_ROOT'].'/img/maps/local/';
    $files = glob($localMapsDir.'local_*.png');
    $mapGroups = [];
    
    // Get valid plan names
    $validPlans = array_map(function($plan) { return $plan->id ?? $plan['id']; }, $allPlans);
    
    foreach ($files as $file) {
        $filename = basename($file);
        if (preg_match('/local_([^_]+(?:_[^_]+)*)_(\d+)_([^_]+)_(\d{8}-\d{6})\.png/', $filename, $matches)) {
            $planName = $matches[1];
            $zLevel = $matches[2];
            $layer = $matches[3];
            
            // Only process files from known plans
            if (in_array($planName, $validPlans)) {
                $key = "{$planName}_{$zLevel}_{$layer}";
                $timestamp = $matches[4];
                $mapGroups[$key][] = ['file' => $file, 'mtime' => filemtime($file), 'timestamp' => $timestamp];
            }
        }
    }
    
    foreach ($mapGroups as $key => $group) {
        if (count($group) > 1) {
            usort($group, function($a, $b) { return $b['mtime'] - $a['mtime']; });
            
            // Keep the newest file
            $kept[] = sprintf("Kept: %s (generated %s)", basename($group[0]['file']), date('Y-m-d H:i:s', $group[0]['mtime']));
            
            // Delete older versions
            for ($i = 1; $i < count($group); $i++) {
                if (@unlink($group[$i]['file'])) {
                    $deleted[] = sprintf("Deleted: %s (generated %s)", basename($group[$i]['file']), date('Y-m-d H:i:s', $group[$i]['mtime']));
                }
            }
        } else {
            $kept[] = sprintf("Kept: %s (only version)", basename($group[0]['file']));
        }
    }
    
    $cleanupReport = [
        'kept' => $kept,
        'deleted' => $deleted
    ];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_local'])) {
    try {
        $layers = ['tiles', 'elements', 'foregrounds', 'resources', 'routes', 'buildings'];

        $viewService = new ViewService($database, 0, 0, $selectedZLevel, 0, $selectedPlan);
        $results = $viewService->generateLocalMap($layers);
        
        if (!empty($results)) {
            setFlash('success', 'Couches générées avec succès' . ($selectedZLevel !== null ? " pour le niveau Z $selectedZLevel" : ''));
            $_SESSION['generated_layers'] = $results;
        }
    } catch (Exception $e) {
        setFlash('danger', 'Erreur lors de la génération : ' . $e->getMessage());
    }
}

ob_start();
?>

<div class="container">
    <div class="card">
        <div class="card-body py-2">
            <?= render_season_filter($seasonFilter) ?>
        </div>
    </div>

    <?php if (!$selectedPlan):
        // Vue d'ensemble : santé des plans de la saison filtrée, tant qu'aucun
        // n'est sélectionné.

        // Vue d'ensemble restreinte aux plans du filtre de saison courant.
        $overviewPlans = $filteredPlans;

        $plansWithIssues = [];
        $okCount = 0;
        foreach ($overviewPlans as $p) {
            $raw = plans()->read($p->id);
            if ($raw === null || $raw === false) {
                // Config absente : problème de plan, rendu à part.
                $plansWithIssues[] = [
                    'plan' => $p, 'errCount' => 1, 'warnCount' => 0, 'v' => null,
                    'emptyMsg' => 'Config du plan absente, aucune récolte possible sur ce plan.',
                ];
                continue;
            }
            $v = PlanJsonValidator::validate($raw, $p->id, $database);
            $errCount  = count($v['errors']);
            $warnCount = count($v['warnings']);
            if ($errCount > 0 || $warnCount > 0) {
                $plansWithIssues[] = ['plan' => $p, 'errCount' => $errCount, 'warnCount' => $warnCount, 'v' => $v, 'emptyMsg' => null];
            } else {
                $okCount++;
            }
        }

        // Tri : plans en erreur d'abord (par nombre décroissant), puis avertissements seuls.
        usort($plansWithIssues, function ($a, $b) {
            return ($b['errCount'] <=> $a['errCount']) ?: ($b['warnCount'] <=> $a['warnCount']);
        });

        $errPlanCount  = count(array_filter($plansWithIssues, fn($x) => $x['errCount'] > 0));
        $warnPlanCount = count($plansWithIssues) - $errPlanCount;
        $totalPlans    = count($overviewPlans);
    ?>
    <details class="card mt-3">
        <summary class="d-flex align-items-center gap-2 flex-wrap">
            <h5 class="card-title mb-0">Vue d'ensemble : santé des plans (<?= e(season_filter_label($seasonFilter)) ?>)</h5>
            <span class="badge badge-solid badge-danger"><?= $errPlanCount ?> avec erreurs</span>
            <span class="badge badge-solid badge-warning"><?= $warnPlanCount ?> avec avertissements seuls</span>
            <span class="badge badge-solid badge-success"><?= $okCount ?> sans problème</span>
            <small class="text-muted">sur <?= $totalPlans ?> plans</small>
        </summary>
        <div class="card-body">
            <p class="text-muted small mb-3">Tous les problèmes détectés sur les plans affichés (filtre de saison ci-dessus), sans avoir à les ouvrir un par un. Cliquez sur « Ouvrir » pour aller au plan concerné.</p>

            <?php if (empty($plansWithIssues)): ?>
                <div class="alert alert-success mb-0"><i class="fas fa-check-circle"></i> Aucun problème détecté sur les <?= $totalPlans ?> plans.</div>
            <?php else: ?>
                <?php foreach ($plansWithIssues as $x):
                    $p = $x['plan'];
                    $variant = $x['errCount'] > 0 ? 'danger' : 'warning';
                    $season  = ($p->season ?? null) === null ? 'toutes' : 'S' . $p->season;
                ?>
                    <details class="alert alert-<?= $variant ?> mb-2">
                        <summary class="d-flex align-items-center gap-2 flex-wrap">
                            <span><?= e($p->name) ?> <small class="text-muted">(<?= e($p->id) ?>) · <?= $season ?></small></span>
                            <?php if ($x['errCount'] > 0): ?>
                                <span class="badge badge-solid badge-danger"><?= $x['errCount'] ?> erreur<?= $x['errCount'] > 1 ? 's' : '' ?></span>
                            <?php endif; ?>
                            <?php if ($x['warnCount'] > 0): ?>
                                <span class="badge badge-solid badge-warning"><?= $x['warnCount'] ?> avert.</span>
                            <?php endif; ?>
                            <form method="post" class="ml-auto m-0">
                                <input type="hidden" name="selected_plan" value="<?= e($p->id) ?>">
                                <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-right"></i> Ouvrir</button>
                            </form>
                        </summary>
                        <div class="p-2">
                            <?php
                            if ($x['v'] === null) {
                                render_validation_group([$x['emptyMsg']], 'danger', 'fa-times-circle', 'Erreur', true);
                            } else {
                                render_validation_report($x['v'], false);
                            }
                            ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </details>

    <?php endif; ?>

    <div class="card mt-3">
        <div class="card-body">
            <h5 class="card-title">Nettoyer les anciennes cartes</h5>
            <form method="post" class="d-flex align-items-center gap-3">
                <?= $csrf->renderTokenField() ?>
                <button type="submit" name="cleanup_local" class="btn btn-warning btn-sm">
                    <i class="fas fa-broom"></i> Nettoyer
                </button>
                <small class="text-muted">Supprime les anciennes versions des fichiers PNG générés, en conservant uniquement la version la plus récente pour chaque couche.</small>
            </form>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-body">
            <h5 class="card-title">Détecter les bornes manquantes</h5>
            <form method="post" class="d-flex align-items-center gap-3">
                <?= $csrf->renderTokenField() ?>
                <button type="submit" name="autodetect_bounds" class="btn btn-primary btn-sm">
                    <i class="fas fa-expand"></i> Détecter
                </button>
                <small class="text-muted">Pose les bornes visibles sur l'étendue réelle des cases, pour chaque niveau qui a une carte mais aucune borne.</small>
            </form>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-body">
            <h5 class="card-title">Générer une carte locale</h5>
            
            <form method="post" id="planForm">
                <?= $csrf->renderTokenField() ?>
                <div class="form-group">
                    <label for="planSelect">Choisir un plan :</label>
                    <?php
                    $planChoices = [];
                    foreach ($filteredPlans as $plan) {
                        $planChoices[$plan->id] = $plan->name . ' (' . $plan->id . ')';
                    }
                    echo formSelect('selected_plan', $planChoices,
                        $selectedPlan !== '' ? $selectedPlan : null,
                        '-- Sélectionner un plan (' . season_filter_label($seasonFilter) . ') --',
                        'class="form-control" id="planSelect" data-autosubmit');
                    ?>
                    <?php if (empty($filteredPlans)): ?>
                        <small class="text-muted">Aucun plan ne correspond au filtre « <?= e(season_filter_label($seasonFilter)) ?> » — élargir le filtre ci-dessus.</small>
                    <?php endif; ?>
                    <small class="text-muted d-block mt-1">Plan manquant ? <a href="/admin/plans.php?action=new">+ Créer un plan</a> (vierge ou par clonage).</small>
                </div>
            </form>
            
            <?php if ($selectedPlan): ?>
                <div class="mt-4 p-3 border rounded">
                    <?php
                        $selectedPlanData = array_filter($allPlans, fn($p) => $p->id === $selectedPlan);
                        $plan = reset($selectedPlanData);
                        $seasonBadge = ' ' . season_badge($plan->season ?? null);
                    ?>
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                        <div>
                            <strong>Plan sélectionné : <?= e($plan->name) ?> (<?= e($plan->id) ?>)<?= $seasonBadge ?></strong>
                            <?php if ($plan->hasZLevels ?? false): ?>
                                <small class="text-muted ms-2"><?= count($plan->fullData->z_levels ?? []) ?> niveau(x) Z</small>
                            <?php endif; ?>
                        </div>
                        <a href="/admin/plans.php?action=edit&plan=<?= urlencode($plan->id) ?>" class="btn btn-secondary btn-sm">
                            <i class="fas fa-edit"></i> Configurer le plan
                        </a>
                    </div>

                    <?php
                    // Validation de la config du plan
                    $rawPlanData = plans()->read($plan->id);
                    if ($rawPlanData === null || $rawPlanData === false) {
                        // No plans row: said explicitly rather than skipping the validation block
                        echo '<div class="alert alert-danger mt-3 py-1 my-1"><i class="fas fa-times-circle"></i> Config du plan absente, aucune récolte possible sur ce plan.</div>';
                    } else {
                        $validation = PlanJsonValidator::validate($rawPlanData, $plan->id, $database);

                        // Rendu groupé en accordéons repliables (un bloc par sévérité)
                        // plutôt qu'une alerte par ligne : la liste des « OK » peut compter
                        // des dizaines d'entrées. <details>/<summary> natifs (aucun JS
                        // Bootstrap n'est chargé côté admin). Erreurs/avertissements ouverts,
                        // OK replié. Voir render_validation_report() en tête de fichier.
                        if (count($validation['errors']) + count($validation['warnings']) + count($validation['ok']) > 0) {
                            echo '<div class="mt-3">';
                            render_validation_report($validation, true);
                            echo '</div>';
                        }
                    }
                    ?>

                    <?php if ($plan->hasZLevels ?? false): ?>
                        
                        <form method="post" class="mt-3">
                            <?= $csrf->renderTokenField() ?>
                            <input type="hidden" name="selected_plan" value="<?= e($selectedPlan) ?>">
                            <div class="form-group">
                                <label for="zLevelSelect">Sélectionner un niveau Z :</label>
                                <?php
                                $zChoices = [];
                                foreach ($plan->fullData->z_levels as $z => $levelData) {
                                    $zChoices[(string) $z] = $levelData->name;
                                }
                                echo formSelect('selected_z_level', $zChoices,
                                    ($selectedZLevel !== null && $selectedZLevel !== '') ? (string) $selectedZLevel : null,
                                    '-- Tous les niveaux --',
                                    'class="form-control" id="zLevelSelect" data-autosubmit');
                                ?>
                            </div>
                            <?php if ($selectedZLevel !== null && $selectedZLevel !== ''):
                                $rawZ = plans()->read($selectedPlan);
                                $zEntry = null;
                                foreach (($rawZ->z_levels ?? []) as $zl) {
                                    if ($zl->z == $selectedZLevel) { $zEntry = $zl; break; }
                                }
                                $mapUnavailable = !empty($zEntry->MapUnavailable);
                            ?>
                                <div class="mt-3 d-flex justify-content-end align-items-center gap-2">
                                    <?php if ($mapUnavailable): ?>
                                        <small class="text-muted fst-italic">Niveau réglé « Sans carte »<code class="tech-name">MapUnavailable</code></small>
                                    <?php endif; ?>
                                    <button type="submit" name="generate_local" class="btn btn-primary btn-sm" <?= $mapUnavailable ? 'disabled' : '' ?>>
                                        <i class="fas fa-sync"></i> Regénérer
                                    </button>
                                </div>
                            <?php endif; ?>
                        </form>

                        <?php if ($selectedZLevel !== null && $selectedZLevel !== ''):
                            $existingService = new ViewService($database, 0, 0, $selectedZLevel, 0, $selectedPlan);
                            $existingLayers = $existingService->getLocalMap();
                            $layerOrder = ['tiles', 'elements', 'foregrounds', 'resources', 'routes', 'buildings'];

                            // Chercher le composite existant
                            $compositeFiles = glob($_SERVER['DOCUMENT_ROOT'] . "/img/maps/local/local_{$selectedPlan}_{$selectedZLevel}_composite_*.png");
                            $compositePath = null;
                            $compositeTimestamp = null;
                            if (!empty($compositeFiles)) {
                                usort($compositeFiles, fn($a, $b) => filemtime($b) - filemtime($a));
                                $compositePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $compositeFiles[0]);
                                preg_match('/_(\d{8}-\d{6})\.png$/', $compositePath, $m);
                                $compositeTimestamp = $m[1] ?? null;
                            }

                            if (!empty($existingLayers) || $compositePath):
                        ?>
                            <div class="mt-4">
                                <h6 class="text-muted">Cartes existantes :</h6>
                                <div class="row">
                                    <?php if ($compositePath): ?>
                                        <div class="col-md-4 mb-3">
                                            <div class="card border-primary">
                                                <img src="<?= $compositePath ?>" class="card-img-top">
                                                <div class="card-body p-2">
                                                    <p class="card-title mb-0"><strong>Composite final</strong></p>
                                                    <?php if ($compositeTimestamp): ?>
                                                        <small class="text-muted">Généré le : <?= date('d/m/Y H:i', strtotime($compositeTimestamp)) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?php foreach ($layerOrder as $layer):
                                        if (!isset($existingLayers[$layer])) continue;
                                        $data = $existingLayers[$layer];
                                    ?>
                                        <div class="col-md-4 mb-3">
                                            <div class="card">
                                                <img src="<?= $data['imagePath'] ?>" class="card-img-top">
                                                <div class="card-body p-2">
                                                    <p class="card-title text-capitalize mb-0"><strong><?= $layer ?></strong></p>
                                                    <small class="text-muted">Généré le : <?= date('d/m/Y H:i', strtotime($data['timestamp'])) ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($cleanupReport)): ?>
                <div class="alert alert-success mt-3">
                    <strong>Résultats du nettoyage :</strong><br><br>
                    <strong>Fichiers conservés :</strong><br><?=implode("<br>", $cleanupReport['kept'])?><br><br>
                    <strong>Fichiers supprimés :</strong><br><?=(count($cleanupReport['deleted']) ? implode("<br>", $cleanupReport['deleted']) : "Aucun")?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['generated_layers'])): ?>
                <?php
                // Create composite image
                $compositePath = null;
                $layerOrder = ['tiles', 'routes', 'elements', 'resources', 'foregrounds', 'buildings'];
                $validLayers = array_intersect($layerOrder, array_keys($_SESSION['generated_layers']));
                
                if (!empty($validLayers)) {
                    $tilesLayer = $_SESSION['generated_layers']['tiles'];
                    $timestamp = $tilesLayer['timestamp'];
                    $tilesImg = imagecreatefrompng($_SERVER['DOCUMENT_ROOT'].$tilesLayer['imagePath']);
                    $width = imagesx($tilesImg);
                    $height = imagesy($tilesImg);
                    
                    // Create blank composite with tiles dimensions
                    $composite = imagecreatetruecolor($width, $height);
                    imagecopy($composite, $tilesImg, 0, 0, 0, 0, $width, $height);
                    imagedestroy($tilesImg);
                    
                    foreach ($validLayers as $layer) {
                        if ($layer !== 'tiles') {
                            $layerImg = imagecreatefrompng($_SERVER['DOCUMENT_ROOT'].$_SESSION['generated_layers'][$layer]['imagePath']);
                            imagecopyresampled($composite, $layerImg, 0, 0, 0, 0, $width, $height, imagesx($layerImg), imagesy($layerImg));
                            imagedestroy($layerImg);
                        }
                    }
                    
                    $compositeFilename = "local_{$selectedPlan}_{$selectedZLevel}_composite_{$timestamp}.png";
                    $compositePath = "/img/maps/local/{$compositeFilename}";
                    imagepng($composite, $_SERVER['DOCUMENT_ROOT'].$compositePath);
                    imagedestroy($composite);
                    
                    // Add composite to results
                    $_SESSION['generated_layers']['composite'] = [
                        'imagePath' => $compositePath,
                        'timestamp' => $timestamp
                    ];
                }
                ?>
                
                <div class="mt-4">
                    <h4>Couches générées :</h4>
                    <div class="row">
                        <!-- Composite Final Image -->
                        <?php if ($compositePath): ?>
                        <div class="col-md-4 mb-3">
                            <div class="card">
                                <img src="<?=$compositePath?>" class="card-img-top">
                                <div class="card-body">
                                    <h5 class="card-title">Composite final</h5>
                                    <p class="text-muted small">
                                        Généré le : <?=date('Y-m-d H:i:s', strtotime($timestamp))?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php foreach ($_SESSION['generated_layers'] as $layer => $data): ?>
                            <?php if ($layer !== 'composite'): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card">
                                    <img src="<?=$data['imagePath']?>" class="card-img-top">
                                    <div class="card-body">
                                        <h5 class="card-title text-capitalize"><?=$layer?></h5>
                                        <p class="text-muted small">
                                            Généré le : <?=date('Y-m-d H:i:s', strtotime($data['timestamp']))?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php unset($_SESSION['generated_layers']); ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($selectedPlan): ?>
    <div class="card mt-3">
        <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="card-title mb-1">Transitions de terrain (pinceau Terrain de Tiled)</h5>
                <p class="text-muted small mb-0">
                    Classification des tuiles du plan (terrain / hors terrain), audit des frontières
                    et génération des fondus manquants : sur la page dédiée.
                </p>
            </div>
            <a class="btn btn-primary btn-sm"
               href="/admin/terrain-transitions.php?selected_plan=<?= e(urlencode($selectedPlan)) ?>">
                <i class="fas fa-fill-drip"></i> Ouvrir pour <?= e($selectedPlan) ?>
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
echo admin_layout('Cartes locales', $content, ['intro' => 'Génère l\'image d\'un niveau de plan à partir de ses cases,'
        . ' et signale les niveaux déclarés dans la configuration sans cases (ou l\'inverse).'
        . ' Un niveau sans carte voulue se règle par « Sans carte » dans l\'édition du plan.']);
