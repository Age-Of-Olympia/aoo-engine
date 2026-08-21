<?php

namespace App\Service;

use DateTime;
use DateTimeZone;
use Exception;

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
    private $localBoundsAvailable = false;

    public function __construct($db, $playerX = null, $playerY = null, $playerZ = null, $playerId = null, $plan = 'olympia') {
        $this->db = $db;
        $this->playerX = $playerX;
        $this->playerY = $playerY;
        $this->playerZ = $playerZ;
        $this->playerId = $playerId;
        $this->currentPlan = $plan;
        $this->raceService = new RaceService();
        $this->calculateBounds();
        $this->colors = ColorService::palette();
    }

    private function calculateBounds() {
        try {
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
                if (!$planData) {
                    error_log("Map bounds: plan data not found for: " . $this->currentPlan);
                    return; // Pas de carte locale disponible, mais la carte monde reste fonctionnelle
                }
                if (!isset($planData->z_levels[$this->playerZ])) {
                    error_log("Map bounds: z-level {$this->playerZ} not found in plan: " . $this->currentPlan . " (carte locale non configurée pour ce niveau)");
                    return; // Ce niveau Z n'a pas de carte configurée, la carte monde reste fonctionnelle
                }

                $zLevel = $planData->z_levels[$this->playerZ];

                if (!empty($zLevel->MapUnavailable)) {
                    error_log("Map bounds: z-level {$this->playerZ} explicitement sans carte (MapUnavailable) dans le plan: " . $this->currentPlan);
                    return; // Carte volontairement absente pour ce niveau
                }

                $this->localMinX = $zLevel->visibleBoundsMinX;
                $this->localMaxX = $zLevel->visibleBoundsMaxX;
                $this->localMinY = $zLevel->visibleBoundsMinY;
                $this->localMaxY = $zLevel->visibleBoundsMaxY;

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

                $this->localBoundsAvailable = true;
            }
        } catch (Exception $e) {
            error_log("Map bounds error: " . $e->getMessage());
            throw new Exception("Erreur dans la génération de la map, merci de faire remonter le bug à l'équipe de dev");
        }
    }

    public function isLocalMapAvailable(): bool
    {
        return $this->localBoundsAvailable;
    }

    public function isWorldPlan(): bool
    {
        return $this->currentPlan === $this->worldPlan;
    }

    /**
     * Position du joueur en pourcentages de l'image de carte (monde ou
     * locale selon le plan courant) — permet de poser un marqueur CSS
     * par-dessus les couches PNG sans générer de couche GD à chaque
     * affichage (cf. MinimapView du HUD).
     *
     * @return array{x: float, y: float}|null null si aucune carte n'est
     *                                        configurée pour ce plan/niveau
     */
    public function getPositionPercent(): ?array
    {
        if ($this->playerX === null || $this->playerY === null) {
            return null;
        }

        if ($this->isWorldPlan()) {
            if (empty($this->scaleX) || empty($this->scaleY)) {
                return null;
            }
            $px = $this->transformX($this->playerX, 'global');
            $py = $this->transformY($this->playerY, 'global');
            $w = $this->width;
            $h = $this->height;
        } else {
            if (!$this->localBoundsAvailable || empty($this->localMapWidth) || empty($this->localMapHeight)) {
                return null;
            }
            $px = $this->transformX($this->playerX, 'local');
            $py = $this->transformY($this->playerY, 'local');
            $w = $this->localMapWidth;
            $h = $this->localMapHeight;
        }

        return [
            'x' => max(0.0, min(100.0, $px / $w * 100)),
            'y' => max(0.0, min(100.0, $py / $h * 100)),
        ];
    }
    
    /**
     * LES personnages visibles sur une carte, et leur couleur — source
     * unique des règles de visibilité.
     *
     * Elles vivaient en double, recopiées dans le générateur du calque
     * GD de chaque carte. Elles n'ont pourtant rien d'anodin : ce sont
     * elles qui décident si un joueur voit un adversaire, et sous quelle
     * identité. Deux copies, c'est la certitude qu'elles divergeront —
     * et elles avaient divergé, chaque carte oubliant une règle, et
     * chacune une règle différente : l'une ignorait le mode discret,
     * l'autre l'invisibilité et les déguisements, une seule excluait le
     * lecteur.
     *
     * Elles sont désormais ALIGNÉES. Ce qui vaut pour les deux cartes :
     * personnages et PNJ seulement, jamais les discrets (incognitoMode),
     * jamais les invisibles, jamais le lecteur — et la colonne `visible`
     * fait office de race d'emprunt, un déguisement n'ayant pas de raison
     * de tenir à l'échelle du monde et de tomber à l'échelle locale.
     *
     * La SEULE différence qui subsiste est délibérée : la carte du monde
     * noircit au-delà de DIST_MAP_MAX. Elle couvre des étendues où l'on
     * ne reconnaît pas un individu à vue ; la carte locale, elle, tient
     * dans la portée de perception.
     *
     * @param string $scope 'local' ou 'global'
     * @return array<int, array{x: int, y: int, color: string, known: bool}>
     *         x/y en PIXELS de la carte concernée
     */
    private function visiblePlayers(string $scope): array
    {
        $isGlobal = $scope === 'global';

        if ($isGlobal) {
            if (empty($this->scaleX) || empty($this->scaleY)) {
                return [];
            }
            $default = '#000000';
            $plan = $this->worldPlan;
            /* Carte du monde : le sol uniquement. */
            $zCondition = 'AND c.z = 0';
        } else {
            if (!$this->localBoundsAvailable) {
                return [];
            }
            $default = '#ffffff';
            $plan = $this->currentPlan;
            $zCondition = $this->currentPlan === $this->worldPlan
                ? 'AND c.z = 0'
                : ($this->playerZ !== null ? 'AND c.z = ' . (int) $this->playerZ : '');
        }

        /* Le tronc COMMUN des deux cartes : personnages et PNJ, jamais
         * les discrets, jamais les invisibles, jamais le lecteur. Ce
         * n'était pas le cas — chaque carte en oubliait une partie, et
         * chacune une partie différente. */
        $sql = "
            SELECT c.x, c.y, p.race, p.visible
            FROM players p
            JOIN coords c ON c.id = p.coords_id
            LEFT JOIN players_options po ON po.player_id = p.id AND po.name = 'incognitoMode'
            WHERE c.x IS NOT NULL AND c.y IS NOT NULL
              AND po.player_id IS NULL
              AND p.player_type IN ('real', 'npc')
              AND p.id != " . (int) $this->playerId . "
              AND (p.visible IS NULL OR p.visible != 'invisible')
              AND c.plan = '" . $plan . "'
              {$zCondition}
        ";

        $raceColors = array_merge(['default' => $default], $this->raceService->getBgColorMap());

        $out = [];

        foreach ($this->db->exe($sql) as $row) {
            if (!isset($row['x'], $row['y'])) {
                continue;
            }

            /* `visible` sert aussi de race d'emprunt : un personnage peut
             * se montrer sous une autre apparence. Vrai sur les DEUX
             * cartes désormais — il n'y a pas de raison qu'un déguisement
             * tienne à l'échelle du monde et tombe à l'échelle locale. */
            $race = $row['visible'] !== null ? $row['visible'] : $row['race'];

            /* Anonymisation à distance : propre à la carte du monde, qui
             * couvre des étendues où l'on ne reconnaît pas un individu à
             * vue. La carte locale tient dans la portée de perception. */
            $known = !$isGlobal
                || ($this->getPlayersDistance($this->playerX, $this->playerY, $row['x'], $row['y']) <= DIST_MAP_MAX
                    && $this->playerZ == 0
                    && $this->currentPlan == $this->worldPlan);

            $out[] = [
                'x' => $this->transformX($row['x'], $scope),
                'y' => $this->transformY($row['y'], $scope),
                'color' => $known ? ($raceColors[$race] ?? $raceColors['default']) : $raceColors['default'],
                'known' => $known,
            ];
        }

        return $out;
    }

    /**
     * Les mêmes personnages, en POURCENTAGES de l'image — de quoi poser
     * des repères CSS par-dessus les couches PNG sans rendu GD, ce que
     * fait la minimap du HUD (elle exclut volontairement les calques
     * joueurs GD, trop coûteux à produire à chaque affichage).
     *
     * Même source de règles que le calque : c'est tout l'intérêt.
     *
     * @return array<int, array{x: float, y: float, color: string, known: bool}>
     */
    public function getVisiblePlayersPercent(): array
    {
        if ($this->playerX === null || $this->playerY === null) {
            return [];
        }

        if ($this->isWorldPlan()) {
            $scope = 'global';
            $w = $this->width;
            $h = $this->height;
        } else {
            $scope = 'local';
            $w = $this->localMapWidth;
            $h = $this->localMapHeight;
        }

        if (empty($w) || empty($h)) {
            return [];
        }

        return array_map(
            static fn(array $one): array => [
                'x' => max(0.0, min(100.0, $one['x'] / $w * 100)),
                'y' => max(0.0, min(100.0, $one['y'] / $h * 100)),
                'color' => $one['color'],
                'known' => $one['known'],
            ],
            $this->visiblePlayers($scope)
        );
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
    
    private array $tileColorCache = [];

    private function getColorForType($name) {
        // Résout aussi les tuiles de transition générées (trans_A_B_code)
        // en mélangeant les couleurs des deux biomes ; mémoïsé — les tuiles
        // arrivent triées par nom, chaque nom se recalculerait des
        // centaines de fois sinon
        $rgb = $this->tileColorCache[$name] ??= ColorService::colorFor($name, $this->colors);
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

    public function generateLocalMap(?array $selectedLayers = null) {
        // No configured bounds (plan JSON without z_levels — tutorial
        // instances among others): nothing can be drawn, dimensions are
        // null and GD would reject them.
        if (!$this->localBoundsAvailable) {
            return [];
        }

        $selectedLayers = $selectedLayers ?? ['tiles', 'elements', 'foregrounds', 'resources', 'routes', 'buildings'];

        // Crée l'image de base
        $this->image = $this->createLayer($this->localMapWidth, $this->localMapHeight);
        $timestamp = (new DateTime('now', new DateTimeZone('UTC')))->format('Ymd-His');
        $outputDir = $_SERVER['DOCUMENT_ROOT'].'/img/maps/local/';

        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

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

        $results = [];

        foreach ($selectedLayers as $layerName) {
            switch ($layerName) {
                case 'tiles':
                    $this->generateTileLayer($this->currentPlan);
                    break;
                case 'elements':
                    $this->generateElementLayer($this->currentPlan);
                    break;
                case 'foregrounds':
                    $this->generateForegroundsLayer($this->currentPlan);
                    break;
                case 'resources':
                    $this->generateResourceLayer($this->currentPlan);
                    break;
                case 'routes':
                    $this->generateRoutesLayer($this->currentPlan);
                     break;
                case 'buildings':
                    $this->generateBuildingsLayer($this->currentPlan);
                    break;
                case 'players':
                    $this->generateLocalPlayersLayer();
                    break;
                case 'player':
                    $this->generateLocalPlayerLayer();
                    break;
            }
            
            $filename = "local_{$this->currentPlan}_{$this->playerZ}_{$layerName}_{$timestamp}.png";
            $filepath = $outputDir . $filename;
            
            if ($layerName === 'tiles') {
                imagecopy($this->image, $this->layers[$layerName], 0, 0, 0, 0, $this->localMapWidth, $this->localMapHeight);
                imagepng($this->image, $filepath);
            } else {
                $this->image = $this->createLayer($this->localMapWidth, $this->localMapHeight);
                imagecopy($this->image, $this->layers[$layerName], 0, 0, 0, 0, $this->localMapWidth, $this->localMapHeight);
                imagepng($this->image, $filepath);
            }
            imagedestroy($this->layers[$layerName]);
            
            $results[$layerName] = [
                'imagePath' => "/img/maps/local/{$filename}",
                'timestamp' => $timestamp
            ];
        }   
        
        return $results;
    }

    public function generateGlobalMap(?array $selectedLayers = null) {
        $this->image = $this->createLayer();
        $selectedLayers = $selectedLayers ?? ['tiles', 'elements', 'coordinates', 'locations', 'routes', 'buildings', 'players', 'player'];
        $timestamp = (new DateTime('now', new DateTimeZone('UTC')))->format('Ymd-His');
        $outputDir = $_SERVER['DOCUMENT_ROOT'].'/img/maps/world/';

        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        $results = [];
        
        foreach ($selectedLayers as $layerName) {            
            switch ($layerName) {
                case 'tiles':
                    $this->generateTileLayer($this->worldPlan);
                    break;
                case 'elements':
                    $this->generateElementLayer($this->worldPlan);
                    break;
                case 'coordinates':
                    $this->generateCoordinatesLayer($this->worldPlan);
                    break;
                case 'locations':
                    $this->generateLocationsLayer($this->worldPlan);
                    break;
                case 'routes':
                    $this->generateRoutesLayer($this->worldPlan);
                    break;
                case 'buildings':
                    $this->generateBuildingsLayer($this->worldPlan);
                    break;
                case 'players':
                    if ($this->playerX !== null && $this->playerY !== null) {
                        $this->generateWorldPlayersLayer();
                    }
                    break;
                case 'player':
                    if ($this->playerX !== null && $this->playerY !== null) {
                        $this->generateWorldPlayerLayer();
                    }
                    break;
            }

            $filename = "world_{$layerName}_{$timestamp}.png";
            $filepath = $outputDir . $filename;

            imagepng($this->layers[$layerName], $filepath);
            imagedestroy($this->layers[$layerName]);

            $results[$layerName] = [
                'imagePath' => "/img/maps/world/{$filename}",
                'timestamp' => $timestamp
            ];
        }

        return $results;
    }

    private function generateForegroundsLayer($plan) { 
        $layer = $this->createLayer();
        $zCondition = $plan === $this->worldPlan 
            ? "AND c.z = 0" 
            : ($this->playerZ !== null ? "AND c.z = " . $this->playerZ : "");
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
        
        $this->layers['foregrounds'] = $layer;
    }   

    private function generateTileLayer($plan) {
        $layer = $this->createLayer();
        $zCondition = $plan === $this->worldPlan 
            ? "AND c.z = 0" 
            : ($this->playerZ !== null ? "AND c.z = " . $this->playerZ : "");
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
        
        $this->layers['tiles'] = $layer;
    }

    private function generateElementLayer($plan) {
        $layer = $this->createLayer();
        $zCondition = $plan === $this->worldPlan 
            ? "AND c.z = 0" 
            : ($this->playerZ !== null ? "AND c.z = " . $this->playerZ : "");
        $mapType = $plan === $this->worldPlan ? "global" : "local";

        // Set transparency for cave elements
        $alpha = ($this->playerZ < 0) ? 80 : 0; // 80 = ~30% transparency

        $query = "SELECT me.*, c.x, c.y
            FROM map_elements me 
            JOIN coords c ON c.id = me.coords_id
            WHERE c.plan = '" . $plan . "'
            AND me.name NOT LIKE 'trace_pas_%'
            AND me.name != 'flag_red'
            AND me.name != 'sang'
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
        
        $this->layers['elements'] = $layer;
    }
    
    private function generateCoordinatesLayer($plan) {
        $layer = $this->createLayer();
        $zCondition = $plan === $this->worldPlan 
            ? "AND c.z = 0" 
            : ($this->playerZ !== null ? "AND c.z = " . $this->playerZ : "");
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
        
        $this->layers['coordinates'] = $layer;
    }
    
    private function generateLocationsLayer($plan) {
        $layer = $this->createLayer();
        $zCondition = $plan === $this->worldPlan 
            ? "AND c.z = 0" 
            : ($this->playerZ !== null ? "AND c.z = " . $this->playerZ : "");
        $mapType = $plan === $this->worldPlan ? "global" : "local";

       // Create colors for markers
        $markerColor = imagecolorallocate($layer, 255, 215, 0);  // Or
        $textColor = imagecolorallocate($layer, 0, 0, 0);  // Noir pour le texte
        $textFillColor = imagecolorallocate($layer, 255, 255, 255);  // Blanc pour le fond du texte
        
        $locations = $this->getAllLocationsFromPlans();
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
        
        $this->layers['locations'] = $layer;
    }
    
    private function generateRoutesLayer($plan) {
        $layer = $this->createLayer();
        $zCondition = $plan === $this->worldPlan 
            ? "AND c.z = 0" 
            : ($this->playerZ !== null ? "AND c.z = " . $this->playerZ : "");
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
        
        $this->layers['routes'] = $layer;
    }

    private function generateResourceLayer($plan) {
        $layer = $this->createLayer();
        $zCondition = $plan === $this->worldPlan
            ? "AND c.z = 0"
            : ($this->playerZ !== null ? "AND c.z = " . (int) $this->playerZ : "");
        $mapType = $plan === $this->worldPlan ? "global" : "local";

        /* Toute la couche, et non « ce dont le nom contient mur ou arbre ».
         *
         * Ce filtre datait du temps où la table portait aussi les murs. La
         * moitié « arbre » laissait de côté la pierre, l'herbe, la jungle, la
         * pierre noire, les rochers, les cocotiers et tous les minerais, soit
         * près des trois quarts des ressources posées.
         *
         * Lue par case et non par ancrage : une ressource tient une case, et
         * c'est cette case que la carte doit teinter.
         *
         * Les types absents de tile_colors retombent sur la couleur par
         * défaut (ColorService::colorFor), donc aucun n'est perdu. */
        $sql = "SELECT p.id, p.race AS name, c.x, c.y
            FROM players p
            JOIN entity_cells ec ON ec.player_id = p.id
            JOIN coords c ON c.id = ec.coords_id
            WHERE p.player_type = 'resource'
            AND c.plan = ?
            AND c.x BETWEEN " . (int) $this->minX . " AND " . (int) $this->maxX . "
            AND c.y BETWEEN " . (int) $this->minY . " AND " . (int) $this->maxY . "
            $zCondition
            ORDER BY p.race, p.id";

        $result = $this->db->exe($sql, array($plan));
        
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
        
        $this->layers['resources'] = $layer;
    }

    /**
     * Couche BÂTIMENTS des cartes : les entités structure (bâtiments,
     * objets uniques) en carrés aux couleurs de leur pseudo-race —
     * couche à part, masquable par l'option hideBuildingsLayer (calques
     * du HUD). Décision du 2026-07-19 : les bâtiments n'appartiennent
     * pas à la couche joueurs, mais méritent leur calque, local ET monde.
     */
    private function generateBuildingsLayer($plan) {
        $layer = $this->createLayer();
        $zCondition = $plan === $this->worldPlan
            ? "AND c.z = 0"
            : ($this->playerZ !== null ? "AND c.z = " . $this->playerZ : "");
        $mapType = $plan === $this->worldPlan ? "global" : "local";

        $raceColors = array_merge(
            ['default' => '#8a8a8a'],
            $this->raceService->getBgColorMap()
        );

        // Bornes du BON référentiel (les couches murs/joueurs lisent les
        // bornes monde même en local — fragile quand le plan monde n'est
        // pas configuré) ; sans bornes, couche vide plutôt qu'un SQL cassé.
        $minX = $mapType === 'global' ? $this->minX : $this->localMinX;
        $maxX = $mapType === 'global' ? $this->maxX : $this->localMaxX;
        $minY = $mapType === 'global' ? $this->minY : $this->localMinY;
        $maxY = $mapType === 'global' ? $this->maxY : $this->localMaxY;
        if (!is_numeric($minX) || !is_numeric($maxX) || !is_numeric($minY) || !is_numeric($maxY)) {
            $this->layers['buildings'] = $layer;
            return;
        }

        $sql = "SELECT c.x, c.y, p.race
            FROM players p
            JOIN coords c ON c.id = p.coords_id
            WHERE c.plan = '" . $plan . "'
            AND p.player_type = 'building'
            AND c.x BETWEEN " . $minX . " AND " . $maxX . "
            AND c.y BETWEEN " . $minY . " AND " . $maxY . "
            $zCondition";

        $result = $this->db->exe($sql);

        while ($building = mysqli_fetch_assoc($result)) {
            $x = $this->transformX($building['x'], $mapType);
            $y = $this->transformY($building['y'], $mapType);

            $raceColor = $raceColors[$building['race']] ?? $raceColors['default'];
            list($r, $g, $b) = sscanf($raceColor, "#%02x%02x%02x");
            $color = imagecolorallocate($layer, $r, $g, $b);

            $tileSize = ($mapType === "global") ? 6 : $this->localScaleX;

            imagefilledrectangle(
                $layer,
                (int)($x - ($tileSize/2)), (int)($y - ($tileSize/2)),
                (int)($x + ($tileSize/2)), (int)($y + ($tileSize/2)),
                $color
            );
        }

        $this->layers['buildings'] = $layer;
    }

    public function generateLocalPlayersLayer() {
        if (!$this->localBoundsAvailable) {
            return null; // Pas de carte configurée pour ce niveau Z
        }
        $layer = $this->createLayer($this->localMapWidth, $this->localMapHeight);
        $mapType = "local";

        /* Qui est visible, et de quelle couleur : visiblePlayers().
         * Cette méthode ne fait plus que DESSINER. */
        foreach ($this->visiblePlayers($mapType) as $one) {

            list($r, $g, $b) = sscanf($one['color'], "#%02x%02x%02x");
            $markerColor = imagecolorallocate($layer, $r, $g, $b);
            $pulseColor = imagecolorallocatealpha($layer, $r, $g, $b, 80);

            $pulseSize = 6;
            imagefilledellipse($layer, $one['x'], $one['y'], $pulseSize * 2, $pulseSize * 2, $pulseColor);

            $markerSize = 6;
            imagefilledellipse($layer, $one['x'], $one['y'], $markerSize, $markerSize, $markerColor);
        }

        $filePath = $this->saveLayer($layer, 'players_layer.png', $this->playerId, $mapType);
        imagedestroy($layer);

        return $filePath;
    }

    public function generateWorldPlayersLayer() {
        $layer = $this->createLayer();
        $mapType = "global";

        /* Qui est visible, sous quelle identité, et noirci ou non au-delà
         * de la portée de reconnaissance : visiblePlayers(). Cette
         * méthode ne fait plus que DESSINER. */
        foreach ($this->visiblePlayers($mapType) as $one) {

            list($r, $g, $b) = sscanf($one['color'], "#%02x%02x%02x");
            $playerColor = imagecolorallocate($layer, $r, $g, $b);

            // Dessine un carré 2x2 pour le joueur
            $size = 1;
            imagefilledrectangle($layer,
                $one['x'] - $size, $one['y'] - $size,
                $one['x'] + $size, $one['y'] + $size,
                $playerColor
            );
        }

        $filePath = $this->saveLayer($layer, 'players_layer.png', $this->playerId, $mapType);
        imagedestroy($layer);

        return $filePath;
    }

    public function generateLocalPlayerLayer() {
        if (!$this->localBoundsAvailable) {
            return null; // Pas de carte configurée pour ce niveau Z
        }
        $bounds = $this->calculateLocalPlayerLayerBounds();
        $mapType = "local";

        // Create a layer with adjusted size
        $layer = $this->createLayer($bounds['width'], $bounds['height']);
        $x = (int)$this->transformX($this->playerX, $mapType);
        $y = (int)$this->transformY($this->playerY, $mapType);

        // Crée les couleurs pour le marqueur de joueur
        $markerColor = imagecolorallocate($layer, 255, 0, 255);  // Magenta
        $pulseColor = imagecolorallocatealpha($layer, 255, 0, 255, 80);  // Magenta semi-transparent
        
        // Dessine le cercle de pulsation extérieur
        $pulseSize = 6;
        imagefilledellipse($layer, $x, $y, $pulseSize * 2, $pulseSize * 2, $pulseColor);
        
        // Dessine le marqueur de position du joueur (cercle plein)
        $markerSize = 6;
        imagefilledellipse($layer, $x, $y, $markerSize, $markerSize, $markerColor);
        
        // Sauvegarde la couche du joueur en tant qu'image PNG
        $filePath = $this->saveLayer($layer, 'layer.png', $this->playerId, $mapType);
        imagedestroy($layer);
        return $filePath;
    }

    public function generateWorldPlayerLayer() {
        // Calculate bounds to include the player's position
        $bounds = $this->calculateWorldPlayerLayerBounds();
        $zCondition = ($this->currentPlan !== $this->worldPlan && $this->playerZ !== null) ? "AND c.z = " . $this->playerZ : "";
        $mapType = "global";

        // Create a layer with adjusted size
        $layer = $this->createLayer($bounds['width'], $bounds['height']);
        
        // Position du marqueur : l'emplacement du plan courant sur la carte
        // monde, sinon la position du joueur — et (0,0) assumé quand le plan
        // n'a pas d'emplacement déclaré (le marqueur sort simplement du cadre,
        // avant c'était une variable indéfinie).
        $x = 0;
        $y = 0;
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
        $markerColor = imagecolorallocate($layer, 255, 0, 255);  // Magenta
        $pulseColor = imagecolorallocatealpha($layer, 255, 0, 255, 80);  // Magenta semi-transparent
        
        // Dessine le cercle de pulsation extérieur
        $pulseSize = 8;
        imagefilledellipse($layer, $x, $y, $pulseSize * 2, $pulseSize * 2, $pulseColor);
        
        // Dessine le marqueur de position du joueur (cercle plein)
        $markerSize = 4;
        imagefilledellipse($layer, $x, $y, $markerSize, $markerSize, $markerColor);
        
        // Sauvegarde la couche du joueur en tant qu'image PNG
        $filePath = $this->saveLayer($layer, 'layer.png', $this->playerId, $mapType); // Capture path
        imagedestroy($layer);
        return $filePath; // Return path
    }

    private function calculateWorldPlayerLayerBounds() {
        // Get the plan bounds
        if ($this->currentPlan !== 'olympia') {
            $planBounds = $this->getBoundsFromPlan($this->worldPlan);
        } else {
            $planBounds = $this->getBoundsFromPlan($this->currentPlan);
        }
        if ($planBounds === null) {
            throw new Exception("Plan bounds are not available.");
        }
    
        // The player layer must have the same dimensions as the other global map layers
        // (like generateWorldPlayersLayer does) to ensure coordinate transformations work correctly
        // since transformY() uses $this->height for calculations
        return [
            'minX' => $planBounds['minX'],
            'maxX' => $planBounds['maxX'],
            'minY' => $planBounds['minY'],
            'maxY' => $planBounds['maxY'],
            'width' => $this->width,
            'height' => $this->height
        ];
    }

    private function calculateLocalPlayerLayerBounds() {
        // Get the plan bounds
        $planData = $this->getPlanData($this->currentPlan);
        if ($planData && isset($planData->z_levels[$this->playerZ])) {
            $zLevel = $planData->z_levels[$this->playerZ];
            if (empty($zLevel->MapUnavailable)) {
                $this->localMinX = $zLevel->visibleBoundsMinX;
                $this->localMaxX = $zLevel->visibleBoundsMaxX;
                $this->localMinY = $zLevel->visibleBoundsMinY;
                $this->localMaxY = $zLevel->visibleBoundsMaxY;
            }
        }
    
        // The player layer must have the same dimensions as the other local map layers
        // (like generateLocalPlayersLayer does) to ensure coordinate transformations work correctly
        // since transformY() uses $this->localMapHeight for calculations
        return [
            'minX' => $this->localMinX,
            'maxX' => $this->localMaxX,
            'minY' => $this->localMinY,
            'maxY' => $this->localMaxY,
            'width' => $this->localMapWidth,
            'height' => $this->localMapHeight
        ];
    }

    /**
     * Jeton opaque et stable identifiant les couches d'UN joueur.
     *
     * Ces couches sont personnelles : le calque « player » porte sa
     * position, le calque « players » porte qui il est capable
     * d'identifier — donc sa portée de perception et son voisinage. Les
     * nommer « player_{id} » les rendait ÉNUMÉRABLES : servies en
     * statique depuis la racine web, n'importe qui pouvait incrémenter
     * le numéro et lire la vue d'un adversaire, sans même être connecté.
     *
     * La clé vit dans un fichier gitignoré, propre à l'installation ;
     * un jeton ne se remonte donc pas à l'identifiant. Repli sur le mot
     * de passe de base — gitignoré lui aussi — pour qu'une installation
     * sans secret Tiled ne retombe pas silencieusement sur l'ancien
     * nommage devinable.
     */
    public static function playerLayerToken(int $playerId): string
    {
        /* constant() plutôt que l'accès direct : ces deux constantes
         * vivent dans des fichiers gitignorés, absents de l'analyse
         * statique comme du dépôt. */
        if (defined('TILED_HMAC_SECRET') && constant('TILED_HMAC_SECRET') !== '') {
            $key = (string) constant('TILED_HMAC_SECRET');
        } elseif (defined('DB_CONSTANTS')) {
            $dbConstants = constant('DB_CONSTANTS');
            $key = (string) ($dbConstants['psw'] ?? 'aoo-map-layer');
        } else {
            $key = 'aoo-map-layer';
        }

        return substr(hash_hmac('sha256', 'map-layer:' . $playerId, $key), 0, 32);
    }

    /** Chemin web d'une couche personnelle — source unique, écriture et lecture. */
    public static function playerLayerPath(string $mapType, int $playerId, string $fileName): string
    {
        $baseName = $mapType === 'global' ? 'global_map' : 'local_map';

        return '/img/maps/' . $baseName . '_p' . self::playerLayerToken($playerId) . '_' . $fileName;
    }

    private function saveLayer($layer, $fileName, $playerId = null, $mapType = null) {
        // Ensure the maps directory exists
        if (!file_exists('img/maps')) {
            mkdir('img/maps', 0777, true);
        }

        $baseName = $mapType === "global" ? 'global_map' : 'local_map';

        if ($playerId !== null) {
            $filePath = ltrim(self::playerLayerPath((string) $mapType, (int) $playerId, $fileName), '/');

            /* L'ancien fichier au nom devinable reste lisible tant qu'il
             * est sur le disque : on l'efface en écrivant le nouveau,
             * pour que la correction se propage d'elle-même au fil des
             * régénérations plutôt que d'attendre un nettoyage manuel. */
            $legacy = 'img/maps/' . $baseName . '_player_' . $playerId . '_' . $fileName;
            if (is_file($legacy)) {
                @unlink($legacy);
            }
        } else {
            $filePath = 'img/maps/' . $baseName . '_' . $fileName;
        }

        // Save the layer as a PNG image
        imagepng($layer, $filePath);

        return $filePath;
    }


    private function getBoundsFromPlan($planName) {
        $planData = plans()->read($planName);

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
        $planData = plans()->read($planName);
        
        if (!$planData) {
            return null;
        }
        
        // Handle new structure with z-levels
        if (isset($planData->z_levels)) {
            $zLevels = [];
            foreach ($planData->z_levels as $zLevel) {
                $entry = ['name' => $zLevel->{'z-name'} ?? "Niveau " . $zLevel->z];

                if (!empty($zLevel->MapUnavailable)) {
                    $entry['MapUnavailable'] = true;
                } else {
                    $entry['visibleBoundsMinX'] = $zLevel->visibleBoundsMinX;
                    $entry['visibleBoundsMaxX'] = $zLevel->visibleBoundsMaxX;
                    $entry['visibleBoundsMinY'] = $zLevel->visibleBoundsMinY;
                    $entry['visibleBoundsMaxY'] = $zLevel->visibleBoundsMaxY;
                }

                $zLevels[$zLevel->z] = (object)$entry;
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
        $planData = plans()->read($planName);
        
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
        $plans = plans()->all(true);
        $allLocations = [];

        foreach ($plans as $planName => $planData) {
            // Include if visibleByDefault is explicitly set to true
            if (isset($planData->visibleByDefault) && $planData->visibleByDefault === true) {
                $allLocations = array_merge($allLocations, $this->getLocationFromPlan($planName));
            }
        }

        return $allLocations;
    }

    public function getAllPlans() {
        $allPlans = [];

        // Tous les plans, toutes saisons : le filtre s2 de all() exclurait
        // même olympia et enfers. Le filtrage par saison est l'affaire des
        // pages admin (admin/helpers.php : plan_matches_season_filter).
        foreach (plans()->all() as $planId => $planData) {
            $fullPlanData = $this->getPlanData($planId);
            $isS2 = strpos($planId, '_s2') !== false;
            $seasonName = $isS2 ? 'S2' : 'S1';
            $allPlans[] = (object)[
                'id' => $planId,
                'name' => $planData->name ?? $planId,
                'shortName' => $planData->shortName ?? $planId,
                'hasZLevels' => !empty($planData->z_levels),
                'visibleByDefault' => $planData->visibleByDefault ?? false,
                'season' => $seasonName,
                'isS2' => $isS2,
                'fullData' => $fullPlanData
            ];
        }

        return $allPlans;
    }

    public function getGlobalMap(): array {
        $layers = ['tiles', 'elements', 'coordinates', 'locations', 'routes', 'buildings', 'player'];
        $mapDir = $_SERVER['DOCUMENT_ROOT'].'/img/maps/world/';
        $results = [];

        foreach ($layers as $layer) {
            $files = glob($mapDir."world_{$layer}_*.png");
            if (!empty($files)) {
                usort($files, function($a, $b) {
                    return filemtime($b) - filemtime($a);
                });

                $filename = basename($files[0]);
                $timestamp = substr($filename, strpos($filename, '_', 11) + 1, 15);

                $results[$layer] = [
                    'imagePath' => "/img/maps/world/{$filename}",
                    'timestamp' => $timestamp
                ];
            }
        }

        return $results;
    }

    public function getLocalMap(): array
    {
        $layers = ['tiles', 'elements', 'foregrounds', 'resources', 'routes', 'buildings', 'players', 'player'];
        $mapDir = $_SERVER['DOCUMENT_ROOT'] . '/img/maps/local/';
        $results = [];

        if ($this->currentPlan === null) {
            return [];
        }

        foreach ($layers as $layer) {
            $pattern = $mapDir . "local_{$this->currentPlan}_{$this->playerZ}_{$layer}_*.png";
            $files = glob($pattern);

            if (!empty($files)) {
                usort($files, function ($a, $b) {
                    return filemtime($b) - filemtime($a);
                });

                $newestFile = $files[0];
                $filename = basename($newestFile);

                preg_match('/_(\d{8}-\d{6})\.png$/', $filename, $matches);
                $timestamp = $matches[1] ?? date('Ymd-His', filemtime($newestFile));

                $results[$layer] = [
                    'imagePath' => "/img/maps/local/{$filename}",
                    'timestamp' => $timestamp
                ];
            }
        }

        return $results;
    }

    private function applyBackground($layer, $planData, $useImage = false) {
        if (isset($planData->bg)) {
            if ($useImage) {
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
                $bgKey = pathinfo($planData->bg, PATHINFO_FILENAME);
                $bgColor = $this->getColorForType($bgKey);
    
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

    public function getPlayersDistance($p1X, $p1Y, $p2X, $p2Y): int
    {
        return max(abs($p2X - $p1X), abs($p2Y - $p1Y));
    }
}
