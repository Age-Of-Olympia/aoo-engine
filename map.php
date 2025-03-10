<?php

require_once('config.php');

$ui = new Ui('Carte du Monde');

$player = new Player($_SESSION['playerId']);

$player->get_coords();

$planJson = json()->decode('plans', $player->coords->plan);
$planJson->id = $player->coords->plan;
$planJson->fromCoords = $player->coords;

ob_start();

// Affiche la carte S2
if (isset($_GET['s2']) && !isset($_GET['local'])) {
    echo '<div>
        <a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a>
        <a href="map.php"><button>Monde</button></a>
        <a href="map.php?local"><button>' . $planJson->name . '</button></a>
        <a href="map.php?local&s2"><button>' . $planJson->name . ' s2</button></a>
        <a href="map.php?s2&regenerate=1"><button>Régénérer la carte</button></a>
    </div><br />';

    // Formulaire de sélection des couches
    echo '<div class="layer-controls" style="margin-bottom: 15px;">
        <form method="GET" action="map.php" style="display: inline-block;">
            <input type="hidden" name="s2" value="1">
            <label><input type="checkbox" name="layers[]" value="tiles" ' . 
            ((!isset($_GET['layers']) || in_array('tiles', $_GET['layers'] ?? [])) ? 'checked' : '') . 
            '> Terrain</label>
            <label><input type="checkbox" name="layers[]" value="elements" ' . 
            ((!isset($_GET['layers']) || in_array('elements', $_GET['layers'] ?? [])) ? 'checked' : '') . 
            '> Éléments</label>
            <label><input type="checkbox" name="layers[]" value="coordinates" ' . 
            (in_array('coordinates', $_GET['layers'] ?? []) ? 'checked' : '') . 
            '> Coordonnées</label>
            <label><input type="checkbox" name="layers[]" value="lieux" ' . 
            (in_array('lieux', $_GET['layers'] ?? []) ? 'checked' : '') . 
            '> Lieux</label>
            <label><input type="checkbox" name="layers[]" value="routes" ' . 
            (in_array('routes', $_GET['layers'] ?? []) ? 'checked' : '') . 
            '> Routes</label>
            <label><input type="checkbox" name="layers[]" value="players" ' . 
            (in_array('players', $_GET['layers'] ?? []) ? 'checked' : '') . 
            '> Tous les joueurs</label>
            <label><input type="checkbox" name="layers[]" value="player" ' . 
            (in_array('player', $_GET['layers'] ?? []) ? 'checked' : '') . 
            '> Ma position</label>
            <button type="submit">Actualiser la carte</button>
        </form>
    </div>';

    // Récupère les couches sélectionnées ou utilise les valeurs par défaut
    $selectedLayers = $_GET['layers'] ?? ['tiles', 'elements'];

    // Initialise la connexion à la base de données
    $database = new Db();

    // Récupère les coordonnées du joueur
    $player = new Player($_SESSION['playerId']);
    $player->get_coords();

    // Debug logging
    error_log("Debug - Player coordinates: x={$player->coords->x}, y={$player->coords->y}, z={$player->coords->z}");
    error_log("Debug - Plan ID: " . $planJson->id);
    
    // Crée le service de vue et génère la carte
    $viewService = new \App\Service\ViewService($database, $player->coords->x, $player->coords->y,$player->coords->z, $planJson->id);
    
    error_log("Debug - Selected Layers: " . print_r($selectedLayers, true));
    $mapResult = $viewService->generateGlobalMap($selectedLayers);
    error_log("Debug - Map Result: " . print_r($mapResult, true));

    if (isset($mapResult['imagePath']) && file_exists($mapResult['imagePath'])) {
        // Convertit l'image en base64 pour l'affichage inline
        $imageData = base64_encode(file_get_contents($mapResult['imagePath']));

        // Crée le HTML pour la carte cliquable
        echo '<map name="terrain-map">';
        foreach ($mapResult['imageMap'] as $area) {
            $coords = implode(',', $area['coords']);
            $title = ucfirst(str_replace('_', ' ', $area['name']));
            echo sprintf(
                '<area shape="rect" coords="%s" title="%s" alt="%s" onmouseover="showTooltip(%s)">',
                htmlspecialchars($coords),
                htmlspecialchars($title),
                htmlspecialchars($title),
                htmlspecialchars(json_encode($area, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT))
            );
        }
        echo '</map>';

        // Affiche l'image avec la carte cliquable
        echo sprintf(
            '<img class="box-shadow" src="data:image/png;base64,%s" alt="Monde S2" usemap="#terrain-map" style="cursor:help">',
            $imageData
        );
    } else {
        echo '<p>La carte n\'est pas encore générée. Veuillez cliquer sur "Régénérer la carte".</p>';
    }

    echo Str::minify(ob_get_clean());
    exit();
}

// Si le joueur est hors de la carte
if(!$planJson){
    echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a></div><br />';

    $url = 'img/ui/illustrations/'. $player->coords->plan .'.webp';
    if(!file_exists($url)){
        $url = 'img/ui/illustrations/gaia.webp';
    }

    echo '<img class="box-shadow" src="'. $url .'" />';
    exit();
}

if(isset($_GET['local'])){
    if (isset($_GET['s2'])) {
        // Initialise la connexion à la base de données
        $database = new Db();
        
        $viewService = new \App\Service\ViewService($database, $player->coords->x, $player->coords->y, $player->coords->plan);
        
        echo '<div>
            <a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a>
            <a href="map.php"><button>Monde</button></a>
            <a href="map.php?s2"><button>Monde s2</button></a>
            <a href="map.php?local"><button>' . $planJson->name . '</button></a>
            <a href="map.php?local&s2"><button>' . $planJson->name . ' s2</button></a>
        </div><br />';

        // Formulaire de sélection des couches
        echo '<div class="layer-controls" style="margin-bottom: 15px;">
            <form method="GET" action="map.php" style="display: inline-block;">
                <input type="hidden" name="local" value="1">
                <input type="hidden" name="s2" value="1">
                <label><input type="checkbox" name="layers[]" value="tiles" ' . 
                ((!isset($_GET['layers']) || in_array('tiles', $_GET['layers'] ?? [])) ? 'checked' : '') . 
                '> Terrain</label>
                <label><input type="checkbox" name="layers[]" value="elements" ' . 
                ((!isset($_GET['layers']) || in_array('elements', $_GET['layers'] ?? [])) ? 'checked' : '') . 
                '> Éléments</label>
                <label><input type="checkbox" name="layers[]" value="coordinates" ' . 
                (in_array('coordinates', $_GET['layers'] ?? []) ? 'checked' : '') . 
                '> Coordonnées</label>
                <label><input type="checkbox" name="layers[]" value="lieux" ' . 
                (in_array('lieux', $_GET['layers'] ?? []) ? 'checked' : '') . 
                '> Lieux</label>
                <label><input type="checkbox" name="layers[]" value="routes" ' . 
                (in_array('routes', $_GET['layers'] ?? []) ? 'checked' : '') . 
                '> Routes</label>
                <label><input type="checkbox" name="layers[]" value="players" ' . 
                (in_array('players', $_GET['layers'] ?? []) ? 'checked' : '') . 
                '> Tous les joueurs</label>
                <label><input type="checkbox" name="layers[]" value="player" ' . 
                (in_array('player', $_GET['layers'] ?? []) ? 'checked' : '') . 
                '> Ma position</label>
                <button type="submit">Actualiser la carte</button>
            </form>
        </div>';

        // Récupère les couches sélectionnées ou utilise les valeurs par défaut
        $selectedLayers = $_GET['layers'] ?? ['tiles', 'elements'];
        
        // Debug logging
        error_log("Debug - Player coordinates: x={$player->coords->x}, y={$player->coords->y}, z={$player->coords->z}");
        error_log("Debug - Plan ID: " . $planJson->id);
        
        // Génère la carte locale pour saison2
        $mapResult = $viewService->generateLocalMap($player->coords->plan, $selectedLayers);
        error_log("Debug - Selected Layers: " . print_r($selectedLayers, true));
        error_log("Debug - Map Result: " . print_r($mapResult, true));

        if (isset($mapResult['imagePath']) && file_exists($mapResult['imagePath'])) {
            // Convertit l'image en base64 pour l'affichage inline
            $imageData = base64_encode(file_get_contents($mapResult['imagePath']));

            // Crée le HTML pour la carte cliquable
            echo '<map name="terrain-map">';
            foreach ($mapResult['imageMap'] as $area) {
                $coords = implode(',', $area['coords']);
                $title = ucfirst(str_replace('_', ' ', $area['name']));
                echo sprintf(
                    '<area shape="rect" coords="%s" title="%s" alt="%s">',
                    htmlspecialchars($coords),
                    htmlspecialchars($title),
                    htmlspecialchars($title)
                );
            }
            echo '</map>';

            // Affiche l'image avec la carte cliquable
            echo sprintf(
                '<img class="box-shadow" src="data:image/png;base64,%s" alt="Carte locale S2" usemap="#terrain-map" style="cursor:help">',
                $imageData
            );
        } else {
            echo '<p>Impossible de générer la carte locale S2.</p>';
        }

        echo Str::minify(ob_get_clean());
        exit();
    }
    include('scripts/map/local.php');
    echo Str::minify(ob_get_clean());
    exit();
}
?>
<div>
    <a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a>
    <a href="map.php"><button>Monde</button></a>
    <a href="map.php?s2"><button>Monde s2</button></a>
    <a href="map.php?local"><button><?php echo $planJson->name ?></button></a>
    <a href="map.php?local&s2"><button><?php echo $planJson->name ?> s2</button></a>
</div>

<?php echo Ui::print_map($player, $planJson) ?>

<script>
// Définit les coordonnées du plan et la carte complète
window.coordsPlan = "<?php echo $player->coords->plan ?>";
window.allMap = <?php echo (isset($_GET['allMap'])) ? 'true' : 'false' ?>;
window.triggerId = <?php echo (!empty($_GET['triggerId']) && is_numeric($_GET['triggerId'])) ? $_GET['triggerId'] : 'false' ?>;

// Inclut le script de déplacement
<?php include('scripts/map/travel.php') ?>

// Affiche les informations de la zone survolée
function showTooltip(data) {
    let content = '';

    // Affiche les informations du joueur
    if (data.type === 'player') {
        content = `Joueur ${data.race}<br>Position: ${data.x}, ${data.y}`;
    }
    // Affiche les informations du lieu
    else if (data.type === 'lieu') {
        content = `${data.name}<br>Position: ${data.x}, ${data.y}`;
    }
    // Affiche les informations de la route
    else if (data.type === 'route') {
        content = `Route<br>Position: ${data.x}, ${data.y}`;
    }
    // Affiche les informations de la zone
    else {
        content = `${data.name}<br>Position: ${data.x}, ${data.y}`;
    }

    // Affiche le contenu dans la bulle d'information
    $('#tooltip').html(content).show();
}
</script>
<script src="js/map.js"></script>
<?php

echo Str::minify(ob_get_clean());
