<?php
// admin/local_maps.php
require_once __DIR__ . '/layout.php';
use Classes\Db;
use App\Service\ViewService;
use App\Service\PlanJsonValidator;

// Clear any world map layers when loading local maps
if (isset($_SESSION['generated_layers']) && strpos(json_encode($_SESSION['generated_layers']), 'world_') !== false) {
    unset($_SESSION['generated_layers']);
}

$database = new Db();
$viewService = new ViewService($database, 0, 0, 0, 0, $selectedPlan ?? 'olympia');

// Get all available plans (local maps)
$allPlans = $viewService->getAllPlans('all');
$selectedPlan = $_POST['selected_plan'] ?? null;
$selectedZLevel = $_POST['selected_z_level'] ?? null;

usort($allPlans, function($a, $b) {
    $nameA = str_replace('_s2', '', $a->id);
    $nameB = str_replace('_s2', '', $b->id);

    if ($nameA === $nameB) {
        return $b->isS2 <=> $a->isS2;
    }

    return strcasecmp($nameA, $nameB);
});

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
    
    $_SESSION['cleanup_report'] = [
        'kept' => $kept,
        'deleted' => $deleted
    ];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_local'])) {
    try {
        $layers = ['tiles', 'elements', 'foregrounds', 'walls', 'routes'];

        $viewService = new ViewService($database, 0, 0, $selectedZLevel, 0, $selectedPlan);
        $results = $viewService->generateLocalMap($layers);
        
        if (!empty($results)) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Couches générées avec succès".(!empty($selectedZLevel) ? " pour le niveau Z $selectedZLevel" : '')];
            $_SESSION['generated_layers'] = $results;
        }
    } catch (Exception $e) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Erreur lors de la génération : ' . $e->getMessage()];
    }
}

ob_start();
?>

<div class="container">
    <h3>Gestion des cartes locales</h3>

    <div class="alert alert-info" style="font-size: 13px; line-height: 1.5;">
        <strong>Qu'est-ce qu'une carte locale ?</strong>
        Elle est constituée de deux éléments qui doivent être cohérents :
        <ul class="mb-1 mt-1">
            <li><strong>Un fichier JSON</strong> (<code style="display:inline;white-space:nowrap">private/plans/&lt;id&gt;.json</code>) — nom, niveaux Z, bornes visibles, biomes…</li>
            <li><strong>Des coordonnées en base</strong> — chaque case dans la table <code style="display:inline;white-space:nowrap">coords</code> (tiles, éléments, murs…)</li>
        </ul>
        Un niveau Z peut exister en base sans être déclaré dans le JSON (et inversement) — la validation ci-dessous détecte ces incohérences.
        Si un niveau n'a volontairement pas de carte, déclarez-le avec <code style="display:inline;white-space:nowrap">"MapUnavailable": true</code> dans le JSON.
    </div>

    <div class="card mt-3">
        <div class="card-body">
            <h5 class="card-title">Nettoyer les anciennes cartes</h5>
            <form method="post" class="d-flex align-items-center gap-3">
                <button type="submit" name="cleanup_local" class="btn btn-warning btn-sm">
                    <i class="fas fa-broom"></i> Nettoyer
                </button>
                <small class="text-muted">Supprime les anciennes versions des fichiers PNG générés, en conservant uniquement la version la plus récente pour chaque couche.</small>
            </form>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-body">
            <h5 class="card-title">Générer une carte locale</h5>
            
            <form method="post" id="planForm">
                <div class="form-group">
                    <label for="planSelect">Choisir un plan :</label>
                    <select class="form-control" id="planSelect" name="selected_plan" onchange="this.form.submit()">
                        <option value="">-- Sélectionner un plan --</option>
                        <?php foreach ($allPlans as $plan):
                            $seasonBadge = $plan->isS2 ? ' <span class="badge bg-success">S2</span>' : ' <span class="badge bg-secondary">S1</span>';
                        ?>
                            <option value="<?= $plan->id ?>" <?= ($selectedPlan === $plan->id) ? 'selected' : '' ?>>
                                <?= $plan->name ?> (<?= $plan->id ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
            
            <?php if ($selectedPlan): ?>
                <div class="alert alert-success mt-4">
                    <?php
                        $selectedPlanData = array_filter($allPlans, fn($p) => $p->id === $selectedPlan);
                        $plan = reset($selectedPlanData);
                        $seasonBadge = $plan->isS2 ? ' <span class="badge bg-success">S2</span>' : ' <span class="badge bg-secondary">S1</span>';
                    ?>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div>
                            <strong>Plan sélectionné : <?= $plan->name ?> (<?= $plan->id ?>)<?= $seasonBadge ?></strong>
                            <?php if ($plan->hasZLevels ?? false): ?>
                                <small class="text-muted ms-2">— <?= count($plan->fullData->z_levels ?? []) ?> niveau(x) Z</small>
                            <?php endif; ?>
                        </div>
                        <a href="/tools.php?edit&dir=private&subDir=plans&finalDir=<?= urlencode($plan->id) ?>" class="btn btn-secondary btn-sm" target="_blank">
                            <i class="fas fa-edit"></i> Voir/éditer le JSON du plan
                        </a>
                    </div>

                    <?php
                    // Validation du JSON du plan
                    $rawPlanData = json()->decode('plans', $plan->id);
                    if ($rawPlanData) {
                        $validation = PlanJsonValidator::validate($rawPlanData, $plan->id, $database);
                        if (!empty($validation['errors']) || !empty($validation['warnings']) || !empty($validation['ok'])) {
                            echo '<div class="mt-3">';

                            foreach ($validation['errors'] as $err) {
                                echo '<div class="alert alert-danger py-1 my-1"><i class="fas fa-times-circle"></i> ' . $err . '</div>';
                            }
                            foreach ($validation['warnings'] as $warn) {
                                echo '<div class="alert alert-warning py-1 my-1"><i class="fas fa-exclamation-triangle"></i> ' . $warn . '</div>';
                            }
                            foreach ($validation['ok'] as $msg) {
                                echo '<div class="alert alert-success py-1 my-1"><i class="fas fa-check-circle"></i> ' . $msg . '</div>';
                            }

                            echo '</div>';
                        }
                    }
                    ?>

                    <?php if ($plan->hasZLevels ?? false): ?>
                        
                        <form method="post" class="mt-3">
                            <input type="hidden" name="selected_plan" value="<?=$selectedPlan?>">
                            <div class="form-group">
                                <label for="zLevelSelect">Sélectionner un niveau Z :</label>
                                <select class="form-control" id="zLevelSelect" name="selected_z_level" onchange="this.form.submit()">
                                    <option value="" <?=empty($selectedZLevel) ? 'selected' : ''?>>-- Tous les niveaux --</option>
                                    <?php foreach ($plan->fullData->z_levels as $z => $levelData): ?>
                                        <option value="<?=$z?>" <?=($selectedZLevel == $z) ? 'selected' : ''?>>
                                            <?=$levelData->name?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if ($selectedZLevel !== null && $selectedZLevel !== ''): ?>
                                <div class="mt-3 d-flex justify-content-end">
                                    <button type="submit" name="generate_local" class="btn btn-primary btn-sm">
                                        <i class="fas fa-sync"></i> Regénérer
                                    </button>
                                </div>
                            <?php endif; ?>
                        </form>

                        <?php if ($selectedZLevel !== null && $selectedZLevel !== ''):
                            $existingService = new ViewService($database, 0, 0, $selectedZLevel, 0, $selectedPlan);
                            $existingLayers = $existingService->getLocalMap();
                            $layerOrder = ['tiles', 'elements', 'foregrounds', 'walls', 'routes'];

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
            
            <?php if (isset($_SESSION['cleanup_report'])): ?>
                <div class="alert alert-success mt-3">
                    <strong>Résultats du nettoyage :</strong><br><br>
                    <strong>Fichiers conservés :</strong><br><?=implode("<br>", $_SESSION['cleanup_report']['kept'])?><br><br>
                    <strong>Fichiers supprimés :</strong><br><?=(count($_SESSION['cleanup_report']['deleted']) ? implode("<br>", $_SESSION['cleanup_report']['deleted']) : "Aucun")?>
                </div>
                <?php unset($_SESSION['cleanup_report']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['generated_layers'])): ?>
                <?php
                // Create composite image
                $compositePath = null;
                $layerOrder = ['tiles', 'routes', 'elements', 'walls', 'foregrounds'];
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
</div>

<?php
$content = ob_get_clean();
echo admin_layout('Gestion des cartes locales', $content);
?>
