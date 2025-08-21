<?php
// admin/maps.php
require_once __DIR__ . '/layout.php';

use App\Service\ViewService;
use Classes\Db;

$database = new Db();
$viewService = new ViewService($database, 0, 0, 0, 0, 'olympia');

// Clear any local map layers when loading world maps
if (isset($_SESSION['generated_layers']) && strpos(json_encode($_SESSION['generated_layers']), 'world_') !== false) {
    unset($_SESSION['generated_layers']);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['generate_global'])) {
        try {
            $layers = ['tiles', 'elements', 'coordinates', 'locations', 'routes'];
            $results = $viewService->generateGlobalMap($layers);
            
            if (!empty($results)) {
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'World map layers generated successfully'];
                $_SESSION['generated_layers'] = $results;
            } else {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to generate world map layers'];
            }
        } catch (Exception $e) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Error generating world map: ' . $e->getMessage()];
        }
    }
    
    if (isset($_POST['cleanup_maps'])) {
        $mapDir = $_SERVER['DOCUMENT_ROOT'].'/img/maps/world/';
        $files = glob($mapDir.'world_*.png');
        
        // Group by layer type
        $layerFiles = [];
        $report = ['deleted' => [], 'kept' => []];
        
        foreach ($files as $file) {
            if (preg_match('/world_(\w+)_(\d{8}-\d{6})\.png$/', basename($file), $matches)) {
                $layerFiles[$matches[1]][$matches[2]] = $file;
            }
        }
        
        // Process each layer
        foreach ($layerFiles as $layer => $versions) {
            if (count($versions) > 1) {
                ksort($versions);
                $newest = array_key_last($versions);
                
                foreach ($versions as $timestamp => $file) {
                    if ($timestamp !== $newest) {
                        unlink($file);
                        $report['deleted'][] = basename($file);
                    } else {
                        $report['kept'][] = basename($file);
                    }
                }
            } else {
                $report['kept'] = array_merge($report['kept'], array_map('basename', $versions));
            }
        }
        
        // Format detailed message
        $message = "<strong>Cleanup Report:</strong><br>";
        $message .= "<strong>Deleted:</strong> " . (count($report['deleted']) ? implode(', ', $report['deleted']) : 'none') . "<br>";
        $message .= "<strong>Kept:</strong> " . implode(', ', $report['kept']);
        
        $_SESSION['flash'] = ['type' => 'success', 'message' => $message, 'html' => true];
    }
    
    if (isset($_POST['show_latest'])) {
        $latestFiles = [];
        $worldMapsDir = $_SERVER['DOCUMENT_ROOT'].'/img/maps/world/';
        $files = glob($worldMapsDir.'world_*.png');
        
        // Group by layer and get most recent
        foreach ($files as $file) {
            if (preg_match('/world_([^_]+)_(\d{8}-\d{6})\.png/', basename($file), $matches)) {
                $layer = $matches[1];
                $timestamp = $matches[2];
                if (!isset($latestFiles[$layer]) || $timestamp > $latestFiles[$layer]['timestamp']) {
                    $latestFiles[$layer] = [
                        'imagePath' => "/img/maps/world/" . basename($file),
                        'timestamp' => $timestamp
                    ];
                }
            }
        }
        
        if (!empty($latestFiles)) {
            // Order layers consistently
            $orderedLayers = [];
            $layerOrder = ['tiles', 'elements', 'coordinates', 'locations', 'routes'];
            foreach ($layerOrder as $layer) {
                if (isset($latestFiles[$layer])) {
                    $orderedLayers[$layer] = $latestFiles[$layer];
                }
            }
            $_SESSION['generated_layers'] = $orderedLayers;
        }
    }
}

ob_start();
?>

<div class="container">
    <h1>Map Management</h1>
    
    <?php if (isset($_SESSION['flash'])): ?>
        <div class="alert alert-<?=$_SESSION['flash']['type']?>" role="alert">
            <?= $_SESSION['flash']['html'] ?? false ? $_SESSION['flash']['message'] : htmlspecialchars($_SESSION['flash']['message']) ?>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>
    
    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2>World Map Layers</h2>
            <form method="post" class="d-inline">
                <button type="submit" name="cleanup_maps" class="btn btn-warning">
                    <i class="fas fa-broom"></i> Cleanup Old Maps
                </button>
            </form>
        </div>
        <div class="card-body">
            <form method="post" class="mb-3">
                <button type="submit" name="show_latest" class="btn btn-info">
                    <i class="fas fa-eye"></i> Show Latest World Map
                </button>
            </form>
            
            <form method="post">
                <button type="submit" name="generate_global" class="btn btn-primary">
                    <i class="fas fa-sync"></i> Generate All World Map Layers
                </button>
            </form>
            
            <?php if (isset($_SESSION['generated_layers'])): ?>
                <div class="mt-4">
                    <h4>Generated Layers:</h4>
                    <div class="row">
                        <?php foreach ($_SESSION['generated_layers'] as $layer => $data): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card">
                                    <img src="<?php echo $data['imagePath']; ?>" class="card-img-top">
                                    <div class="card-body">
                                        <h5 class="card-title text-capitalize"><?=$layer?></h5>
                                        <p class="text-muted small">
                                            Generated: <?=date('Y-m-d H:i:s', strtotime($data['timestamp']))?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
echo admin_layout('Map Management', $content);
?>
