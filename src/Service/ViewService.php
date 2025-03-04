<?php

namespace App\Service;

class ViewService {
    private $width = 800;
    private $height = 532;
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
    private $imageMapCoords = [];
    private $playerX;
    private $playerY;
    private $terrainNames = [
        // Terrain naturel
        'desert_de_l_egeon' => 'Désert',
        'jungle_sauvage' => 'Jungle',
        'eryn_dolen' => 'Forêt Sombre',
        'foret_petrifiee' => 'Forêt Pétrifiée',
        'monts_de_l_oubli' => 'Monts de l\'Oubli',
        'cimes_geantes' => 'Cimes Géantes',
        'falaise' => 'Falaise',
        'lac_cenedril' => 'Forêt Sombre',
        'campement_redoraan' => 'Sable',
        'lac_thetis' => 'Glace',
        'lac_pegasus' => 'Lac Pégase',
        'archipel' => 'Archipel',
        
        // Routes et passages
        'route' => 'Chemin',
        'escalier_vers_le_bas' => 'Escalier',
        'escalier_vers_le_haut' => 'Escalier',
        'echelle' => 'Échelle',
        'carreaux' => 'Carreaux',
        
        // Donjons et grottes
        'caverne' => 'Caverne',
        'mines' => 'Mines',
        'faille_naine' => 'Faille Naine',
        'pit' => 'Gouffre',
        'enfers' => 'Enfers',
        
        // Par défaut
        'default' => 'Terrain Inconnu'
    ];
    
    private $raceService;

    public function __construct($db, $playerX = null, $playerY = null) {
        $this->db = $db;
        $this->playerX = $playerX;
        $this->playerY = $playerY;
        $this->raceService = new RaceService($db);
        $this->calculateBounds();
        $this->initializeColors();
    }
    
    private function initializeColors() {
        // Couleurs de base pour les terrains
        $this->colors = [
            // Terrain naturel
            'desert_de_l_egeon' => [238, 214, 175],
            'jungle_sauvage' => [121, 132, 151],
            'eryn_dolen' => [115, 153, 61],
            'foret_petrifiee' => [169, 169, 169],
            'monts_de_l_oubli' => [139, 137, 137],
            'cimes_geantes' => [105, 105, 105],
            'falaise' => [128, 128, 128],
            'lac_cenedril' => [79, 93, 81],
            'lac_thetis' => [206, 220, 231],
            'lac_pegasus' => [184, 134, 11],
            'archipel' => [0, 206, 209],

            // Éléments
            'boue' => [139, 69, 19],
            'eau' => [0, 105, 148],
            'lave' => [255, 69, 0],
            'ronce' => [34, 70, 34],

            // Routes et passages
            'route' => [50, 50, 50],
            'escalier_vers_le_bas' => [160, 82, 45],
            'escalier_vers_le_haut' => [160, 82, 45],
            'echelle' => [139, 69, 19],
            'carreaux' => [192, 192, 192],
            
            // Donjons et grottes
            'caverne' => [72, 61, 139],
            'mines' => [47, 79, 79],
            'faille_naine' => [25, 25, 112],
            'pit' => [0, 0, 0],
            'enfers' => [139, 0, 0],
            
            // Établissements et structures
            'havres' => [218, 165, 32],
            'fort_turok' => [139, 69, 19],
            'campement_redoraan' => [213, 194, 171],
            'praetorium' => [184, 134, 11],
            'manoir_tiroloin' => [139, 71, 38],
            'taverne_d_olympia' => [205, 149, 12],
            'barge_stellaire' => [70, 130, 180],
            'redora' => [205, 92, 92],
            'fefnir' => [178, 34, 34],
            
            // Lieux spéciaux
            'cimetiere_des_betes_sacrees' => [105, 105, 105],
            'arbre_sacre-02' => [85, 107, 47],
            'banniere_velue' => [192, 192, 192],
            
            // Runes
            'rune1' => [148, 0, 211],
            'rune3' => [148, 0, 211],
            'rune4' => [148, 0, 211],
            'rune10' => [148, 0, 211],
            'rune11' => [148, 0, 211],
            'rune16' => [148, 0, 211],
            
            // Zones de butin
            'loot' => [255, 215, 0],
            
            // Couleur par défaut pour les types inconnus
            'default' => [100, 100, 100]
        ];
    }
    
    private function calculateBounds() {
        // Calcule les limites en considérant à la fois map_tiles et map_elements
        $query = "SELECT 
                    MIN(x) as minX, 
                    MAX(x) as maxX, 
                    MIN(y) as minY, 
                    MAX(y) as maxY 
                 FROM (
                    SELECT c.x, c.y
                    FROM coords c
                    JOIN map_elements me ON c.id = me.coords_id
                    WHERE c.plan = 'olympia'
                    AND me.name NOT LIKE 'trace_pas_%'
                    UNION
                    SELECT c.x, c.y
                    FROM coords c
                    JOIN map_tiles mt ON c.id = mt.coords_id
                    WHERE c.plan = 'olympia'
                 ) as combined_coords";
        
        $result = $this->db->exe($query);
        $bounds = mysqli_fetch_assoc($result);
        
        $this->minX = $bounds['minX'];
        $this->maxX = $bounds['maxX'];
        $this->minY = $bounds['minY'];
        $this->maxY = $bounds['maxY'];
        
        // Calcule les facteurs d'échelle pour adapter les coordonnées à notre image avec des marges
        $this->scaleX = ($this->width - 2 * $this->margin) / ($this->maxX - $this->minX);
        $this->scaleY = ($this->height - 2 * $this->margin) / ($this->maxY - $this->minY);
    }
    
    private function transformX($x) {
        return (int)($this->margin + ($x - $this->minX) * $this->scaleX);
    }
    
    private function transformY($y) {
        // Inverse les coordonnées Y : négatif est sud, positif est nord
        return (int)($this->height - ($this->margin + ($y - $this->minY) * $this->scaleY));
    }
    
    private function getColorForType($name) {
        if (isset($this->colors[$name])) {
            $rgb = $this->colors[$name];
            return imagecolorallocate($this->image, $rgb[0], $rgb[1], $rgb[2]);
        }
        error_log("Utilisation de la couleur par défaut pour le type : " . $name);
        $rgb = $this->colors['default'];
        return imagecolorallocate($this->image, $rgb[0], $rgb[1], $rgb[2]);
    }
    
    private function createLayer() {
        $layer = imagecreatetruecolor($this->width, $this->height);
        // Active le canal alpha
        imagealphablending($layer, true);
        imagesavealpha($layer, true);
        // Remplit avec un fond transparent
        $transparent = imagecolorallocatealpha($layer, 0, 0, 0, 127);
        imagefill($layer, 0, 0, $transparent);
        return $layer;
    }

    public function generateGlobalMap($selectedLayers = ['tiles', 'elements']) {
        // Réinitialise les coordonnées de la carte image
        $this->imageMapCoords = [];
        
        // Crée l'image de base
        $this->image = $this->createLayer();
        
        // Génère les couches demandées dans un ordre spécifique
        if (in_array('tiles', $selectedLayers)) {
            $this->generateTileLayer();
        }
        if (in_array('elements', $selectedLayers)) {
            $this->generateElementLayer();
        }
        if (in_array('coordinates', $selectedLayers)) {
            $this->generateCoordinatesLayer();
        }
        if (in_array('routes', $selectedLayers)) {
            $this->generateRoutesLayer();
        }
        if (in_array('lieux', $selectedLayers)) {
            $this->generateLieuxLayer();
        }
        if (in_array('players', $selectedLayers)) {
            $this->generateAllPlayersLayer();
        }
        if (in_array('player', $selectedLayers) && $this->playerX !== null && $this->playerY !== null) {
            $this->generatePlayerLayer();
        }
        
        // Composite les couches sur l'image principale
        foreach ($this->layers as $layer) {
            imagecopy($this->image, $layer, 0, 0, 0, 0, $this->width, $this->height);
            imagedestroy($layer); // Libère la mémoire
        }
        
        // Ajoute une bordure à l'image finale
        $borderColor = imagecolorallocate($this->image, 139, 69, 19);
        imagerectangle($this->image, 0, 0, $this->width-1, $this->height-1, $borderColor);
        
        return [
            'imagePath' => $this->saveImage(),
            'imageMap' => $this->imageMapCoords
        ];
    }

    private function generateTileLayer() {
        $layer = $this->createLayer();
        
        $query = "SELECT mt.*, c.x, c.y
                 FROM map_tiles mt 
                 JOIN coords c ON c.id = mt.coords_id
                 WHERE c.plan = 'olympia'
                 ORDER BY mt.name";
        $result = $this->db->exe($query);
        
        while ($tile = mysqli_fetch_assoc($result)) {
            $x = $this->transformX($tile['x']);
            $y = $this->transformY($tile['y']);
            $color = $this->getColorForType($tile['name']);
            
            $tileSize = 6; // Taille de 6x6 pixels
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
            
            // Récupère le nom d'affichage pour l'infobulle
            $displayName = $this->terrainNames[$tile['name']] ?? $tile['name'];
            
            // Stocke les coordonnées pour la carte image
            $this->imageMapCoords[] = [
                'coords' => [$x1, $y1, $x2, $y2],
                'type' => 'tile',
                'name' => $displayName,
                'x' => $tile['x'],
                'y' => $tile['y']
            ];
        }
        
        $this->layers[] = $layer;
    }

    private function generateElementLayer() {
        $layer = $this->createLayer();
        
        $query = "SELECT me.*, c.x, c.y
                 FROM map_elements me 
                 JOIN coords c ON c.id = me.coords_id
                 WHERE c.plan = 'olympia'
                 AND me.name NOT LIKE 'trace_pas_%'
                 ORDER BY me.name";
        $result = $this->db->exe($query);
        
        while ($element = mysqli_fetch_assoc($result)) {
            $x = $this->transformX($element['x']);
            $y = $this->transformY($element['y']);
            $color = $this->getColorForType($element['name']);
            
            $elementSize = 6; // Taille de 6x6 pixels
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
            
            // Stocke les coordonnées pour la carte image
            $this->imageMapCoords[] = [
                'coords' => [$x1, $y1, $x2, $y2],
                'type' => 'element',
                'name' => $element['name'],
                'x' => $element['x'],
                'y' => $element['y']
            ];
        }
        
        $this->layers[] = $layer;
    }
    
    private function generateCoordinatesLayer() {
        $layer = $this->createLayer();
        
        // Récupère les coordonnées minimales et maximales
        $query = "SELECT MIN(c.x) as minX, MAX(c.x) as maxX, 
                        MIN(c.y) as minY, MAX(c.y) as maxY
                 FROM coords c
                 JOIN map_tiles mt ON c.id = mt.coords_id
                 WHERE c.plan = 'olympia'";
        $result = $this->db->exe($query);
        $bounds = mysqli_fetch_assoc($result);
        
        // Crée les couleurs
        $gridColor = imagecolorallocatealpha($layer, 255, 255, 255, 100);  // Blanc semi-transparent
        $textColor = imagecolorallocate($layer, 255, 255, 255);  // Blanc pour le texte
        $textBg = imagecolorallocate($layer, 0, 0, 0);  // Noir pour le fond du texte
        
        // Dessine les lignes verticales et les coordonnées X
        for ($x = $bounds['minX']; $x <= $bounds['maxX']; $x += 10) {  // Toutes les 10 unités
            $screenX = $this->transformX($x);
            
            // Dessine la ligne verticale
            imageline($layer, $screenX, 0, $screenX, $this->height, $gridColor);
            
            // Dessine le numéro de coordonnée en haut
            $text = (string)$x;
            imagefilledrectangle($layer, $screenX - 10, 2, $screenX + 10, 12, $textBg);
            imagestring($layer, 2, $screenX - strlen($text) * 2, 2, $text, $textColor);
        }
        
        // Dessine les lignes horizontales et les coordonnées Y
        for ($y = $bounds['minY']; $y <= $bounds['maxY']; $y += 10) {  // Toutes les 10 unités
            $screenY = $this->transformY($y);
            
            // Dessine la ligne horizontale
            imageline($layer, 0, $screenY, $this->width, $screenY, $gridColor);
            
            // Dessine le numéro de coordonnée sur le côté gauche
            $text = (string)$y;
            imagefilledrectangle($layer, 2, $screenY - 5, 20, $screenY + 5, $textBg);
            imagestring($layer, 2, 2, $screenY - 4, $text, $textColor);
        }
        
        $this->layers[] = $layer;
    }
    
    private function generateLieuxLayer() {
        $layer = $this->createLayer();
        
        // Requête les lieux uniques à partir de map_foregrounds, en prenant la première coordonnée pour chaque nom de base
        $sql = "SELECT 
            SUBSTRING_INDEX(SUBSTRING_INDEX(mf.name, 'unique_', -1), '_', 1) as base_name,
            c.x, c.y 
            FROM map_foregrounds mf
            JOIN coords c ON c.id = mf.coords_id
            WHERE mf.name LIKE 'unique_%'
            AND c.plan = 'olympia'
            GROUP BY base_name
            HAVING MIN(mf.coords_id)";  // Prend la première coordonnée pour chaque lieu unique
            
        $result = $this->db->exe($sql);
        $locations = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $locations[] = $row;
        }
        
        // Crée les couleurs pour les marqueurs
        $markerColor = imagecolorallocate($layer, 255, 215, 0);  // Or
        $textColor = imagecolorallocate($layer, 0, 0, 0);  // Noir pour le texte
        $textFillColor = imagecolorallocate($layer, 255, 255, 255);  // Blanc pour le fond du texte
        
        foreach ($locations as $location) {
            $x = (int)$this->transformX($location['x']);
            $y = (int)$this->transformY($location['y']);
            
            // Formate le nom (majuscule la première lettre)
            $name = ucfirst($location['base_name']);
            
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
            
            // Charge une police pour le texte
            $fontSize = 3;  // Taille 1-5, où 5 est la plus grande
            
            // Récupère les dimensions du texte
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
            
            // Ajoute à la carte image pour les infobulles
            $this->imageMapCoords[] = [
                'coords' => [$x - $size, $y - $size, $x + $size, $y + $size],
                'type' => 'lieu',
                'name' => $name,
                'x' => $location['x'],
                'y' => $location['y']
            ];
        }
        
        $this->layers[] = $layer;
    }
    
    private function generateRoutesLayer() {
        $layer = $this->createLayer();
        
        // Requête les routes à partir de map_routes
        $sql = "SELECT mr.*, c.x, c.y
                FROM map_routes mr
                JOIN coords c ON c.id = mr.coords_id
                WHERE c.plan = 'olympia'
                ORDER BY mr.name, mr.id";
                
        $result = $this->db->exe($sql);
        
        // Crée les couleurs pour les routes
        $routeColor = imagecolorallocate($layer, 139, 69, 19);  // Marron
        $routeOutline = imagecolorallocate($layer, 255, 255, 255);  // Blanc pour le contour
        
        while ($route = mysqli_fetch_assoc($result)) {
            $x = (int)$this->transformX($route['x']);
            $y = (int)$this->transformY($route['y']);
            
            // Dessine le point de route avec un contour blanc (plus petit)
            $size = 1;  // Réduit à 1
            imagefilledrectangle($layer, 
                $x - 1, $y - 1,
                $x + 1, $y + 1,
                $routeOutline
            );
            
            // Dessine le carré marron pour le point de route (plus petit)
            imagefilledrectangle($layer, 
                $x - $size, $y - $size,
                $x + $size, $y + $size,
                $routeColor
            );
            
            // Ajoute à la carte image pour les infobulles (un peu plus grand que la zone visible pour un survol plus facile)
            $this->imageMapCoords[] = [
                'coords' => [$x - 2, $y - 2, $x + 2, $y + 2],
                'type' => 'route',
                'name' => $route['name'] ?: 'Route',
                'x' => $route['x'],
                'y' => $route['y']
            ];
        }
        
        $this->layers[] = $layer;
    }
    
    private function generateAllPlayersLayer() {
        $layer = $this->createLayer();
        
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
            AND c.plan = 'olympia'
        ";
        
        $players = $this->db->exe($sql);

        // Dessine chaque joueur
        foreach ($players as $player) {
            // Ignore si les coordonnées sont invalides
            if (!isset($player['x']) || !isset($player['y'])) {
                continue;
            }

            // Récupère les coordonnées
            $x = $this->transformX($player['x']);
            $y = $this->transformY($player['y']);

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
            
            // Formate la date de dernière connexion
            $lastLoginStr = $player['lastLoginTime'] ? date('Y-m-d', $player['lastLoginTime']) : 'Jamais';
            
            // Ajoute à la carte image pour les infobulles (un peu plus grand que le carré visible pour un survol plus facile)
            $this->imageMapCoords[] = [
                'coords' => [$x - 2, $y - 2, $x + 2, $y + 2],
                'type' => 'player',
                'name' => $player['player_name'] . ' (' . $player['race'] . ') - Dernière connexion: ' . $lastLoginStr,
                'race' => $player['race'],
                'x' => $player['x'],
                'y' => $player['y']
            ];
        }
        
        $this->layers[] = $layer;
    }
    
    private function generatePlayerLayer() {
        $layer = $this->createLayer();
        
        $x = (int)$this->transformX($this->playerX);
        $y = (int)$this->transformY($this->playerY);
        
        // Crée les couleurs pour le marqueur de joueur
        $markerColor = imagecolorallocate($layer, 255, 0, 0);  // Rouge
        $pulseColor = imagecolorallocatealpha($layer, 255, 0, 0, 80);  // Rouge semi-transparent
        
        // Dessine le cercle de pulsation extérieur
        $pulseSize = 8;
        imagefilledellipse($layer, $x, $y, $pulseSize * 2, $pulseSize * 2, $pulseColor);
        
        // Dessine le marqueur de position du joueur (cercle plein)
        $markerSize = 4;
        imagefilledellipse($layer, $x, $y, $markerSize, $markerSize, $markerColor);
        
        // Ajoute à la carte image pour l'infobulle
        $this->imageMapCoords[] = [
            'coords' => [$x - $pulseSize, $y - $pulseSize, $x + $pulseSize, $y + $pulseSize],
            'type' => 'player',
            'name' => 'Position actuelle',
            'x' => $this->playerX,
            'y' => $this->playerY
        ];
        
        $this->layers[] = $layer;
    }
    
    private function saveImage() {
        $cacheDir = __DIR__ . '/../../cache/maps';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }
        
        // Génère un nom de fichier unique basé sur les couches sélectionnées
        $layerKey = implode('-', array_keys($this->layers));
        $cachePath = $cacheDir . '/global_map_' . md5($layerKey) . '.png';
        imagepng($this->image, $cachePath);
        imagedestroy($this->image);
        
        return $cachePath;
    }
}
