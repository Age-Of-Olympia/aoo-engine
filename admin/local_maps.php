<?php
// admin/local_maps.php
require_once __DIR__ . '/layout.php';
use Classes\Db;
use App\Service\ViewService;

// Clear any world map layers when loading local maps
if (isset($_SESSION['generated_layers']) && strpos(json_encode($_SESSION['generated_layers']), 'world_') !== false) {
    unset($_SESSION['generated_layers']);
}

$database = new Db();
$viewService = new ViewService($database, 0, 0, 0, 0, $selectedPlan ?? 'olympia');

// Get all available plans (local maps)
$allPlans = $viewService->getAllPlans();
$selectedPlan = $_POST['selected_plan'] ?? null;
$selectedZLevel = $_POST['selected_z_level'] ?? null;

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
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Local map layers generated successfully".(!empty($selectedZLevel) ? " for Z-level $selectedZLevel" : '')];
            $_SESSION['generated_layers'] = $results;
        }
    } catch (Exception $e) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Error generating local map: ' . $e->getMessage()];
    }
}

ob_start();
?>

<div class="container">
    <h1>Local Map Management</h1>
    
    <div class="card mt-4">
        <div class="card-header">
            <form method="post">
                <button type="submit" name="cleanup_local" class="btn btn-warning">
                    <i class="fas fa-broom"></i> Cleanup Old Local Maps
                </button>
            </form>
        </div>
        <div class="card-body">
            <h2>Select Local Map</h2>
            
            <form method="post">
                <div class="form-group">
                    <label for="planSelect">Choose Plan:</label>
                    <select class="form-control" id="planSelect" name="selected_plan" required>
                        <option value="">-- Select a Plan --</option>
                        <?php foreach ($allPlans as $plan): ?>
                            <option value="<?=$plan->id?>" <?=($selectedPlan === $plan->id) ? 'selected' : ''?>>
                                <?=$plan->name?> (<?=$plan->id?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Confirm Selection
                    </button>
                </div>
            </form>
            
            <?php if ($selectedPlan): ?>
                <div class="alert alert-success mt-4">
                    <h4>Selected Plan:</h4>
                    <p>
                        <?php 
                            $selectedPlanData = array_filter($allPlans, fn($p) => $p->id === $selectedPlan);
                            $plan = reset($selectedPlanData);
                            echo "<strong>{$plan->name}</strong> (ID: {$plan->id})";
                        ?>
                    </p>
                    <a href="/tools.php?edit&dir=private&subDir=plans&finalDir=<?= urlencode($plan->id) ?>" class="btn btn-secondary btn-sm mb-3" target="_blank">
                        <i class="fas fa-external-link-alt"></i> View Plan Content
                    </a>
                    <?php if ($plan->hasZLevels ?? false): ?>
                        <?php 
                            $zLevels = count($plan->fullData->z_levels ?? []);
                            echo "<p>This plan has {$zLevels} Z-level(s).</p>";
                        ?>
                        
                        <form method="post" class="mt-3">
                            <input type="hidden" name="selected_plan" value="<?=$selectedPlan?>">
                            <div class="form-group">
                                <label for="zLevelSelect">Select Z-level:</label>
                                <select class="form-control" id="zLevelSelect" name="selected_z_level" required>
                                    <option value="" <?=empty($selectedZLevel) ? 'selected' : ''?>>-- All Levels --</option>
                                    <?php foreach ($plan->fullData->z_levels as $z => $levelData): ?>
                                        <option value="<?=$z?>" <?=($selectedZLevel == $z) ? 'selected' : ''?>>
                                            <?=$levelData->name?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mt-3">
                                <button type="submit" name="generate_local" class="btn btn-primary">
                                    <i class="fas fa-sync"></i> Generate Selected Level
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['cleanup_report'])): ?>
                <div class="alert alert-success mt-3">
                    <strong>Cleanup Results:</strong><br><br>
                    <strong>Kept files:</strong><br><?=implode("<br>", $_SESSION['cleanup_report']['kept'])?><br><br>
                    <strong>Deleted files:</strong><br><?=(count($_SESSION['cleanup_report']['deleted']) ? implode("<br>", $_SESSION['cleanup_report']['deleted']) : "None")?>
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
                    <h4>Generated Layers:</h4>
                    <div class="row">
                        <!-- Composite Final Image -->
                        <?php if ($compositePath): ?>
                        <div class="col-md-4 mb-3">
                            <div class="card">
                                <img src="<?=$compositePath?>" class="card-img-top">
                                <div class="card-body">
                                    <h5 class="card-title">Final Composite</h5>
                                    <p class="text-muted small">
                                        Generated: <?=date('Y-m-d H:i:s', strtotime($timestamp))?>
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
                                            Generated: <?=date('Y-m-d H:i:s', strtotime($data['timestamp']))?>
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
echo admin_layout('Local Map Management', $content);
?>
