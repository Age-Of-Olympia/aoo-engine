<?php
use Classes\Player;
use Classes\Ui;
use Classes\Str;
use Classes\Db;
require_once('config.php');

$worldPlan = 'olympia';
$player = new Player($_SESSION['playerId']);
$player->getCoords();

$isInHell = (isset($player->coords->plan) && $player->coords->plan === 'enfers');

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
$database = new Db();

// Redirect to local map by default if not on olympia
if (empty($_GET)) {
    if ($planJson->id === $worldPlan) {
        header('Location: map.php?world'); // Global map for world plan
    } else {
        header('Location: map.php?local'); // Local map for other plans
    }
    exit();
}

$ui = new Ui('Carte du Monde');
ob_start();
 function generateLayerCheckbox($name, $value) {
        echo '<label><input type="checkbox" name="layers[]" value="' . $value . '" ' . 
               ((!isset($_GET['layers']) || in_array('tiles', $_GET['layers'] ?? [])) ? 'checked' : '') . '> ' . $name . '</label>';
    }
    function printHell()
    {
        echo '<div class="hell-message" style="text-align: center; margin: 20px 0; padding: 15px; background-color: #330000; border: 1px solid #660000; color: #ff6666;">';
        echo '<h2>Vous êtes aux Enfers</h2>';
        echo '<p>On va pas vous faire un dessin, vous êtes bien dans le royaume des morts.<br>';
        echo 'La sortie est en 0,0<br>';
        echo 'La boutique souvenirs est en 1,1<br>';
        echo 'L\'amphithéâtre de Perséphone en -10,1<br>';
        echo 'Et la paillote de Her\'eal en -2,10</p>';
        echo '</div>';
    }
//  Carte globale
if (!isset($_GET['local'])) {
    echo '<div>
        <a href="index.php"><button><span class="ra ra-sideswipe"></span>Retour</button></a>';
    
    // Show "Monde" button only if player is on the olympia plan
    if ($planJson->id === $worldPlan) {
        echo '<a href="map.php?world"><button>Monde</button></a>';
    } else {
        echo '<a href="map.php?world"><button>Monde</button></a>
              <a href="map.php?local"><button>' . $planJson->name . $zLevelName . '</button></a>';
    }
    echo '</div>'; // Fermer la div des boutons

    // Afficher le message des enfers si nécessaire
    if ($isInHell) {
        printHell();
        echo Str::minify(ob_get_clean());
        exit();
    }
    echo '<h1>Olympia</h1>';
    // Admin-only map panel redirection
    if ($player->have_option('isAdmin')) {
        echo '<div style="margin-bottom: 15px; padding-top: 10px;">
            <a href="admin/world_map.php"><button>Admin World Map Panel</button></a>
        </div>';
    }
   
    // Formulaire de sélection des couches
    echo '<div class="layer-controls" style="margin-bottom: 15px;">
            <form method="GET" action="map.php" style="display: inline-block;">';
    
    generateLayerCheckbox('Terrain', 'tiles');
    generateLayerCheckbox('Éléments', 'elements');
    generateLayerCheckbox('Coordonnées', 'coordinates');
    generateLayerCheckbox('Lieux', 'locations');
    generateLayerCheckbox('Routes', 'routes');
    generateLayerCheckbox('Tous les joueurs', 'players');
    generateLayerCheckbox('Ma position', 'player');
            
          echo '<button type="submit">Actualiser la carte</button>
        </form>
    </div>';

    // Récupère les couches sélectionnées ou utilise les valeurs par défaut
    $selectedLayers = $_GET['layers'] ?? ['tiles', 'elements', 'coordinates', 'locations', 'routes', 'players', 'player'];

    try {
        $viewService = new \App\Service\ViewService($database, $player->coords->x, $player->coords->y,$player->coords->z, $player->id, $planJson->id);
        $mapResult = $viewService->getGlobalMap();
        $worldPlayersLayerPath = $viewService->generateWorldPlayersLayer();
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
                echo '<image xlink:href="' . $fullPath . '"
                         width="700" height="466" x="60" y="33" />';
            }
        }

        // Special handling for players & player layer
        if (in_array('players', $selectedLayers)) {
            $playersLayerPath = '/img/maps/global_map_player_' . $_SESSION['playerId'] . '_players_layer.png';
            $fullPath = $_SERVER['DOCUMENT_ROOT'].$playersLayerPath;
            if (file_exists($fullPath)) {
                list($width, $height) = getimagesize($fullPath);
                echo '<image xlink:href="' . $playersLayerPath . '" width="' . $width . '" height="' . $height . '" x="60" y="33" />';
            }
        }

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
        // Display navigation buttons first
        echo '<div>
            <a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a>
            <a href="map.php?world"><button>Monde</button></a>
            <a href="map.php?local"><button>' . $planJson->name . $zLevelName . '</button></a>
        </div>';
        
        // Afficher le message des enfers si nécessaire
        if ($isInHell) {
            printHell();
            echo Str::minify(ob_get_clean());
            exit();
        }
        
        echo '<br />';

        // Admin-only map panel redirection
        if ($player->have_option('isAdmin')) {
            echo '<div style="margin-bottom: 15px; padding-top: 10px;">
                <a href="admin/local_maps.php"><button>Admin Local Map Panel</button></a>
            </div>';
        }
        echo '<h1>'. $planJson->name .'</h1>';
        // Formulaire de sélection des couches
        echo '<div class="layer-controls" style="margin-bottom: 15px;">
            <form method="GET" action="map.php" style="display: inline-block;">
                <input type="hidden" name="local" value="1">
                <input type="hidden" name="layers[]" value="tiles">
                <label><input type="checkbox" name="layers[]" value="tiles" checked disabled> Terrain</label>';
                
                generateLayerCheckbox('Éléments', 'elements');
                generateLayerCheckbox('Décor', 'foregrounds');
                generateLayerCheckbox('Murs', 'walls');
                generateLayerCheckbox('Routes', 'routes');
                generateLayerCheckbox('Tous les joueurs', 'players');
                generateLayerCheckbox('Ma position', 'player');
               
                echo '<button type="submit">Actualiser la carte</button>
            </form>
        </div>';

        // Récupère les couches sélectionnées ou utilise les valeurs par défaut
        $selectedLayers = $_GET['layers'] ?? ['tiles', 'elements', 'foregrounds', 'walls', 'routes', 'players', 'player'];

        try {
            $viewService = new \App\Service\ViewService($database, $player->coords->x, $player->coords->y,$player->coords->z, $player->id, $planJson->id);
            $mapResult = $viewService->getLocalMap();
            $localPlayersLayerPath = $viewService->generateLocalPlayersLayer();
            $localPlayerLayerPath = $viewService->generateLocalPlayerLayer();
        } catch (Exception $e) {
            echo '<div style="padding: 15px; margin: 15px; border: 1px solid #ccc; background: white;">';
            echo 'Carte locale : ' . htmlspecialchars($e->getMessage());
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
            // Use the 'tiles' layer to get base dimensions
            $tileLayerPath = $mapResult['tiles']['imagePath'] ?? null; 
            if ($tileLayerPath && file_exists($_SERVER['DOCUMENT_ROOT'] . $tileLayerPath)) {
                list($imageWidth, $imageHeight) = getimagesize($_SERVER['DOCUMENT_ROOT'] . $tileLayerPath);
            } else {
                $imageWidth = 600;
                $imageHeight = 400;
            }
            
            echo '<div id="ui-map" style="position: relative;">';
            
            echo '
            <svg width="' . $imageWidth . '" height="' . $imageHeight . '" viewBox="0 0 ' . $imageWidth . ' ' . $imageHeight . '" 
                style="overflow: visible">
                
                <!-- Overlay the map layers - The loop below handles this -->
                ';

                $layerOrder = ['tiles', 'elements', 'foregrounds', 'walls', 'routes', 'players', 'player'];

                foreach ($layerOrder as $layer) {
                    if (in_array($layer, $selectedLayers) && isset($mapResult[$layer]['imagePath'])) {
                        $fullPath = $_SERVER['DOCUMENT_ROOT'].$mapResult[$layer]['imagePath'];
                        if (file_exists($fullPath)) {
                            list($width, $height) = getimagesize($fullPath);
                            echo '<image xlink:href="' . $mapResult[$layer]['imagePath'] . '" width="' . $width . '" height="' . $height . '" />';
                        }
                    }
                }

                // Special handling for players & player layers
                if (in_array('players', $selectedLayers)) {
                    $playersLayerPath = '/img/maps/local_map_player_' . $_SESSION['playerId'] . '_players_layer.png';
                    $fullPath = $_SERVER['DOCUMENT_ROOT'].$playersLayerPath;
                    if (file_exists($fullPath)) {
                        list($width, $height) = getimagesize($fullPath);
                        echo '<image xlink:href="' . $playersLayerPath . '" width="' . $width . '" height="' . $height . '" />';
                    }
                }

                if (in_array('player', $selectedLayers)) {
                    $playerLayerPath = '/img/maps/local_map_player_' . $_SESSION['playerId'] . '_layer.png';
                    $fullPath = $_SERVER['DOCUMENT_ROOT'].$playerLayerPath;
                    if (file_exists($fullPath)) {
                        list($width, $height) = getimagesize($fullPath);
                        echo '<image xlink:href="' . $playerLayerPath . '" width="' . $width . '" height="' . $height . '" />';
                    }
                }
                echo '</svg></div>';
                

                if ($player->have_option('isAdmin') && !empty($planJson->z_levels)) {

                    usort($planJson->z_levels, function($a, $b) {
                        return $b->z <=> $a->z;
                    });

                    echo '<div style="text-align: center; margin: 10px 0; padding: 5px; border-radius: 3px; display: flex; flex-wrap: wrap; justify-content: center; gap: 5px;">';
                    foreach ($planJson->z_levels as $zLevel) {
                        $isCurrent = $player->coords->z == $zLevel->z;
                        $style = $isCurrent ? 'background: #007bff; color: white;' : 'background: white; color: black;';
                        echo '<button style="padding: 2px 5px; border: 1px solid #ccc; border-radius: 3px; cursor: pointer; ' . $style . '">
                            Z' . $zLevel->z . ': ' . ($zLevel->{'z-name'} ?? 'Niveau ' . $zLevel->z) . '
                        </button>';
                    }
                    echo '</div>';
                }
        } else {
            echo '<p>La carte n\'est pas encore générée.</p>';
        }

        echo Str::minify(ob_get_clean());

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
