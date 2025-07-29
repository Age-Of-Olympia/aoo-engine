<?php
// admin/screenshots.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

use Classes\Db;
use Classes\Player;
use Classes\View;
use App\Service\ViewService;
use App\Service\ScreenshotService;

$database = new Db();
$screenshotService = new ScreenshotService();

$viewService = new ViewService($database, 0, 0, 0, 0, 'olympia');
$allPlans = $viewService->getAllPlans('all');

$selectedPlanId = $_POST['plan_id'] ?? '';
$selectedX = $_POST['x'] ?? '0';
$selectedY = $_POST['y'] ?? '0';
$selectedZ = $_POST['z'] ?? '0';
$selectedRange = $_POST['range'] ?? '5';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_screenshot'])) {
    $coords = [
        'x' => (int)$selectedX,
        'y' => (int)$selectedY,
        'z' => (int)$selectedZ,
        'plan' => $selectedPlanId
    ];
    $range = (int)$selectedRange;
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "screenshot_{$selectedPlanId}_{$selectedX}_{$selectedY}_{$selectedZ}_{$timestamp}";
    $result = $screenshotService->generateScreenshot($coords, $range, $filename);

    if ($result['success']) {
        $success = "Capture d'écran générée avec succès : " . basename($result['filepath']);
        $imageUrl = str_replace($_SERVER['DOCUMENT_ROOT'], '', $result['filepath']);
        $showPreview = true;
    } else {
        $error = "Erreur lors de la génération : " . $result['error'];
    }
}

ob_start();
?>

<div class="container">
    <h1>Générateur de captures d'écran</h1>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <form method="post">
                <div class="form-group">
                    <label for="plan_id">Plan :</label>
                    <select class="form-control" id="plan_id" name="plan_id" required>
                    <?php foreach ($allPlans as $plan): 
                        $isSelected = ($selectedPlanId == $plan->id) ? 'selected' : '';
                    ?>
                        <option value="<?= htmlspecialchars($plan->id) ?>" <?= $isSelected ?>>
                            <?= htmlspecialchars($plan->name) ?> (<?= htmlspecialchars($plan->id) ?>)
                        </option>
                    <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="x">X :</label>
                            <input type="number" class="form-control" id="x" name="x" 
                                value="<?= htmlspecialchars($selectedX) ?>" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="y">Y :</label>
                            <input type="number" class="form-control" id="y" name="y" 
                                value="<?= htmlspecialchars($selectedY) ?>" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="z">Z :</label>
                            <input type="number" class="form-control" id="z" name="z" 
                                value="<?= htmlspecialchars($selectedZ) ?>" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="range">Portée :</label>
                            <input type="number" class="form-control" id="range" name="range" 
                                value="<?= htmlspecialchars($selectedRange) ?>" min="1" max="30" required>
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
    
    <?php if (isset($showPreview) && $showPreview): ?>
    <div class="card mt-4">
        <div class="card-header">
            <h3>Aperçu de la capture</h3>
        </div>
        <div class="card-body text-center">
            <div style="max-width: 100%; overflow: auto;">
                <img src="<?= $imageUrl ?>" alt="Capture d'écran" class="img-fluid">
            </div>
            <div class="mt-3">
                <a href="<?= $imageUrl ?>" class="btn btn-primary" download>
                    <i class="fas fa-download"></i> Télécharger l'image
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>

<?php
$content = ob_get_clean();
echo admin_layout('Générateur de captures', $content);
?>

<script>
async function limitConcurrency(items, limit, asyncFn) {
    const results = [];
    const executing = [];

    for (const item of items) {
        const p = Promise.resolve().then(() => asyncFn(item));
        results.push(p);

        if (limit <= items.length) {
            const e = p.then(() => executing.splice(executing.indexOf(e), 1));
            executing.push(e);
            if (executing.length >= limit) {
                await Promise.race(executing);
            }
        }
    }

    return Promise.all(results);
}

async function imageExists(url) {
    try {
        const response = await fetch(url, { method: 'HEAD' });
        return response.ok;
    } catch (error) {
        console.error('Erreur lors de la vérification de l\'image:', url, error);
        return false;
    }
}

document.getElementById('download-png').addEventListener('click', async () => {
    const svgElement = document.querySelector('svg');
    if (!svgElement) return;

    const imageElements = [...svgElement.querySelectorAll('image')];

    async function processImage(img) {
        const href = img.getAttribute('href') || img.getAttribute('xlink:href');
        console.log('Traitement de l\'image:', href);
        if (!href || href.startsWith('data:')) return;

        try {
            const res = await fetch(href);
            const blob = await res.blob();
            const reader = new FileReader();
            return new Promise((resolve) => {
                reader.onloadend = () => {
                    img.setAttribute('href', reader.result);
                    resolve();
                };
                reader.readAsDataURL(blob);
            });
        } catch (err) {
            console.error('Échec du chargement de l’image :', href, err);
        }
    }

    await limitConcurrency(imageElements, 5, processImage);
    console.log('Toutes les images sont converties en base64');

    const svgData = new XMLSerializer().serializeToString(svgElement);
    const svgBlob = new Blob([svgData], {type: 'image/svg+xml;charset=utf-8'});
    const url = URL.createObjectURL(svgBlob);
    const zValue = parseInt(document.getElementById('z').value) || 0;
  
    const img = new Image();
    img.onload = () => {
    const canvas = document.createElement('canvas');
    canvas.width = svgElement.viewBox.baseVal.width || svgElement.width.baseVal.value;
    canvas.height = svgElement.viewBox.baseVal.height || svgElement.height.baseVal.value;
    const svgBgMatch = svgElement.getAttribute('style')?.match(/background:\s*url\('([^']+)'\)/i);

    const bgUrl = svgBgMatch ? svgBgMatch[1] : null;
    console.log('URL de fond extraite du SVG :', bgUrl);
    const ctx = canvas.getContext('2d');
    
    if (zValue === 0) {
        const bgImg = new Image();
        bgImg.crossOrigin = "Anonymous";
        
        const drawFinalImage = () => {
            console.log('Dessin de l\'image finale...');
            ctx.drawImage(bgImg, 0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0);
            console.log('Dessin terminé, finalisation...');
            finalizeImage();
        };
        
        const finalizeImage = () => {
            URL.revokeObjectURL(url);
            const pngUrl = canvas.toDataURL('image/jpeg');
            const a = document.createElement('a');
            a.href = pngUrl;
            a.download = 'screenshot.jpg';
            a.click();
        };
        
        // Si on a une URL de fond, on l'utilise
        if (bgUrl) {
            console.log('Chargement de l\'image de fond personnalisée...');
            bgImg.onload = drawFinalImage;
            bgImg.onerror = () => {
                console.error('Erreur de chargement de l\'image de fond personnalisée, utilisation du fond par défaut');
                // En cas d'erreur, on utilise le fond par défaut
                bgImg.src = 'http://' + window.location.host + '/img/ui/bg/bg.jpeg';
            };
            bgImg.src = bgUrl;
        } else {
            // Sinon on utilise le fond par défaut
            console.log('Chargement de l\'image de fond par défaut...');
            bgImg.onload = drawFinalImage;
            bgImg.onerror = (e) => {
                console.error('Erreur de chargement de l\'image de fond par défaut:', e);
            };
            bgImg.src = 'http://' + window.location.host + '/img/ui/bg/bg.jpeg';
    }

    } else {
        ctx.drawImage(img, 0, 0);
        URL.revokeObjectURL(url);
        const pngUrl = canvas.toDataURL('image/jpeg');
        const a = document.createElement('a');
        a.href = pngUrl;
        a.download = 'screenshot.jpg';
        a.click();
    }
};
    img.src = url;
});
</script>
