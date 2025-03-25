<?php

namespace App\Service;

class ViewService {
    private $width = 700;
    private $height = 466;
    private $localWidth = 400;
    // private $localHeight = 300;
    private $currentPlan;
    private $image;
    private $layers = [];
    private $db;
    private $scaleX;
    private $scaleY;
    private $minX;
    private $minY;
    private $maxX;
    private $maxY;
    private $margin = 20;
    private $colors = [];
    private $playerX;
    private $playerY;
    private $playerZ;
    private $playerId;
    private $raceService;
    private $colorService;
    private $worldPlan = 'olympia';
    private $localMinX;
    private $localMaxX;
    private $localMinY;
    private $localMaxY;
    private $localScaleX;
    private $localScaleY;
    private $localMapWidth;
    private $localMapHeight;
    private $localOffsetX = 0;
    private $localOffsetY = 0;
    private $localCenterX = 0;
    private $localCenterY = 0;

    public function __construct($db, $playerX = null, $playerY = null, $playerZ = null, $playerId = null, $plan = 'olympia') {
        $this->db = $db;
        $this->playerX = $playerX;
        $this->playerY = $playerY;
        $this->playerZ = $playerZ;
        $this->playerId = $playerId;
        $this->currentPlan = $plan;
        $this->raceService = new RaceService($db);
        $this->colorService = new ColorService();
        $this->calculateBounds();
        $this->colors = $this->colorService->initializePastelColors();
    }

    private function calculateBounds() {
        $worldPlanBounds = $this->getBoundsFromPlan($this->worldPlan);

        // Global map
        if ($worldPlanBounds !== null) {
            $this->minX = $worldPlanBounds['minX'];
            $this->maxX = $worldPlanBounds['maxX'];
            $this->minY = $worldPlanBounds['minY'];
            $this->maxY = $worldPlanBounds['maxY'];
            $this->scaleX = ($this->width - 2 * $this->margin) / ($this->maxX - $this->minX);
            $this->scaleY = ($this->height - 2 * $this->margin) / ($this->maxY - $this->minY);
        }

        if ($this->currentPlan !== $this->worldPlan) {
            // Local map
            $planData = $this->getPlanData($this->currentPlan);
            if ($planData && isset($planData->z_levels[$this->playerZ])) {
                $zLevel = $planData->z_levels[$this->playerZ];
                $this->localMinX = $zLevel->visibleBoundsMinX;
                $this->localMaxX = $zLevel->visibleBoundsMaxX;
                $this->localMinY = $zLevel->visibleBoundsMinY;
                $this->localMaxY = $zLevel->visibleBoundsMaxY;
            }

            // Scale calculations
            $rangeX = (float)($this->localMaxX - $this->localMinX);
            $rangeY = (float)($this->localMaxY - $this->localMinY);
            
            // Fixed width for local map
            $this->localMapWidth = $this->localWidth;

            $this->localScaleX = $this->localMapWidth / $rangeX;
            $this->localScaleY = $this->localScaleX; // Maintain square aspect ratio
            $this->localMapHeight = (int)($rangeY * $this->localScaleY);
    
            // Centering
            $this->localCenterX = ($this->localMinX + $this->localMaxX) / 2;
            $this->localCenterY = ($this->localMinY + $this->localMaxY) / 2;
    
            // Offset to center the map
            $this->localOffsetX = ($this->localMapWidth / 2) - (($this->localCenterX - $this->localMinX) * $this->localScaleX);
            $this->localOffsetY = ($this->localMapHeight / 2) - (($this->localCenterY - $this->localMinY) * $this->localScaleY);
        }
    }
    
    private function transformX($x, $mapType = "global") {
        if ($mapType === "global") {
            $scale = $this->scaleX;
            $min = $this->minX;
            return (int)($this->margin + ($x - $min) * $scale);
        } else {
            $scale = $this->localScaleX;
            $min = $this->localMinX;
            return (int)($this->localOffsetX + ($x - $min) * $scale);
        }
    }
    
    private function transformY($y, $mapType = "global") {
        if ($mapType === "global") {
            $scale = $this->scaleY;
            $min = $this->minY;
            return (int)($this->height - ($this->margin + ($y - $min) * $scale));
        } else {
            $scale = $this->localScaleY;
            $min = $this->localMinY;
            return (int)($this->localMapHeight - ($this->localOffsetY + ($y - $min) * $scale));
        }
    }
    
    private function getColorForType($name) {
        if (isset($this->colors[$name])) {
            $rgb = $this->colors[$name];
            return imagecolorallocate($this->image, $rgb[0], $rgb[1], $rgb[2]);
        }

        $rgb = $this->colors['default'];
        return imagecolorallocate($this->image, $rgb[0], $rgb[1], $rgb[2]);
    }
    
    private function createLayer($width = null, $height = null) {
        $width = $width ?? $this->width;
        $height = $height ?? $this->height;
        $layer = imagecreatetruecolor($width, $height);

        // Active le canal alpha
        imagealphablending($layer, true);
        imagesavealpha($layer, true);

        // Remplit avec un fond transparent
        $transparent = imagecolorallocatealpha($layer, 0, 0, 0, 127);
        imagefill($layer, 0, 0, $transparent);
        return $layer;
    }

    public function generateLocalMap($selectedLayers = ['tiles']) {
        // Crée l'image de base
        $this->image = $this->createLayer($this->localMapWidth, $this->localMapHeight);

        // Add cave background if Z is negative
        if ($this->playerZ < 0) {
            $darkGrey = imagecolorallocate($this->image, 46, 46, 46);
            imagefilledrectangle(
                $this->image,
                0, 0,
                $this->localMapWidth - 1,
                $this->localMapHeight - 1,
                $darkGrey
            );
        }

        // Add sky background if Z is positive
        if ($this->playerZ > 0) {
            $lightBlue = imagecolorallocate($this->image, 202, 228, 241);
            imagefilledrectangle(
                $this->image,
                0, 0,
                $this->localMapWidth - 1,
                $this->localMapHeight - 1,
                $lightBlue
            );
        }

        // Génère les couches demandées dans un ordre spécifique
        if (in_array('tiles', $selectedLayers)) {
            $this->generateTileLayer($this->currentPlan);
        }
        if (in_array('routes', $selectedLayers)) {
            $this->generateRoutesLayer($this->currentPlan);
        }
        if (in_array('elements', $selectedLayers)) {
            $this->generateElementLayer($this->currentPlan);
        }
        if (in_array('foregrounds', $selectedLayers)) {
            $this->generateForegroundsLayer($this->currentPlan);
        }
        if (in_array('walls', $selectedLayers)) {
            $this->generateWallLayer($this->currentPlan);
        }
        if (in_array('players', $selectedLayers)) {
            $this->generateLocalPlayersLayer();
        }
        if (in_array('player', $selectedLayers)) {
            $this->generateLocalPlayerLayer();
        }
        // Composite les couches sur l'image principale
        foreach ($this->layers as $layer) {
            imagecopy($this->image, $layer, 0, 0, 0, 0, $this->localMapWidth, $this->localMapHeight);
            imagedestroy($layer); // Libère la mémoire
        }
        
        // Ajoute une bordure à l'image finale
        // $borderColor = imagecolorallocate($this->image, 139, 69, 19);
        // imagerectangle($this->image, 0, 0, $this->width-1, $this->height-1, $borderColor);
        
        return [
            'imagePath' => $this->saveImage()
        ];
    }

    public function generateGlobalMap($selectedLayers = ['tiles', 'elements']) {
        // Crée l'image de base
        $this->image = $this->createLayer();
        
        // Génère les couches demandées dans un ordre spécifique
        if (in_array('tiles', $selectedLayers)) {
            $this->generateTileLayer($this->worldPlan);
        }
        if (in_array('elements', $selectedLayers)) {
            $this->generateElementLayer($this->worldPlan);
        }
        if (in_array('coordinates', $selectedLayers)) {
            $this->generateCoordinatesLayer($this->worldPlan);
        }
        if (in_array('routes', $selectedLayers)) {
            $this->generateRoutesLayer($this->worldPlan);
        }
        if (in_array('locations', $selectedLayers)) {
            $this->generateLocationsLayer($this->worldPlan);
        }
        if (in_array('players', $selectedLayers)) {
            $this->generateAllPlayersLayer($this->worldPlan);
        }
        if (in_array('player', $selectedLayers) && $this->playerX !== null && $this->playerY !== null) {
            $this->generateWorldPlayerLayer();
        }
        
        // Composite les couches sur l'image principale
        foreach ($this->layers as $layer) {
            imagecopy($this->image, $layer, 0, 0, 0, 0, $this->width, $this->height);
            imagedestroy($layer); // Libère la mémoire
        }
        
        return [
            'imagePath' => $this->saveImage("global")
        ];
    }

    private function generateForegroundsLayer($plan) { 
        $layer = $this->createLayer();
        $zCondition = ($plan !== $this->worldPlan && $this->playerZ !== null) ? "AND c.z = " . $this->playerZ : "";
        $mapType = $plan === $this->worldPlan ? "global" : "local";

        // Query to fetch tiles and foregrounds
        $query = "SELECT c.x, c.y, mf.name AS foreground_name
            FROM coords c
            INNER JOIN map_foregrounds mf ON mf.coords_id = c.id
            WHERE mf.name = 'ombre'
            AND c.plan = '" . $plan . "'
            AND c.x BETWEEN " . $this->minX . " AND " . $this->maxX . "
            AND c.y BETWEEN " . $this->minY . " AND " . $this->maxY . "
            $zCondition
            ORDER BY c.x, c.y";

        $result = $this->db->exe($query);
        
        while ($tile = mysqli_fetch_assoc($result)) {
            $x = $this->transformX($tile['x'], $mapType);
            $y = $this->transformY($tile['y'], $mapType);
            
            $tileSize = ($mapType === "global") ? 6 : $this->localScaleX;

            $x1 = (int)($x - ($tileSize/2));
            $y1 = (int)($y - ($tileSize/2));
            $x2 = (int)($x + ($tileSize/2));
            $y2 = (int)($y + ($tileSize/2));

            $margin = 0.5;
            $shadowColor = imagecolorallocatealpha($layer, 0, 0, 0, 110); // Ombre semi-transparente
            imagefilledrectangle(
                $layer,
                (int)($x1 + $margin), (int)($y1 + $margin), (int)($x2 - $margin), (int)($y2 - $margin),
                $shadowColor
            );
        }
        
        $this->layers[] = $layer;
    }   

    private function generateTileLayer($plan) {
        $layer = $this->createLayer();
        $zCondition = ($plan !== $this->worldPlan && $this->playerZ !== null) ? "AND c.z = " . $this->playerZ : "";
        $mapType = $plan === $this->worldPlan ? "global" : "local";

        if ($mapType == "local") {
            if ($this->playerZ == 0) {
                $planData = $this->getPlanData($plan);
                $this->applyBackground($layer, $planData, false);
            }
        }

        $query = "SELECT mt.*, c.x, c.y" . 
        ($mapType === "global" ? ", mf.name AS foreground_name" : "") . "
        FROM map_tiles mt 
        JOIN coords c ON c.id = mt.coords_id" . 
        ($mapType === "global" ? " LEFT JOIN map_foregrounds mf ON mf.coords_id = mt.coords_id AND mf.name = 'ombre'" : "") . "
        WHERE c.plan = '" . $plan . "'
        AND c.x BETWEEN " . $this->minX . " AND " . $this->maxX . "
        AND c.y BETWEEN " . $this->minY . " AND " . $this->maxY . "
        $zCondition
        ORDER BY mt.name";

        $result = $this->db->exe($query);
        
        while ($tile = mysqli_fetch_assoc($result)) {
            $x = $this->transformX($tile['x'], $mapType);
            $y = $this->transformY($tile['y'], $mapType);
            $color = $this->getColorForType($tile['name']?? 'default');
            
            $tileSize = ($mapType === "global") ? 6 : $this->localScaleX;

            $x1 = (int)($x - ($tileSize/2));
            $y1 = (int)($y - ($tileSize/2));
            $x2 = (int)($x + ($tileSize/2));
            $y2 = (int)($y + ($tileSize/2));
            
            // Dessine la tuile
            imagefilledrectangle(
                $layer,
                $x1, $y1, $x2, $y2,
                $color
            );

            if ($mapType === "global" && isset($tile['foreground_name']) && $tile['foreground_name'] === 'ombre') {
                $shadowColor = imagecolorallocatealpha($layer, 0, 0, 0, 110);
                imagefilledrectangle(
                    $layer,
                    $x1, $y1, $x2, $y2,
                    $shadowColor
                );
            }
        }
        
        $this->layers[] = $layer;
    }

    private function generateElementLayer($plan) {
        $layer = $this->createLayer();
        $zCondition = ($plan !== $this->worldPlan && $this->playerZ !== null) ? "AND c.z = " . $this->playerZ : "";
        $mapType = $plan === $this->worldPlan ? "global" : "local";

        // Set transparency for cave elements
        $alpha = ($this->playerZ < 0) ? 80 : 0; // 80 = ~30% transparency

        $query = "SELECT me.*, c.x, c.y
            FROM map_elements me 
            JOIN coords c ON c.id = me.coords_id
            WHERE c.plan = '" . $plan . "'
            AND me.name NOT LIKE 'trace_pas_%'
            AND me.name != 'flag_red'
            AND c.x BETWEEN " . $this->minX . " AND " . $this->maxX . "
            AND c.y BETWEEN " . $this->minY . " AND " . $this->maxY . "
            $zCondition
            ORDER BY me.name";

        $result = $this->db->exe($query);
        
        while ($element = mysqli_fetch_assoc($result)) {
            $x = $this->transformX($element['x'], $mapType);
            $y = $this->transformY($element['y'], $mapType);
            $color = $this->getColorForType($element['name']);

            // Add transparency for cave elements
            if ($alpha > 0) {
                $color = imagecolorallocatealpha(
                    $layer, 
                    ($color >> 16) & 0xFF,
                    ($color >> 8) & 0xFF,
                    $color & 0xFF,
                    $alpha
                );
            }

            $elementSize = ($mapType === "global") ? 6 : $this->localScaleX;

            $x1 = (int)($x - ($elementSize/2));
            $y1 = (int)($y - ($elementSize/2));
            $x2 = (int)($x + ($elementSize/2));
            $y2 = (int)($y + ($elementSize/2));
            
            // Dessine l'élément
            imagefilledrectangle(
                $layer,
                $x1, $y1, $x2, $y2,
                $color
            );
        }
        
        $this->layers[] = $layer;
    }
    
    private function generateCoordinatesLayer($plan) {
        $layer = $this->createLayer();
        $zCondition = ($plan !== $this->worldPlan && $this->playerZ !== null) ? "AND c.z = " . $this->playerZ : "";
        $mapType = $plan === $this->worldPlan ? "global" : "local";

        // Récupère les coordonnées minimales et maximales
        $query = "SELECT MIN(c.x) as minX, MAX(c.x) as maxX, 
                        MIN(c.y) as minY, MAX(c.y) as maxY
                FROM coords c
                JOIN map_tiles mt ON c.id = mt.coords_id
                WHERE c.plan = '" . $plan . "'
                AND c.x BETWEEN " . $this->minX . " AND " . $this->maxX . "
                AND c.y BETWEEN " . $this->minY . " AND " . $this->maxY . "";
        $result = $this->db->exe($query);
        $bounds = mysqli_fetch_assoc($result);

        // Crée les couleurs
        $gridColor = imagecolorallocatealpha($layer, 255, 255, 255, 100);  // Blanc semi-transparent
        $textColor = imagecolorallocate($layer, 255, 255, 255);  // Blanc pour le texte
        $textBg = imagecolorallocate($layer, 0, 0, 0);  // Noir pour le fond du texte
        
        // Dessine les lignes verticales et les coordonnées X
        for ($x = $bounds['minX']; $x <= $bounds['maxX']; $x += 10) {  // Toutes les 10 unités
            $screenX = $this->transformX($x, $mapType);
            
            // Dessine la ligne verticale
            imageline($layer, $screenX, 0, $screenX, $this->height, $gridColor);
            
            // Dessine le numéro de coordonnée en haut
            $text = (string)$x;
            imagefilledrectangle($layer, $screenX - 10, 2, $screenX + 10, 12, $textBg);
            imagestring($layer, 2, $screenX - strlen($text) * 2, 2, $text, $textColor);
        }
        
        // Dessine les lignes horizontales et les coordonnées Y
        for ($y = $bounds['minY']; $y <= $bounds['maxY']; $y += 10) {  // Toutes les 10 unités
            $screenY = $this->transformY($y, $mapType);
            
            // Dessine la ligne horizontale
            imageline($layer, 0, $screenY, $this->width, $screenY, $gridColor);
            
            // Dessine le numéro de coordonnée sur le côté gauche
            $text = (string)$y;
            imagefilledrectangle($layer, 2, $screenY - 5, 20, $screenY + 5, $textBg);
            imagestring($layer, 2, 2, $screenY - 4, $text, $textColor);
        }
        
        $this->layers[] = $layer;
    }
    
    private function generateLocationsLayer($plan) {
        $layer = $this->createLayer();
        $locations = $this->getAllLocationsFromPlans();
        $zCondition = ($plan !== $this->worldPlan && $this->playerZ !== null) ? "AND c.z = " . $this->playerZ : "";
        $mapType = $plan === $this->worldPlan ? "global" : "local";

       // Create colors for markers
        $markerColor = imagecolorallocate($layer, 255, 215, 0);  // Or
        $textColor = imagecolorallocate($layer, 0, 0, 0);  // Noir pour le texte
        $textFillColor = imagecolorallocate($layer, 255, 255, 255);  // Blanc pour le fond du texte
        
        foreach ($locations as $location) {
            $x = (int)$this->transformX($location['x'], $mapType);
            $y = (int)$this->transformY($location['y'], $mapType);
            
            // Formate le nom (majuscule la première lettre)
            $name = ucfirst($location['name']);
            $name = iconv('UTF-8', 'ASCII//TRANSLIT', $name);
            
            // Dessine le marqueur de lieu (forme de plus)
            $size = 4;
            imagefilledrectangle($layer, 
                $x - 1, $y - $size,
                $x + 1, $y + $size,
                $markerColor
            );
            imagefilledrectangle($layer,
                $x - $size, $y - 1,
                $x + $size, $y + 1,
                $markerColor
            );
            
            // Draw the location name using imagestring
            $fontSize = 3;  // Taille 1-5, où 5 est la plus grande
            $textWidth = imagefontwidth($fontSize) * strlen($name);
            $textHeight = imagefontheight($fontSize);

            // Positionne le texte en dessous du marqueur
            $textX = (int)($x - ($textWidth / 2));
            $textY = $y + $size + 2;  // Petit espace après le marqueur

            // Dessine le texte avec un contour (pour une meilleure lisibilité)
            for ($dx = -1; $dx <= 1; $dx++) {
                for ($dy = -1; $dy <= 1; $dy++) {
                    if ($dx !== 0 || $dy !== 0) {  // Ignore la position centrale
                        imagestring($layer, $fontSize, $textX + $dx, $textY + $dy, $name, $textColor);
                    }
                }
            }
            // Dessine le texte de remplissage
            imagestring($layer, $fontSize, $textX, $textY, $name, $textFillColor);
        }
        
        $this->layers[] = $layer;
    }
    
    private function generateRoutesLayer($plan) {
        $layer = $this->createLayer();
        $zCondition = ($plan !== $this->worldPlan && $this->playerZ !== null) ? "AND c.z = " . $this->playerZ : "";
        $mapType = $plan === $this->worldPlan ? "global" : "local";

        // Requête les routes à partir de map_routes
        $sql = "SELECT mr.*, c.x, c.y
            FROM map_routes mr
            JOIN coords c ON c.id = mr.coords_id
            WHERE c.plan = '" . $plan . "'
            AND c.x BETWEEN " . $this->minX . " AND " . $this->maxX . "
            AND c.y BETWEEN " . $this->minY . " AND " . $this->maxY . "
            $zCondition
            ORDER BY mr.name, mr.id";
        $result = $this->db->exe($sql);
        
        // Crée les couleurs pour les routes
        $routeColor = ($mapType === 'global') ? imagecolorallocate($layer, 139, 69, 19) : $this->getColorForType('route');
        
        while ($route = mysqli_fetch_assoc($result)) {
            $x = (int)$this->transformX($route['x'], $mapType);
            $y = (int)$this->transformY($route['y'], $mapType);
            
            $size = ($mapType === "global") ? 1 : $this->localScaleX;

            if ($mapType == "local") {
                $x1 = (int)($x - ($size/2));
                $y1 = (int)($y - ($size/2));
                $x2 = (int)($x + ($size/2));
                $y2 = (int)($y + ($size/2));
                
                imagefilledrectangle(
                    $layer,
                    $x1, $y1, $x2, $y2,
                    $routeColor
                );
            } else {
                imagefilledrectangle($layer, 
                    $x - $size, $y - $size,
                    $x + $size, $y + $size,
                    $routeColor
                );
            }
        }
        
        $this->layers[] = $layer;
    }

    private function generateWallLayer($plan) {
        $layer = $this->createLayer();
        $zCondition = ($plan !== $this->worldPlan && $this->playerZ !== null) ? "AND c.z = " . $this->playerZ : "";
        $mapType = $plan === $this->worldPlan ? "global" : "local";

        // Requête les murs à partir de map_murs
        $sql = "SELECT mw.*, c.x, c.y
            FROM map_walls mw
            JOIN coords c ON c.id = mw.coords_id
            WHERE c.plan = '" . $plan . "'
            AND mw.name LIKE '%mur%'
            AND c.x BETWEEN " . $this->minX . " AND " . $this->maxX . "
            AND c.y BETWEEN " . $this->minY . " AND " . $this->maxY . "
            $zCondition
            ORDER BY mw.name, mw.id";

        $result = $this->db->exe($sql);
        
        // Crée les couleurs pour les murs
        $wallColor = imagecolorallocate($layer, 139, 69, 19);  // Marron
        
        while ($wall = mysqli_fetch_assoc($result)) {
            $x = $this->transformX($wall['x'], $mapType);
            $y = $this->transformY($wall['y'], $mapType);
            $color = $this->getColorForType($wall['name']?? 'default');
            
            $tileSize = ($mapType === "global") ? 6 : $this->localScaleX;

            $x1 = (int)($x - ($tileSize/2));
            $y1 = (int)($y - ($tileSize/2));
            $x2 = (int)($x + ($tileSize/2));
            $y2 = (int)($y + ($tileSize/2));
            
            // Dessine la tuile
            imagefilledrectangle(
                $layer,
                $x1, $y1, $x2, $y2,
                $color
            );
        }
        
        $this->layers[] = $layer;
    }

    private function generateLocalPlayersLayer() {
        $layer = $this->createLayer($this->localMapWidth, $this->localMapHeight);
        $zCondition = $this->playerZ !== null ? "AND c.z = " . $this->playerZ : "";
        $mapType = "local";

            // Définit les couleurs pour les races
            $raceColors = [
                'default' => '#ffffff',
                'elfe' => '#008000',
                'geant' => '#661414',
                'hs' => '#2e6650',
                'nain' => '#FF0000',
                'olympien' => '#ff9933',
                'animal' => '#D2B48C',
                'lutin' => '#000000',
                'humain' => '#0000ff',
            ];
    
            // Récupère tous les joueurs avec des coordonnées
            $sql = "
                SELECT c.x, c.y, p.race, p.name as player_name, p.lastLoginTime
                FROM players p 
                JOIN coords c ON c.id = p.coords_id
                WHERE c.x IS NOT NULL 
                AND c.y IS NOT NULL
                AND c.plan = '" . $this->currentPlan . "'
                $zCondition
            ";
            
            $players = $this->db->exe($sql);

            // Dessine chaque joueur
            foreach ($players as $player) {
                // Ignore si les coordonnées sont invalides
                if (!isset($player['x']) || !isset($player['y'])) {
                    continue;
                }

                // Récupère les coordonnées
                $x = $this->transformX($player['x'], $mapType);
                $y = $this->transformY($player['y'], $mapType);

                $size = $this->localScaleX;

                // Récupère la couleur pour la race
                $raceColor = $raceColors[$player['race']] ?? $raceColors['default'];
                list($r, $g, $b) = sscanf($raceColor, "#%02x%02x%02x");
                $playerColor = imagecolorallocate($layer, $r, $g, $b);
                
                $x1 = (int)($x - ($size/2));
                $y1 = (int)($y - ($size/2));
                $x2 = (int)($x + ($size/2));
                $y2 = (int)($y + ($size/2));
                
                imagefilledrectangle(
                    $layer,
                    $x1, $y1, $x2, $y2,
                    $playerColor
                );
            }
            
            // Sauvegarde la couche des joueurs en tant qu'image PNG
            $this->saveLayer($layer, 'players_layer.png', $this->playerId, $mapType);
            imagedestroy($layer);
    }

    private function generateAllPlayersLayer($plan) {
        $layer = $this->createLayer();
        $zCondition = ($plan !== $this->worldPlan && $this->playerZ !== null) ? "AND c.z = " . $this->playerZ : "";
        $mapType = $plan === $this->worldPlan ? "global" : "local";
        
        // Définit les couleurs pour les races
        $raceColors = [
            'default' => '#ffffff',
            'elfe' => '#008000',
            'geant' => '#661414',
            'hs' => '#2e6650',
            'nain' => '#FF0000',
            'olympien' => '#ff9933',
            'animal' => '#D2B48C',
            'lutin' => '#000000',
            'humain' => '#0000ff',
        ];
        
        // Récupère tous les joueurs avec des coordonnées
        $sql = "
            SELECT c.x, c.y, p.race, p.name as player_name, p.lastLoginTime
            FROM players p 
            JOIN coords c ON c.id = p.coords_id
            WHERE c.x IS NOT NULL 
            AND c.y IS NOT NULL
            AND c.plan = '" . $plan . "'
        ";
        
        $players = $this->db->exe($sql);

        // Dessine chaque joueur
        foreach ($players as $player) {
            // Ignore si les coordonnées sont invalides
            if (!isset($player['x']) || !isset($player['y'])) {
                continue;
            }

            // Récupère les coordonnées
            $x = $this->transformX($player['x'], $mapType);
            $y = $this->transformY($player['y'], $mapType);

            // Récupère la couleur pour la race
            $raceColor = $raceColors[$player['race']] ?? $raceColors['default'];
            
            // Convertit la couleur hexadécimale en RVB
            list($r, $g, $b) = sscanf($raceColor, "#%02x%02x%02x");
            
            // Alloue la couleur
            $playerColor = imagecolorallocate($layer, $r, $g, $b);
            
            // Dessine un carré 2x2 pour le joueur
            $size = 1; // Cela fera un carré 2x2 (1 pixel dans chaque direction à partir du centre)
            imagefilledrectangle($layer, 
                $x - $size, $y - $size, 
                $x + $size, $y + $size, 
                $playerColor
            );
        }
        
        $this->layers[] = $layer;
    }

    private function generateLocalPlayerLayer() {
        $bounds = $this->calculateLocalPlayerLayerBounds();
        $mapType = "local";

        // Create a layer with adjusted size
        $layer = $this->createLayer($bounds['width'], $bounds['height']);
        $x = (int)$this->transformX($this->playerX, $mapType);
        $y = (int)$this->transformY($this->playerY, $mapType);

        // Crée les couleurs pour le marqueur de joueur
        $markerColor = imagecolorallocate($layer, 255, 0, 0);  // Rouge
        $pulseColor = imagecolorallocatealpha($layer, 255, 0, 0, 80);  // Rouge semi-transparent
        
        // Dessine le cercle de pulsation extérieur
        $pulseSize = 4;
        imagefilledellipse($layer, $x, $y, $pulseSize * 2, $pulseSize * 2, $pulseColor);
        
        // Dessine le marqueur de position du joueur (cercle plein)
        $markerSize = 2;
        imagefilledellipse($layer, $x, $y, $markerSize, $markerSize, $markerColor);
        
        // Sauvegarde la couche du joueur en tant qu'image PNG
        $this->saveLayer($layer, 'layer.png', $this->playerId, $mapType);
        imagedestroy($layer);
    }

    private function generateWorldPlayerLayer() {
        // Calculate bounds to include the player's position
        $bounds = $this->calculateWorldPlayerLayerBounds();
        $zCondition = ($this->currentPlan !== $this->worldPlan && $this->playerZ !== null) ? "AND c.z = " . $this->playerZ : "";
        $mapType = "global";

        // Create a layer with adjusted size
        $layer = $this->createLayer($bounds['width'], $bounds['height']);
        
        if ($this->currentPlan !== 'olympia') {
            $location = $this->getLocationFromPlan($this->currentPlan);
            if (isset($location[0]) && is_array($location[0])) {
                $x = (int)$this->transformX($location[0]['x'], $mapType);
                $y = (int)$this->transformY($location[0]['y'], $mapType);
            }
        } else {
            $x = (int)$this->transformX($this->playerX, $mapType);
            $y = (int)$this->transformY($this->playerY, $mapType);
        }
        
        // Crée les couleurs pour le marqueur de joueur
        $markerColor = imagecolorallocate($layer, 255, 0, 0);  // Rouge
        $pulseColor = imagecolorallocatealpha($layer, 255, 0, 0, 80);  // Rouge semi-transparent
        
        // Dessine le cercle de pulsation extérieur
        $pulseSize = 8;
        imagefilledellipse($layer, $x, $y, $pulseSize * 2, $pulseSize * 2, $pulseColor);
        
        // Dessine le marqueur de position du joueur (cercle plein)
        $markerSize = 4;
        imagefilledellipse($layer, $x, $y, $markerSize, $markerSize, $markerColor);
        
        // Sauvegarde la couche du joueur en tant qu'image PNG
        $this->saveLayer($layer, 'layer.png', $this->playerId, $mapType);
        imagedestroy($layer);
    }

    private function calculateWorldPlayerLayerBounds() {
        // Get the plan bounds
        if ($this->currentPlan !== 'olympia') {
            $planBounds = $this->getBoundsFromPlan($this->worldPlan);
        } else {
            $planBounds = $this->getBoundsFromPlan($this->currentPlan);
        }
        if ($planBounds === null) {
            throw new \Exception("Plan bounds are not available.");
        }
    
        // Use existing scale factors from global map
        $scaleX = $this->scaleX;
        $scaleY = $this->scaleY;
    
        // Calculate required width and height based on player position
        $requiredWidth = abs($this->playerX - $planBounds['minX']) + abs($this->playerX - $planBounds['maxX']);
        $requiredHeight = abs($this->playerY - $planBounds['minY']) + abs($this->playerY - $planBounds['maxY']);

        return [
            'minX' => $planBounds['minX'],
            'maxX' => $planBounds['maxX'],
            'minY' => $planBounds['minY'],
            'maxY' => $planBounds['maxY'],
            'width' => (int)($requiredWidth * $scaleX),
            'height' => (int)($requiredHeight * $scaleY)
        ];
    }

    private function calculateLocalPlayerLayerBounds() {
        // Get the plan bounds
        $planData = $this->getPlanData($this->currentPlan);
        if ($planData && isset($planData->z_levels[$this->playerZ])) {
            $zLevel = $planData->z_levels[$this->playerZ];
            $this->localMinX = $zLevel->visibleBoundsMinX;
            $this->localMaxX = $zLevel->visibleBoundsMaxX;
            $this->localMinY = $zLevel->visibleBoundsMinY;
            $this->localMaxY = $zLevel->visibleBoundsMaxY;
        }
    
        // Use existing scale factors from local map
        $scaleX = $this->localScaleX;
        $scaleY = $this->localScaleY;
    
        // Calculate required width and height based on player position
        $requiredWidth = abs($this->playerX - $this->localMinX) + abs($this->playerX - $this->localMaxX);
        $requiredHeight = abs($this->playerY - $this->localMinY) + abs($this->playerY - $this->localMaxY);
    
        return [
            'minX' => $this->localMinX,
            'maxX' => $this->localMaxX,
            'minY' => $this->localMinY,
            'maxY' => $this->localMaxY,
            'width' => (int)($requiredWidth * $scaleX),
            'height' => (int)($requiredHeight * $scaleY)
        ];
    }

    private function saveLayer($layer, $fileName, $playerId = null, $mapType = null) {
        // Ensure the maps directory exists
        if (!file_exists('img/maps')) {
            mkdir('img/maps', 0777, true);
        }
        
        // Prefix the filename with the player's ID if provided
        if ($playerId !== null) {
            if ($mapType === "global") {
                $baseName = 'global_map';
            } else {
                $baseName = 'local_map';
            }
            $fileName = 'player_' . $playerId . '_' . $fileName;
        }
        
        // Save the layer as a PNG image
        $filePath = 'img/maps/' . $baseName . '_' . $fileName;
        imagepng($layer, $filePath);
        // imagedestroy($layer);
    }

    private function saveImage($mapType = null) {
        if (!file_exists('img/maps')) {
            mkdir('img/maps', 0777, true);
        }

        // Generate base filename based on map type
        if ($mapType === "global") {
            $baseName = 'global_map';
        } else {
            $formattedPlanName = str_replace(' ', '_', strtolower($this->currentPlan));
            $baseName = 'local_' . $formattedPlanName;
        }

        $layerKey = implode('-', array_keys($this->layers));
        $cachePath = 'img/maps/' . $baseName . '_' . md5($layerKey) . '.png';

        imagepng($this->image, $cachePath);
        imagedestroy($this->image);
        
        return $cachePath;
    }

    private function getBoundsFromPlan($planName) {
        $jsonHelper = new \json();
        $planData = $jsonHelper->decode('plans', $planName);

        if (!$planData) {
            return null;
        }

        // Check if the plan has visible bounds defined
        if (isset($planData->visibleBoundsMinX) && 
            isset($planData->visibleBoundsMaxX) && 
            isset($planData->visibleBoundsMinY) && 
            isset($planData->visibleBoundsMaxY)) {

            return [
                'minX' => (int)$planData->visibleBoundsMinX,
                'maxX' => (int)$planData->visibleBoundsMaxX,
                'minY' => (int)$planData->visibleBoundsMinY,
                'maxY' => (int)$planData->visibleBoundsMaxY
            ];
        }

        return null;
    }

    private function getPlanData($planName) {
        $jsonHelper = new \json();
        $planData = $jsonHelper->decode('plans', $planName);
        
        if (!$planData) {
            return null;
        }
        
        // Handle new structure with z-levels
        if (isset($planData->z_levels)) {
            $zLevels = [];
            foreach ($planData->z_levels as $zLevel) {
                $zLevels[$zLevel->z] = (object)[
                    'visibleBoundsMinX' => $zLevel->visibleBoundsMinX,
                    'visibleBoundsMaxX' => $zLevel->visibleBoundsMaxX,
                    'visibleBoundsMinY' => $zLevel->visibleBoundsMinY,
                    'visibleBoundsMaxY' => $zLevel->visibleBoundsMaxY
                ];
            }
            
            return (object)[
                'bg' => $planData->bg ?? null,
                'num_z_levels' => $planData->num_z_levels ?? count($zLevels),
                'z_levels' => $zLevels
            ];
        }
        
        // Fallback to old structure
        return (object)[
            'bg' => $planData->bg ?? null,
            'visibleBoundsMinX' => $planData->visibleBoundsMinX ?? null,
            'visibleBoundsMaxX' => $planData->visibleBoundsMaxX ?? null,
            'visibleBoundsMinY' => $planData->visibleBoundsMinY ?? null,
            'visibleBoundsMaxY' => $planData->visibleBoundsMaxY ?? null
        ];
    }

    private function getLocationFromPlan($planName) {
        $jsonHelper = new \json();
        $planData = $jsonHelper->decode('plans', $planName);
        
        if (!$planData) {
            return [];
        }
        
        return [[
            'name' => $planData->shortName,
            'x' => $planData->x,
            'y' => $planData->y,
            'plan' => $planName
        ]];
    }

    private function getAllLocationsFromPlans() {
        $jsonHelper = new \json();
        $plans = $jsonHelper->get_all('plans', true);
        $allLocations = [];

        foreach ($plans as $planName => $planData) {
            // Include if visibleByDefault is explicitly set to true
            if ($planData->visibleByDefault === true) {
                $allLocations = array_merge($allLocations, $this->getLocationFromPlan($planName));
            }
        }

        return $allLocations;
    }

    private function applyBackground($layer, $planData, $useImage = false) {
        if (isset($planData->bg)) {
            if ($useImage) {
                // Use the background image
                $bgImagePath = $planData->bg;
                if (file_exists($bgImagePath)) {
                    $bgImage = imagecreatefrompng($bgImagePath);
                    imagecopyresized(
                        $layer, $bgImage,
                        0, 0, 0, 0,
                        $this->localMapWidth, $this->localMapHeight,
                        imagesx($bgImage), imagesy($bgImage)
                    );
                    imagedestroy($bgImage);
                }
            } else {
                // Use the background color
                $bgKey = pathinfo($planData->bg, PATHINFO_FILENAME);
                $bgColor = $this->getColorForType($bgKey ?? 'default');
    
                if ($bgColor) {
                    imagefilledrectangle(
                        $layer, 
                        0, 0, 
                        $this->localMapWidth - 1, 
                        $this->localMapHeight - 1, 
                        $bgColor
                    );
                }
            }
        }
    }
}
