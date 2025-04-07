<?php

require_once('config.php');

$worldPlan = 'olympia';
$player = new Player($_SESSION['playerId']);
$player->getCoords();
$planJson = json()->decode('plans', $player->coords->plan);
$planJson->id = $player->coords->plan;
$planJson->fromCoords = $player->coords;
$zLevelName = '';
if ($planJson->id !== $worldPlan && property_exists($planJson, 'z_levels') && count($planJson->z_levels) > 1) {
    foreach ($planJson->z_levels as $zLevel) {
        if ($zLevel->z === $player->coords->z) {
            $zLevelName = ' - ' . ($zLevel->{'z-name'} ?? 'Niveau ' . $player->coords->z);
            break;
        }
    }
}

// Redirect to s2 map by default if no parameters are specified
if (empty($_GET) || (!isset($_GET['local']) && !isset($_GET['s2']))) {
    if ($planJson->id === $worldPlan) {
        header('Location: map.php?s2'); // Global map for world plan
    } else {
        header('Location: map.php?local&s2'); // Local map for other plans
    }
    exit();
}

$ui = new Ui('Carte du Monde');
ob_start();

//  Carte globale
if (isset($_GET['s2']) && !isset($_GET['local'])) {
    echo '<div>
        <a href="index.php"><button><span class="ra ra-sideswipe"></span>Retour</button></a>';
    
    // Show "Monde" button only if player is on the olympia plan
    if ($planJson->id === $worldPlan) {
        echo '<a href="map.php?s2"><button>Monde</button></a>';
    } else {
        echo '<a href="map.php?s2"><button>Monde</button></a>
              <a href="map.php?local&s2"><button>' . $planJson->name . $zLevelName . '</button></a>';
    }

    // Admin-only map panel redirection
    if ($player->have_option('isAdmin')) {
        echo '<div style="margin-bottom: 15px; padding-top: 10px;">
            <a href="admin/world_map.php"><button>Admin Map Panel</button></a>
        </div>';
    }

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
            ((!isset($_GET['layers']) || in_array('coordinates', $_GET['layers'] ?? [])) ? 'checked' : '') . 
            '> Coordonnées</label>
            <label><input type="checkbox" name="layers[]" value="locations" ' . 
            ((!isset($_GET['layers']) || in_array('locations', $_GET['layers'] ?? [])) ? 'checked' : '') . 
            '> Lieux</label>
            <label><input type="checkbox" name="layers[]" value="routes" ' . 
            ((!isset($_GET['layers']) || in_array('routes', $_GET['layers'] ?? [])) ? 'checked' : '') . 
            '> Routes</label>
            <label><input type="checkbox" name="layers[]" value="player" ' . 
            ((!isset($_GET['layers']) || in_array('player', $_GET['layers'] ?? [])) ? 'checked' : '') . 
            '> Ma position</label>
            <button type="submit">Actualiser la carte</button>
        </form>
    </div>';

    // Récupère les couches sélectionnées ou utilise les valeurs par défaut
    $selectedLayers = $_GET['layers'] ?? ['tiles', 'elements', 'coordinates', 'locations', 'routes', 'player'];

    // Initialise la connexion à la base de données
    $database = new Db();

    // Récupère les coordonnées du joueur
    $player = new Player($_SESSION['playerId']);
    $player->getCoords();

    try {
        $viewService = new \App\Service\ViewService($database, $player->coords->x, $player->coords->y,$player->coords->z, $player->id, $worldPlan);
        $mapResult = $viewService->getGlobalMap();
        $worldPlayerLayerPath = $viewService->generateWorldPlayerLayer();
    } catch (Exception $e) {
        echo '<div style="padding: 15px; margin: 15px; border: 1px solid #ccc; background: white;">';
        echo 'Carte du monde : ' . htmlspecialchars($e->getMessage());
        echo '</div>';
        echo Str::minify(ob_get_clean());
        exit();
    }

    $hasValidLayers = false;
    if (is_array($mapResult)) {
        foreach ($selectedLayers as $layer) {
            $layerData = $mapResult[$layer] ?? null;
            $imagePath = $layerData['imagePath'] ?? null;

            $fullPath = $_SERVER['DOCUMENT_ROOT'].$imagePath;
            if ($imagePath && file_exists($fullPath)) {
                $hasValidLayers = true;
                break;
            }
        }
    }

    if ($hasValidLayers) {
        echo '
        <div id="ui-map">
        <?xml version="1.0" encoding="UTF-8" standalone="no"?>
        <svg
            xmlns="http://www.w3.org/2000/svg"
            xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1"
            baseProfile="full"
            id="svg-map"
            width="800"
            height="532"
            style="overflow: visible"
            >
            <!-- Parchment background -->
            <image xlink:href="img/ui/map/parchemin.webp" width="800" height="532" />';
            
            $layerOrder = ['tiles', 'elements', 'coordinates', 'locations', 'routes', 'player'];
            
            foreach ($layerOrder as $layer) {
                if (in_array($layer, $selectedLayers) && isset($mapResult[$layer]['imagePath'])) {
                    $fullPath = $mapResult[$layer]['imagePath'];
                    echo '<image xlink:href="'.$fullPath.'"
                         width="700" height="466" x="60" y="33" />';
                }
            }

            // Special handling for player layer
            if (in_array('player', $selectedLayers)) {
                $fullPath = $worldPlayerLayerPath;
                if (file_exists($fullPath)) {
                    list($width, $height) = getimagesize($fullPath);
                    echo '<image xlink:href="' . $worldPlayerLayerPath . '"
                         width="' . $width . '" height="' . $height . '" x="60" y="33" />';
                }
            }

            echo '</svg></div>';
    } else {
        echo '<p>La carte n\'est pas encore générée.</p>';
    }

    echo Str::minify(ob_get_clean());
    exit();
}

// Carte locale
if(isset($_GET['local'])){
    if (isset($_GET['s2'])) {
        $database = new Db();
        // Display navigation buttons first
        echo '<div>
            <a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a>
            <a href="map.php?s2"><button>Monde</button></a>
            <a href="map.php?local&s2"><button>' . $planJson->name . $zLevelName . '</button></a>
        </div><br />';

        // Then handle errors
        try {
            $viewService = new \App\Service\ViewService($database, $player->coords->x, $player->coords->y,$player->coords->z, $player->id, $planJson->id);
        } catch (Exception $e) {
            echo '<div style="padding: 15px; margin: 15px; border: 1px solid #ccc; background: white;">';
            echo 'Carte locale : ' . htmlspecialchars($e->getMessage());
            echo '</div>';
            echo Str::minify(ob_get_clean());
            exit();
        }
        try {
            $selectedLayers = $_GET['layers'] ?? ['tiles', 'elements', 'foregrounds', 'walls', 'routes', 'players', 'player'];
            $mapResult = $viewService->generateLocalMap($selectedLayers);
        } catch (Exception $e) {
            echo '<div style="padding: 15px; margin: 15px; border: 1px solid #ccc; background: white;">';
            echo 'Carte locale : ' . htmlspecialchars($e->getMessage());
            echo '</div>';
            echo Str::minify(ob_get_clean());
            exit();
        }

        // Formulaire de sélection des couches
        echo '<div class="layer-controls" style="margin-bottom: 15px;">
            <form method="GET" action="map.php" style="display: inline-block;">
                <input type="hidden" name="local" value="1">
                <input type="hidden" name="s2" value="1">
                <input type="hidden" name="layers[]" value="tiles">
                <label><input type="checkbox" name="layers[]" value="tiles" checked disabled> Terrain</label>
                <label><input type="checkbox" name="layers[]" value="elements" ' . 
                ((!isset($_GET['layers']) || in_array('elements', $_GET['layers'] ?? [])) ? 'checked' : '') . 
                '> Éléments</label>
                <label><input type="checkbox" name="layers[]" value="foregrounds" ' . 
                ((!isset($_GET['layers']) || in_array('foregrounds', $_GET['layers'] ?? [])) ? 'checked' : '') . 
                '> Foregrounds</label>
                <label><input type="checkbox" name="layers[]" value="walls" ' . 
                ((!isset($_GET['layers']) || in_array('walls', $_GET['layers'] ?? [])) ? 'checked' : '') . 
                '> Murs</label>
                <label><input type="checkbox" name="layers[]" value="routes" ' . 
                ((!isset($_GET['layers']) || in_array('routes', $_GET['layers'] ?? [])) ? 'checked' : '') . 
                '> Routes</label>
                <label><input type="checkbox" name="layers[]" value="players" ' . 
                ((!isset($_GET['layers']) || in_array('players', $_GET['layers'] ?? [])) ? 'checked' : '') . 
                '> Tous les joueurs</label>
                <label><input type="checkbox" name="layers[]" value="player" ' . 
                ((!isset($_GET['layers']) || in_array('player', $_GET['layers'] ?? [])) ? 'checked' : '') . 
                '> Ma position</label>
                <button type="submit">Actualiser la carte</button>
            </form>
        </div>';

        if (isset($mapResult['imagePath']) && file_exists($_SERVER['DOCUMENT_ROOT'].$mapResult['imagePath'])) {
            list($imageWidth, $imageHeight) = getimagesize($_SERVER['DOCUMENT_ROOT'].$mapResult['imagePath']);
            echo '<div id="ui-map" style="position: relative;">';
            
            if ($player->have_option('isAdmin')) {
                // Sort z_levels by z value in descending order
                usort($planJson->z_levels, function($a, $b) {
                    return $b->z <=> $a->z;
                });
                
                echo '<div style="position: absolute; right: 1px; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.8); padding: 5px; border-radius: 3px; display: flex; flex-direction: column; gap: 3px;">';
                foreach ($planJson->z_levels as $zLevel) {
                    $isCurrent = $player->coords->z == $zLevel->z;
                    echo '<button style="' . ($isCurrent ? 'background: #007bff; color: white;' : '') . '">
                        Z' . $zLevel->z . ': ' . ($zLevel->{'z-name'} ?? 'Niveau ' . $zLevel->z) . '
                    </button>';
                }
                echo '</div>';
            }
            
            echo '
            <svg
                xmlns="http://www.w3.org/2000/svg"
                xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1"
                baseProfile="full"
                id="svg-map"
                width="' . $imageWidth . '"
                height="' . $imageHeight . '"
                style="overflow: visible">
                
                <!-- Overlay the local map -->
                <image xlink:href="' . $mapResult['imagePath'] . '" width="' . $imageWidth . '" height="' . $imageHeight . '" />
                <!-- Add the player(s) layer(s) if selected -->
                ';
                if (in_array('players', $selectedLayers)) {
                    $playersLayerPath = 'img/maps/local_map_player_' . $_SESSION['playerId'] . '_players_layer.png';
                    $fullPath = $_SERVER['DOCUMENT_ROOT'].$playersLayerPath;
                    if (file_exists($fullPath)) {
                        list($width, $height) = getimagesize($fullPath);
                        echo '<image xlink:href="' . $playersLayerPath . '" width="' . $width . '" height="' . $height . '" />';
                    }
                }
                if (in_array('player', $selectedLayers)) {
                    $playerLayerPath = 'img/maps/local_map_player_' . $_SESSION['playerId'] . '_layer.png';
                    $fullPath = $_SERVER['DOCUMENT_ROOT'].$playerLayerPath;
                    if (file_exists($fullPath)) {
                        list($width, $height) = getimagesize($fullPath);
                        echo '<image xlink:href="' . $playerLayerPath . '" width="' . $width . '" height="' . $height . '" />';
                    }
                }
                echo '</svg></div>';
        } else {
            echo '<p>La carte n\'est pas encore générée. Veuillez cliquer sur "Régénérer la carte".</p>';
        }

        echo Str::minify(ob_get_clean());
        // exit();
    }
    $playerZ = $player->coords->z;
    include('scripts/map/local_map.php');
    echo Str::minify(ob_get_clean());
    exit();
}

// Si le joueur est hors de la carte
if(!$planJson){
    echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a></div><br />';

    $url = 'img/ui/illustrations/'. $player->coords->plan .'.webp';
    if(!file_exists($_SERVER['DOCUMENT_ROOT'].$url)){
        $url = 'img/ui/illustrations/gaia.webp';
    }

    echo '<img class="box-shadow" src="'. $url .'" />';
    exit();
}

?>
