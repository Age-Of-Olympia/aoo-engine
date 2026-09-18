<?php
namespace Classes;

use App\Enum\CoordType;

class View{

    /**
     * Side of one board tile, in pixels. Every screen measure of the
     * board derives from it — change the UI scale here, nowhere else.
     */
    public const TILE_PX = 50;

    /**
     * Width of the fade on an element's open side, as a fraction of the
     * tile. An element bordered by a cell without the same element fades
     * out on that side, so a lake ends in a soft shore, not a square.
     */
    private const ELEMENT_EDGE_FADE = 0.3;

    /** Width of the cross-fade between the two halves of an elbow, as a fraction of the tile. */
    private const ELBOW_BLEND = 0.3;

    /**
     * Family of an element: its name up to the first underscore. Elements
     * of one family join edge to edge (eau, eau_cascade, eau_ecume); any
     * other neighbour is an edge to fade toward.
     */
    public static function elementFamily(string $name): string
    {
        return explode('_', $name, 2)[0];
    }

    /**
     * Where a cell's element fades, as a bitmask. Sides: 1 north, 2 east,
     * 4 south, 8 west, open when the neighbour there is not of the same
     * family. Corners: 16 NE, 32 SE, 64 SW, 128 NW, set on the inside of a
     * bend — the diagonal cell is open while both sides around it are
     * filled — so the soft margins of the two branches meet round the
     * corner instead of leaving a square notch.
     */
    public static function elementEdgeBits(array $elementAt, int $x, int $y, string $name): int
    {
        $family = self::elementFamily($name);
        $has = function(int $dx, int $dy) use ($elementAt, $x, $y, $family): bool {
            $there = $elementAt[($x + $dx) .','. ($y + $dy)] ?? null;
            return $there !== null && self::elementFamily($there) === $family;
        };

        $bits = 0;
        foreach([[0, 1, 1], [1, 0, 2], [0, -1, 4], [-1, 0, 8]] as [$dx, $dy, $bit]){

            if(!$has($dx, $dy)){

                $bits |= $bit;
            }
        }
        foreach([[1, 1, 16], [1, -1, 32], [-1, -1, 64], [-1, 1, 128]] as [$dx, $dy, $bit]){

            if(!$has($dx, $dy) && $has($dx, 0) && $has(0, $dy)){

                $bits |= $bit;
            }
        }

        return $bits;
    }

    /**
     * One mask per pattern, in bounding-box units so one definition serves
     * every cell: white shows, a black-to-clear gradient on each open side
     * and a radial one on each inside corner fade the image out there.
     * Overlaps multiply.
     *
     * @param list<int> $patterns bitmasks from elementEdgeBits(), non-zero
     */
    public static function elementEdgeDefs(array $patterns): string
    {
        $f = self::ELEMENT_EDGE_FADE;
        $sides = [
            1 => 'x1="0" y1="0" x2="0" y2="1"',
            2 => 'x1="1" y1="0" x2="0" y2="0"',
            4 => 'x1="0" y1="1" x2="0" y2="0"',
            8 => 'x1="0" y1="0" x2="1" y2="0"',
        ];
        $corners = [16 => 'cx="1" cy="0"', 32 => 'cx="1" cy="1"', 64 => 'cx="0" cy="1"', 128 => 'cx="0" cy="0"'];
        $defs = '<defs>';
        foreach($sides as $bit => $axis){

            $defs .= '<linearGradient id="elem-fade-'. $bit .'" '. $axis .'>'
                . '<stop offset="0" stop-color="#000"/><stop offset="'. $f .'" stop-color="#000" stop-opacity="0"/></linearGradient>';
        }
        foreach($corners as $bit => $centre){

            $defs .= '<radialGradient id="elem-fade-'. $bit .'" '. $centre .' r="'. $f .'">'
                . '<stop offset="0" stop-color="#000"/><stop offset="1" stop-color="#000" stop-opacity="0"/></radialGradient>';
        }
        foreach(array_unique(array_filter($patterns)) as $bits){

            $defs .= '<mask id="elem-edge-'. $bits .'" maskUnits="objectBoundingBox" maskContentUnits="objectBoundingBox">'
                . '<rect width="1" height="1" fill="#fff"/>';
            foreach(array_keys($sides + $corners) as $bit){

                if($bits & $bit){

                    $defs .= '<rect width="1" height="1" fill="url(#elem-fade-'. $bit .')"/>';
                }
            }
            $defs .= '</mask>';
        }

        return $defs .'</defs>';
    }

    /**
     * A mark named meteo_<x> is weather: painted in Tiled like any mark
     * (its 50x50 icon in img/marks feeds the palette) but drawn as the
     * board-wide mask img/tiles/<x> while the viewer stands on it.
     */
    public const WEATHER_MARK_PREFIX = 'meteo_';

    /**
     * How each weather texture scrolls: seconds per loop and axis. Same
     * values as the plans that use these textures as their own mask;
     * a texture absent here is drawn still.
     */
    private const WEATHER_SCROLL = [
        'rain'           => ['seconds' => 0.2, 'vertical' => true],
        'fog'            => ['seconds' => 10,  'vertical' => false],
        'fog_ice'        => ['seconds' => 8,   'vertical' => false],
        'sand_storm'     => ['seconds' => 20,  'vertical' => false],
        'sand_storm_red' => ['seconds' => 20,  'vertical' => false],
        'dust_storm'     => ['seconds' => 10,  'vertical' => false],
        'ethereal_storm' => ['seconds' => 60,  'vertical' => false],
        'cloud_shadow'   => ['seconds' => 60,  'vertical' => false],
        'ombres'         => ['seconds' => 60,  'vertical' => false],
    ];

    /**
     * Mask of a weather mark: texture path and scroll settings, or null
     * when no texture of that name is on disk.
     *
     * @return array{mask: string, seconds: float, vertical: bool}|null
     */
    public static function weatherMask(string $markName): ?array
    {
        $name = substr($markName, strlen(self::WEATHER_MARK_PREFIX));

        foreach(\App\Service\TileCatalogService::IMAGE_EXTENSIONS as $ext){

            if(file_exists('img/tiles/'. $name .'.'. $ext)){

                $scroll = self::WEATHER_SCROLL[$name] ?? ['seconds' => 0, 'vertical' => false];

                return ['mask' => 'img/tiles/'. $name .'.'. $ext] + $scroll;
            }
        }

        return null;
    }

    /**
     * Sides of a cell joined to a neighbour of its family, by side. A
     * straight run joins two opposite sides, an elbow two sides at a right
     * angle.
     *
     * @param array<string, string> $elementAt element name by "x,y"
     * @return array<string, string> side => "x,y" of the neighbour
     */
    private static function joinedSides(array $elementAt, int $x, int $y, string $family): array
    {
        $joined = [];
        foreach(['N' => [0, 1], 'E' => [1, 0], 'S' => [0, -1], 'W' => [-1, 0]] as $side => [$dx, $dy]){

            $key = ($x + $dx) .','. ($y + $dy);
            if(isset($elementAt[$key]) && self::elementFamily($elementAt[$key]) === $family){

                $joined[$side] = $key;
            }
        }

        return $joined;
    }

    /**
     * Axis rotations of every cell of a family, by "x,y": the rotation
     * its path's straight runs use vertically ('v') and horizontally
     * ('h'). A path is the set of cells joined side to side; a cell
     * joined north and south gives its path the vertical rotation, one
     * joined east and west the horizontal one. A missing axis is the
     * other turned a quarter; a path with no straight run gets 0 and 90.
     *
     * @param array<string, string> $elementAt  element name by "x,y"
     * @param array<string, int>    $rotationAt element rotation by "x,y"
     * @return array<string, array{v: int, h: int}>
     */
    public static function elementAxes(array $elementAt, array $rotationAt, string $family): array
    {
        $axesAt = [];
        foreach(array_keys($elementAt) as $start){

            if(isset($axesAt[$start]) || self::elementFamily($elementAt[$start]) !== $family){
                continue;
            }
            // Walk the path from here, collecting its straight runs
            $path = [$start => true];
            $queue = [$start];
            $axes = [];
            while($queue !== []){

                $key = array_shift($queue);
                [$x, $y] = explode(',', $key);
                $joined = self::joinedSides($elementAt, (int) $x, (int) $y, $family);
                if(isset($joined['N'], $joined['S'])){
                    $axes['v'] ??= (int) ($rotationAt[$key] ?? 0);
                }
                if(isset($joined['E'], $joined['W'])){
                    $axes['h'] ??= (int) ($rotationAt[$key] ?? 0);
                }
                foreach($joined as $next){
                    if(!isset($path[$next])){
                        $path[$next] = true;
                        $queue[] = $next;
                    }
                }
            }
            $axes['v'] ??= isset($axes['h']) ? ($axes['h'] + 270) % 360 : 0;
            $axes['h'] ??= ($axes['v'] + 90) % 360;
            foreach(array_keys($path) as $key){
                $axesAt[$key] = $axes;
            }
        }

        return $axesAt;
    }

    /**
     * The two halves of an elbow: a cell with exactly two neighbours of
     * its family at right angles and nothing of the family in the corner
     * between them — the top of a two-wide fall has the corner filled and
     * is no bend. Each half faces one neighbour: a
     * straight run gives the half its own rotation, so the flow enters
     * and leaves the elbow exactly as painted; another elbow gives it the
     * path's rotation on that axis (elementAxes), so a staircase stays
     * consistent. The elbow's own rotation does not count.
     *
     * @param array<string, string>                $elementAt  element name by "x,y"
     * @param array<string, int>                   $rotationAt element rotation by "x,y"
     * @param array<string, array{v: int, h: int}> $axesAt     from elementAxes()
     * @return list<array{side: string, rotation: int, clip: string}>|null
     */
    public static function elementElbow(array $elementAt, array $rotationAt, array $axesAt, int $x, int $y, string $name): ?array
    {
        $family = self::elementFamily($name);
        $joined = self::joinedSides($elementAt, $x, $y, $family);
        if(count($joined) !== 2 || isset($joined['N'], $joined['S']) || isset($joined['E'], $joined['W'])){

            return null;
        }
        $inside = ($x + (isset($joined['E']) ? 1 : -1)) .','. ($y + (isset($joined['N']) ? 1 : -1));
        if(isset($elementAt[$inside]) && self::elementFamily($elementAt[$inside]) === $family){

            return null;
        }

        $outer = (isset($joined['N']) ? 'S' : 'N') . (isset($joined['E']) ? 'W' : 'E');
        $halves = [];
        foreach($joined as $side => $key){

            [$nx, $ny] = explode(',', $key);
            $there = self::joinedSides($elementAt, (int) $nx, (int) $ny, $family);
            $straight = isset($there['N'], $there['S']) || isset($there['E'], $there['W']);
            $axis = $side === 'N' || $side === 'S' ? 'v' : 'h';
            $halves[] = [
                'side'     => $side,
                'rotation' => $straight ? (int) ($rotationAt[$key] ?? 0) : ($axesAt[$x .','. $y][$axis] ?? ($axis === 'v' ? 0 : 90)),
                'clip'     => 'elem-half-'. $side . $outer,
            ];
        }

        return $halves;
    }

    /**
     * Masks for elbow halves, in bounding-box units (screen y down). Each
     * half is opaque on its side and fades out across a short band centred
     * on the mitre; the two halves are exact complements, so the textures
     * cross-fade over the diagonal instead of meeting on a line.
     *
     * @param list<string> $ids mask ids from elementElbow()
     */
    public static function elementHalfDefs(array $ids): string
    {
        $band = self::ELBOW_BLEND / 2;
        $corner = ['NW' => '0,0', 'NE' => '1,0', 'SE' => '1,1', 'SW' => '0,1'];
        $opposite = ['NW' => 'SE', 'NE' => 'SW', 'SE' => 'NW', 'SW' => 'NE'];
        $edge = ['N' => ['NW', 'NE'], 'E' => ['NE', 'SE'], 'S' => ['SW', 'SE'], 'W' => ['NW', 'SW']];
        $defs = '';
        foreach(array_unique($ids) as $id){

            [$side, $outer] = [substr($id, 10, 1), substr($id, 11)];
            // The half's own corner: the edge's corner that is not the inner one
            $inner = $opposite[$outer];
            $own = $edge[$side][0] === $inner ? $edge[$side][1] : $edge[$side][0];
            [$x1, $y1] = explode(',', $corner[$own]);
            [$x2, $y2] = explode(',', $corner[$opposite[$own]]);
            $defs .= '<mask id="'. $id .'" maskUnits="objectBoundingBox" maskContentUnits="objectBoundingBox">'
                . '<linearGradient id="'. $id .'-g" x1="'. $x1 .'" y1="'. $y1 .'" x2="'. $x2 .'" y2="'. $y2 .'">'
                . '<stop offset="'. (0.5 - $band) .'" stop-color="#fff"/><stop offset="'. (0.5 + $band) .'" stop-color="#000"/></linearGradient>'
                . '<rect width="1" height="1" fill="url(#'. $id .'-g)"/></mask>';
        }

        return $defs === '' ? '' : '<defs>'. $defs .'</defs>';
    }

    private $coords; // Coordonnées de la vue
    private $p; // Portée de la vue
    private $tiled; // Indique si la vue est dans l'éditeur de map
    private $inSight; // Coordonnées des objets dans le champ de vision
    private $inSightId; // id de ces coordonnées
    private $useTbl; // array qui permettra d'augmenter le z-level des images
    /** @var list<array{id:int, name:string, family:string, image:string, x:int, y:int, w:int, h:int}> */
    private $sceneryFigures = []; // scenery drawn whole, across its footprint
    private $options; // player->get_options()
    private $playerId; // ID du joueur pour qui la vue est générée
    private $fullCoordsOnCases; // data-coords-full sur les cases (éditeur + admins)
    private $footW = 1; // the viewer's footprint, in tiles — a 2×2
    private $footH = 1; // building senses from its whole box


    function __construct($coords, $p, $tiled=false, $options=array(), $playerId=null){


        $this->coords = $coords;
        $this->p = $p;
        $this->tiled = $tiled;

        // Use provided playerId or fall back to session
        $this->playerId = $playerId ?? ($_SESSION['playerId'] ?? null);

        /* A multi-cell viewer SENSES from its whole footprint: the zone
         * is the footprint's box grown by p, not a square around the
         * anchor cell. The origin cell stays (x-p, y+p) — the box only
         * gains columns rightward and rows downward, where a figure
         * extends — so every screen transform below holds unchanged. */
        $this->footW = 1;
        $this->footH = 1;
        if($this->playerId !== null){

            $db = new Db();
            $res = $db->exe('SELECT race FROM players WHERE id = ?', [(int) $this->playerId]);
            $race = ($res && ($row = $res->fetch_object())) ? (string) $row->race : '';
            $foot = self::typeFootprints()[$race] ?? null;

            if($foot !== null){

                $this->footW = $foot->width();
                $this->footH = $foot->height();
            }
        }

        $this->inSight = array();
        $this->inSightId = array();
        View::get_coords_id_arround($this->inSight, $this->inSightId, $coords, $p, $this->footW - 1, $this->footH - 1);

        $this->useTbl = array();
        $this->options = $options;

        /* Coordonnées complètes x,y,z,plan sur chaque case : l'éditeur
         * de map en a besoin pour ses outils, et les admins en jeu pour
         * l'outil clic droit (format directement collable en console). */
        $this->fullCoordsOnCases = $tiled || in_array('isAdmin', $options);
    }
   
    /**
     * Builds the class attribute, or nothing when the list is empty. Single
     * composition point: an element can never carry two class attributes.
     *
     * @param array<int, string> $classes
     */
    private static function class_attr(array $classes): string
    {
        $classes = array_values(array_unique(array_filter($classes)));

        return $classes ? ' class="'. implode(' ', $classes) .'"' : '';
    }

    //outCoords && $outCoordsId are passed by reference initialized is resposability of caller
    // extraX/extraY widen the box rightward and downward for a multi-cell viewer
    public static function get_coords_id_arround(&$outCoords,&$outCoordsId,$coords,$p,$extraX=0,$extraY=0){
        $minX = $coords->x - $p;
        $maxX = $coords->x + $p + $extraX;
        $minY = $coords->y - $p - $extraY;
        $maxY = $coords->y + $p;

        $sql = '
        SELECT id, x, y, shade FROM coords
        WHERE x BETWEEN ? AND ?
        AND y BETWEEN ? AND ?
        AND z = ?
        AND plan = ?
        ';

        $db = new Db();

        $res = $db->exe($sql, [
            $minX,
            $maxX,
            $minY,
            $maxY,
            $coords->z,
            $coords->plan
        ]);

        while($row = $res->fetch_object()){
            if(isset($outCoords))
                $outCoords[$row->id] = $row;
            if(isset($outCoordsId))
                $outCoordsId[] = $row->id;
        }

    }


    public function get_view(){


        $classTransparent = array();


        ob_start();


        $sizeW = (($this->p * 2) + $this->footW) * self::TILE_PX;
        $sizeH = (($this->p * 2) + $this->footH) * self::TILE_PX;


        $planJson = plans()->read($this->coords->plan);

        // Texture of the weather on the viewer's cell, set by the render loop
        $weatherMask = null;

        // Load invisible players to filter them from view
        $invisiblePlayers = array();
        $db = new Db();
        $invisibleSQL = "SELECT player_id FROM `players_options` WHERE name='invisibleMode'";
        $resInvisible = $db->exe($invisibleSQL);
        while($row = $resInvisible->fetch_object()){
            $invisiblePlayers[$row->player_id] = true;
        }

        $tile = (!empty($planJson->bg)) ? $planJson->bg : 'img/tiles/'. $this->coords->plan .'.webp';

        if(!file_exists($tile)){

            $tile = 'img/tiles/'. $this->coords->plan .'.png';
        }

        if($this->coords->z < 0){

            $tile = 'img/tiles/underground.webp';
        }
        elseif($this->coords->z > 0){

            $tile = 'img/tiles/sky.webp';
        }


        echo '
        <div id="view">
        <div id="svg-container" style="display:block;">
        <?xml version="1.0" encoding="UTF-8" standalone="no"?>
        <svg
            xmlns="http://www.w3.org/2000/svg"
            xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1"
            viewBox="0 0 '. $sizeW .' '. $sizeH .'"
            
            id="svg-view"

            width="100%"
            height="100%"

            style="max-width: '. $sizeW .'px;"

            class="box-shadow"
            >
            ';

            /* Le sol ENTRE dans le SVG, au lieu d'être une
             * background-image CSS posée sur lui.
             *
             * En CSS, la texture se répétait à sa taille en PIXELS : elle
             * ne suivait donc ni le zoom ni le défilement du damier, qui
             * eux vivent dans le viewBox. Au pincement, les cases
             * grandissaient pendant que le sol restait fin et se
             * contentait de se répéter davantage. En <pattern>, la
             * texture est exprimée dans les mêmes unités que le reste :
             * elle suit tout, sans une ligne de JavaScript pour la
             * recaler.
             *
             * La taille du motif est celle de l'IMAGE, pas une constante :
             * les tuiles de sol n'ont pas toutes le même format — gaia
             * fait 500 (dix cases), underground et sky en font 50. La
             * figer aurait changé l\'échelle du décor selon le plan. */
            $tileSize = @getimagesize($tile);
            $tileW = ($tileSize[0] ?? 0) ?: self::TILE_PX;
            $tileH = ($tileSize[1] ?? 0) ?: self::TILE_PX;

            /* Débordement large : buildMapRulers (js/hud.js) agrandit le
             * viewBox pour loger les coordonnées en marge, et cette marge
             * était peinte par le fond CSS. Le rectangle la couvre donc
             * aussi ; ce qui dépasse est écrêté par le viewBox, sans coût. */
            echo '
            <defs>
                <pattern id="ground-pattern" patternUnits="userSpaceOnUse"
                         width="'. $tileW .'" height="'. $tileH .'">
                    <image href="'. $tile .'" xlink:href="'. $tile .'"
                           width="'. $tileW .'" height="'. $tileH .'" />
                </pattern>
            </defs>
            <rect x="-200" y="-200" width="'. ($sizeW + 400) .'" height="'. ($sizeH + 400) .'"
                  fill="url(#ground-pattern)" />
            ';


            $tiledSql = '';
            $inSightIdImploded = implode(',', $this->inSightId);

            /* Which element each cell in sight carries, to fade an element
             * on the sides where its neighbour is not the same one — and
             * the angle a tile or element was placed at, by layer. */
            $elementAt = [];
            $rotationAt = [];
            $edgePatterns = [];
            $halfClips = [];
            $axesByFamily = [];
            $resPlaced = $db->exe(
                'SELECT "elements" AS layer, name, coords_id, rotation FROM map_elements WHERE coords_id IN ('. $inSightIdImploded .')
                 UNION ALL
                 SELECT "tiles", name, coords_id, rotation FROM map_tiles WHERE rotation <> 0 AND coords_id IN ('. $inSightIdImploded .')'
            );
            while($placed = $resPlaced->fetch_object()){

                $cell = $this->inSight[$placed->coords_id];
                if($placed->layer === 'elements'){
                    $elementAt[$cell->x .','. $cell->y] = $placed->name;
                }
                $rotationAt[$placed->layer][$cell->x .','. $cell->y] = (int) $placed->rotation;
            }

            /* Les cases infranchissables, telles que le serveur les refusera.
             *
             * Le damier ne portait jusqu'ici que le seul cas indevinable par
             * le client — les déclencheurs `forbidden`, absents du DOM en jeu
             * normal — et laissait `js/blocked-tiles.js` RECONSTITUER le reste
             * à partir des calques dessinés : une image de ressource ici, une
             * image de joueur là. Deux prédicats de plus, en JavaScript,
             * capables de contredire `go.php` ; ils se contredisaient déjà
             * entre eux, `js/view.js` écartant les structures traversables que
             * `js/blocked-tiles.js` comptait.
             *
             * Le verdict est désormais demandé à celui qui refuse le pas, pour
             * tout le champ de vision d'un coup. Le client ne déduit plus, il
             * lit `data-blocked`. */
            $blockedCoordsXY = [];
            if (!empty($this->inSightId)) {
                $occupancy = new \App\Service\Map\TileOccupancyService();
                $refusals = $occupancy->blockedForStep(
                    array_map('intval', $this->inSightId),
                    (int) $this->playerId,
                    \App\Service\Map\TileOccupancyService::charactersVisibleOn($planJson ?: null)
                );

                foreach (array_keys($refusals) as $blockedId) {
                    if (isset($this->inSight[$blockedId])) {
                        $blockedCoordsXY[$this->inSight[$blockedId]->x .','. $this->inSight[$blockedId]->y] = true;
                    }
                }
            }

            /* Les entités en vue, en UNE requête.
             *
             * La boucle de rendu ne lit d'un occupant que son type, son
             * avatar, sa race et son nom. Elle montait pourtant un objet
             * Player complet PAR LIGNE — caracs, options, effets, inventaire
             * — soit autant d'hydratations que d'occupants à l'écran, à
             * chaque rendu de damier. Sur la fenêtre la plus dense de
             * fort_turok, 428 occupants. */
            /* Les PNJ apparus pour la session de tutoriel en cours.
             *
             * Le damier les marque `.tutorial-enemy`, la prise sur laquelle
             * les étapes accrochent leur surlignage. Il les reconnaissait au
             * NOM — « Âme d'entraînement » —, c'est-à-dire à un libellé
             * d'affichage que l'administration du tutoriel peut changer :
             * renommer le PNJ éteignait le surlignage, sans erreur ni trace.
             *
             * Une requête, et seulement pendant un tutoriel : hors session,
             * getSessionEnemyIds() rend un tableau vide sans toucher la base. */
            $tutorialEnemyIds = \App\Tutorial\TutorialHelper::getSessionEnemyIds();

            $entitiesInSight = [];
            if (!empty($this->inSightId)) {
                /* Ce qui TRAÎNE n'est pas une figure : le damier le montre déjà
                 * par le repère de bourse de la case, comme toute la ferraille
                 * au sol. Le dessiner en plus donnerait deux objets là où il
                 * n'y en a qu'un. */
                $resEntities = (new Db())->exe('
                    SELECT id, name, player_type, avatar, race
                    FROM players
                    WHERE coords_id IN ('. $inSightIdImploded .')
                    AND player_type NOT IN ("scenery", "plant", "route")
                    AND slot <> "dropped"
                ');
                while ($rowE = $resEntities->fetch_object()) {
                    $entitiesInSight[(int) $rowE->id] = $rowE;
                }
            }

            /* Whole scenery: one figure per entity, and the cells it takes
             * over from the piece rows.
             *
             * Not in the editor. Tiled works a piece at a time — pieces are
             * erased, named and placed one cell at a time there — so laying a
             * single image over the rows an animator is dragging would blind
             * them to their own work. */
            $sceneryCovered = [];
            $this->sceneryFigures = [];

            if (!$this->tiled && !empty($this->inSightId)) {
                $inSight = (new \App\Service\Map\SceneryFiguresInSight())->forWindow(
                    array_map('intval', $this->inSightId)
                );

                $this->sceneryFigures = $inSight['figures'];
                $sceneryCovered = $inSight['covered'];
            }

            // Safety check: if no coords in sight, skip the query
            if (empty($this->inSightId)) {
                error_log("[View] No coords found in sight for current position - skipping map elements query");
                echo '</svg>';
                return;
            }

            if($this->tiled){

                // only for tiled

                $tiledSql = '
                UNION

                SELECT
                id, name, coords_id,
                "triggers" AS whichTable,
                200 AS tableOrder
                FROM
                map_triggers
                WHERE
                coords_id IN ('. $inSightIdImploded .')

                UNION

                SELECT
                id, name, coords_id,
                "dialogs" AS whichTable,
                300 AS tableOrder
                FROM
                map_dialogs
                WHERE
                coords_id IN ('. $inSightIdImploded .')
                ';
            }


            $sql = '
            SELECT
            id, name, coords_id,
            "tiles" AS whichTable,
            93 AS tableOrder
            FROM
            map_tiles
            WHERE
            coords_id IN ('. $inSightIdImploded .')

            UNION

            SELECT
            MIN(id) AS id, MIN(name) AS name, coords_id,
            "items" AS whichTable,
            96 AS tableOrder
            FROM
            map_items
            WHERE
            coords_id IN ('. $inSightIdImploded .')
            GROUP BY coords_id

            UNION

            SELECT
            MIN(id) AS id, "bourse" AS name, coords_id,
            "items" AS whichTable,
            96 AS tableOrder
            FROM
            players
            WHERE
            slot = "dropped"
            AND
            coords_id IN ('. $inSightIdImploded .')
            GROUP BY coords_id

            UNION

            SELECT
            id, name, coords_id,
            "elements" AS whichTable,
            97 AS tableOrder
            FROM
            map_elements
            WHERE
            coords_id IN ('. $inSightIdImploded .')
            
            UNION

            /* Les plantes sont des ENTITÉS, et le damier ignore ce détail :
               elles gardent leur couche et leur profondeur — 97.5, donc SOUS le
               personnage, puisque une fleur se marche dessus. Le nom vient de
               `race` : le sprite se déduit du TYPE (img/plants/…), quand `name`
               porte le libellé de exemplaire posé. */
            SELECT
            id, race AS name, coords_id,
            "plants" AS whichTable,
            97.5 AS tableOrder
            FROM
            players
            WHERE
            coords_id IN ('. $inSightIdImploded .')
            AND player_type = "plant"

            UNION

            /* Roads are ENTITIES now, and the board ignores that detail: they
               keep their layer and their depth — 97.6, so UNDER the character,
               since a road is walked on. The name comes from `race`, as for
               plants: the sprite follows the TYPE (img/routes/...). Mind the
               quotes here — this SQL lives in a single-quoted PHP string. */
            SELECT
            id, race AS name, coords_id,
            "routes" AS whichTable,
            97.6 AS tableOrder
            FROM
            players
            WHERE
            coords_id IN ('. $inSightIdImploded .')
            AND player_type = "route"

            UNION

            /* Marks — footsteps, the flag — sit above every ground layer
               and under the characters. */
            SELECT
            id, name, coords_id,
            "marks" AS whichTable,
            97.9 AS tableOrder
            FROM
            map_marks
            WHERE
            coords_id IN ('. $inSightIdImploded .')

            UNION

            SELECT
            id, name, coords_id,
            "players" AS whichTable,
            98 AS tableOrder
            FROM
            players
            WHERE
            coords_id IN ('. $inSightIdImploded .')
            /* Scenery has its own pass, after the loop, at its own depth.
             * Letting it through here would draw it twice — and at 98, so
             * UNDER the resources and without its footprint: one 50x50 image
             * on its anchor cell alone. This filter stays. */
            AND player_type NOT IN ("scenery", "plant")

            UNION

            SELECT
            id, name, coords_id,
            "foregrounds" AS whichTable,
            100 AS tableOrder
            FROM
            map_foregrounds
            WHERE
            coords_id IN ('. $inSightIdImploded .')
            /* Minus the cells of a figure already drawn whole. Excluded per
             * FIGURE, not per "an entity holds this cell": scenery that does
             * NOT draw whole — a single cell, or no composed picture yet —
             * has to keep its pieces, or it would vanish. */
            '. ($sceneryCovered === [] ? '' : 'AND coords_id NOT IN ('. implode(',', array_keys($sceneryCovered)) .')') .'

            UNION

            /* Les suivants — l\'étal du marchand, le double d\'illusion.
             * Ils se dessinent comme du décor et à la même profondeur, mais
             * ils n\'en sont plus : ils appartiennent à un personnage, ils le
             * suivent, et ils s\'en vont avec lui. Les ranger dans
             * map_foregrounds les rendait indiscernables des décors posés par
             * un animateur — 19 marchands de la carte n\'appartiennent à
             * personne — et un suivant retiré emportait parfois l\'un d\'eux. */
            SELECT
            id, name, coords_id,
            "foregrounds" AS whichTable,
            100 AS tableOrder
            FROM
            players_followers
            WHERE
            coords_id IN ('. $inSightIdImploded .')

            '. $tiledSql .'

            ORDER BY
            tableOrder
            ';


            $db = new Db();

            $res = $db->exe($sql);


            while($row = $res->fetch_object()){


                $id = $row->whichTable . $row->id;


                $coords = $this->inSight[$row->coords_id];

                /* A weather mark is never drawn on its cell (the editor
                 * excepted, where the mapper must see it): the one under
                 * the viewer picks the mask laid over the whole board. */
                if(!$this->tiled && $row->whichTable == 'marks' && str_starts_with($row->name, self::WEATHER_MARK_PREFIX)){

                    if($coords->x == $this->coords->x && $coords->y == $this->coords->y){

                        $weatherMask = self::weatherMask($row->name);
                    }

                    continue;
                }


                $x = $coords->x;
                $y = $coords->y;


                $x = ($x - $this->coords->x + $this->p) * self::TILE_PX;
                $y = (-$y + $this->coords->y + $this->p) * self::TILE_PX;

                /* One sprite may span several tiles: a 2×2 édifice covers
                 * its whole box. Screen y inverts game y, so the ORIGIN row
                 * is already the top-left of the box. */
                $spanW = self::TILE_PX;
                $spanH = self::TILE_PX;

                // A tile or element placed turned is drawn turned about its cell centre
                $angle = $rotationAt[$row->whichTable][$coords->x .','. $coords->y] ?? 0;
                $turn = $angle ? ' transform="rotate('. $angle .' '. (floor($x) + self::TILE_PX / 2) .' '. (floor($y) + self::TILE_PX / 2) .')"' : '';


                // La couche resources garde ses images dans img/walls
                // (dépôt d'assets + avatars copiés en base — voir
                // TiledMapService::layerImageDir)
                $imgDir = $row->whichTable == 'resources' ? 'walls' : $row->whichTable;
                $img = 'img/'. $imgDir .'/'. $row->name .'.png';

                // Classes carried by this cell's image, reset with $img on every row.
                $imgClasses = [];


                if($row->whichTable == 'items'){


                    $img = 'img/tiles/loot.png';
                }

                elseif($row->whichTable == 'players'){

                    /* Une SEULE requête pour toutes les entités en vue (voir
                     * $entitiesInSight plus haut), au lieu d'un objet Player
                     * complet hydraté par ligne.
                     *
                     * La boucle ne lit que cinq champs — type, avatar, race,
                     * nom, id — là où PlayerFactory::legacy()->get_data()
                     * montait tout le personnage : caracs, options, effets,
                     * inventaire. À 428 occupants dans la fenêtre la plus
                     * dense de fort_turok, c'était 428 hydratations par rendu
                     * de damier. */
                    $entity = $entitiesInSight[(int) $row->id] ?? null;

                    if($entity === null){

                        /* Ligne apparue entre les deux requêtes : on la saute
                         * plutôt que de la dessiner à moitié. */
                        continue;
                    }

                    // Les structures (bâtiments, objets uniques) font partie du
                    // décor, comme les murs : toujours visibles, même quand la
                    // visibilité des joueurs est coupée (plans isolés, tutoriel).
                    $isStructure = \App\Enum\EntityCategory::fromPlayerType($entity->player_type ?? null)->isStructure();

                    // Skip invisible players (except when viewing your own character)
                    if (!$isStructure && $row->id != $this->playerId && isset($invisiblePlayers[$row->id])) {
                        continue;
                    }

                    // Les joueurs normaux sont soumis aux règles de visibilité
                    if (!$isStructure && $this->playerId > 0) {
                        // Masquer les autres joueurs si :
                        // 1. Le JSON du plan n'existe pas OU
                        // 2. Le JSON du plan existe et player_visibility est explicitement défini sur false
                        if ((!$planJson || (isset($planJson->player_visibility) && $planJson->player_visibility === false))
                            && $row->id > 0 && $row->id != $this->playerId) {
                            continue;
                        }
                    }
                    // Les PNJs peuvent voir tout le monde, sans restriction de visibilité

                    $img = $entity->avatar;

                    /* Avatar figé en base à la CONVERSION : quand elle a tourné
                     * sans img/ (le déploiement exécute les migrations depuis
                     * le checkout git), il est resté vide — 10 539 bâtiments
                     * sur 13 549 en production — et les murs convertis
                     * s'affichaient en initiales « Mu ».
                     *
                     * Le rendu se contente de RÉSOUDRE le visuel manquant. Il
                     * ne répare plus la ligne : réparer était un UPDATE et une
                     * purge de cache déclenchés depuis un chemin de lecture,
                     * pour chaque structure encore vide, à chaque affichage de
                     * carte. La réparation durable est
                     * `building repair-avatars` en console, qui fait
                     * exactement le même calcul, une fois. */
                    if($isStructure && (empty($img) || !file_exists($img))){

                        $img = ((string) ($entity->player_type ?? '') === 'item')
                            ? self::boardExemplarSprite((string) $entity->race, (string) $entity->name)
                            : self::structureSprite((string) $entity->race, (string) $entity->name);
                    }

                    /* Any entity spans its type's cut-out — a character race
                     * declared 2×2 is drawn as large as the édifice it rivals. */
                    $footprint = self::typeFootprints()[(string) $entity->race] ?? null;

                    if($footprint !== null && !$footprint->isSingleCell()){

                        $spanW = self::TILE_PX * $footprint->width();
                        $spanH = self::TILE_PX * $footprint->height();
                    }

                    /* La bordure de race dit d'un coup d'œil À QUI on a
                     * affaire : elle a du sens sur un personnage, moins sur
                     * un mur ou un coffre, où elle encombre le décor. Elle
                     * reste donc toujours posée sur les personnages, PNJ
                     * compris, et devient facultative sur le reste. */
                    $raceHintApplies = in_array('raceHint', $this->options)
                        && !($isStructure && in_array('hideStructureBorders', $this->options));

                    if($raceHintApplies){


                        $raceBgColor = \App\Service\RaceService::getRaceColor($entity->race);


                        if(in_array('raceHintMax', $this->options)){

                            $style = 'fill: '. $raceBgColor;
                        }

                        else{

                            $style = 'fill: transparent; stroke-width: 5; stroke: '. $raceBgColor;
                        }


                        echo '
                        <rect
                            class="case"

                            x="' . $x . '"
                            y="' . $y . '"

                            width="'. $spanW .'"
                            height="'. $spanH .'"

                            style="'. $style .'"
                            />
                        ';
                    }
                }

                elseif($row->whichTable == 'foregrounds'){


                    $this->useTbl[] = $id;
                }


                // transparent gradient
                if(!empty($classTransparent[$x .','. $y]) && $row->whichTable != 'tiles'){

                    // Never written into $img: a quote injected in the URL would
                    // give elements with their own class two class attributes,
                    // fatal in strict XML (SVG read alone or through <img>).
                    $imgClasses[] = 'transparent-gradient';
                }


                if($row->whichTable == 'elements' || $row->whichTable == 'marks'){


                    $typesTbl = array(
                        'gif'=>'0.3',
                        'webp'=>'0.5',
                        'png'=>'1',
                        'svg'=>'1'
                    );


                    /* An element fades on its open sides. The mask goes on
                     * a group AROUND the turned image: on the image itself
                     * it would turn with it and fade the wrong sides. */
                    $edgeMask = '';
                    // One drawing per cell, or two clipped halves at an elbow
                    $halves = [['turn' => $turn, 'clip' => '']];
                    if($row->whichTable == 'elements'){

                        $edgeBits = self::elementEdgeBits($elementAt, (int) $coords->x, (int) $coords->y, $row->name);
                        $edgeMask = $edgeBits ? ' mask="url(#elem-edge-'. $edgeBits .')"' : '';
                        $edgePatterns[$edgeBits] = $edgeBits;

                        $family = self::elementFamily($row->name);
                        $axesByFamily[$family] ??= self::elementAxes($elementAt, $rotationAt['elements'] ?? [], $family);
                        $elbow = self::elementElbow($elementAt, $rotationAt['elements'] ?? [], $axesByFamily[$family], (int) $coords->x, (int) $coords->y, $row->name);
                        if($elbow !== null){

                            $halves = [];
                            foreach($elbow as $half){

                                $halfClips[$half['clip']] = $half['clip'];
                                $halves[] = [
                                    'turn' => $half['rotation'] ? ' transform="rotate('. $half['rotation'] .' '. (floor($x) + self::TILE_PX / 2) .' '. (floor($y) + self::TILE_PX / 2) .')"' : '',
                                    'clip' => ' mask="url(#'. $half['clip'] .')"',
                                ];
                            }
                        }
                    }

                    foreach($typesTbl as $k=>$e){


                        $img = 'img/'. $row->whichTable .'/'. $row->name .'.'. $k;

                        // Scenery layers do not get transparent-gradient; drop
                        // this reset to apply it to them.
                        $imgClasses = [];

                        if(file_exists($img)){

                            echo ($edgeMask ? '<g'. $edgeMask .'>' : '');
                            foreach($halves as $half){

                                echo ($half['clip'] ? '<g'. $half['clip'] .'>' : '') .'
                                <image

                                    width="'. self::TILE_PX .'"
                                    height="'. self::TILE_PX .'"

                                    data-table="'. $row->whichTable .'"
                                    data-coords="'. $coords->x .','. $coords->y .'"

                                    x="'. floor($x) .'"
                                    y="'. floor($y) .'"

                                    style="opacity: '. $e .';"
                                    pointer-events="none"

                                    href="'. $img .'"
                                    '. self::class_attr($imgClasses) . $half['turn'] .'
                                    />
                                '. ($half['clip'] ? '</g>' : '');
                            }
                            echo ($edgeMask ? '</g>' : '');
                        }
                    }


                    if($row->whichTable == 'elements'){
                        $classTransparent[$x .','. $y] = 'transparent-gradient';
                    }
                }

                else{

                    // default


                    $isCurrentPlayer = ($row->whichTable == 'players' && $row->id == $this->playerId);
                    $isTutorialEnemy = ($row->whichTable == 'players'
                        && isset($tutorialEnemyIds[(int) $row->id]));

                    if($row->whichTable == 'players'){

                        // Shadow image — decorative only. .avatar-shadow CSS
                        // shrinks this to 35x35 with a -5/14 offset. Tutorial
                        // markers (#current-player-avatar, .tutorial-enemy,
                        // .current-player) live on the FULL-size avatar
                        // below so highlight padding computes against the
                        // actual 50x50 tile rect and stays symmetric.
                        echo '
                        <image

                            id="'. $id .'-shadow"

                            width="'. $spanW .'"
                            height="'. $spanH .'"

                            data-table="'. $row->whichTable .'"
                            data-coords="'. $coords->x .','. $coords->y .'"

                            x="'. floor($x) .'"
                            y="'. floor($y) .'"

                            href="'. $img .'"

                            '. self::class_attr(array_merge($imgClasses, ['avatar-shadow'])) .'
                            />
                        ';
                    }


                    // Full-size avatar (50x50, no offsets). All tutorial
                    // selectors target this so highlights stay aligned.
                    // A spanned sprite fills its box even when not square.
                    $spanAttr = ($spanW !== self::TILE_PX || $spanH !== self::TILE_PX) ? ' preserveAspectRatio="none"' : '';
                    $avatarClasses = $imgClasses;
                    if ($isCurrentPlayer) {
                        $avatarClasses[] = 'current-player';
                    }
                    if ($isTutorialEnemy) {
                        $avatarClasses[] = 'tutorial-enemy';
                    }
                    $avatarClassAttr = self::class_attr($avatarClasses);

                    echo '
                    <image

                        id="'. ($isCurrentPlayer ? 'current-player-avatar' : $id) .'"

                        width="'. $spanW .'"
                        height="'. $spanH .'"

                        data-table="'. $row->whichTable .'"
                        data-coords="'. $coords->x .','. $coords->y .'"

                        x="'. floor($x) .'"
                        y="'. floor($y) .'"

                        href="'. $img .'"'. $avatarClassAttr . $spanAttr . $turn .'
                        />
                    ';
                }

            }


            // uses
            foreach($this->useTbl as $e){

                echo '<use xlink:href="#'. $e .'" />';
            }


            /* Scenery, drawn whole across its footprint.
             *
             * Deliberately outside the loop above: that loop is written for
             * 50x50 tiles — a transparent gradient injected into the href, an
             * avatar shadow CSS pins to 35px, a race border on one cell. A
             * three-cell figure would not survive it.
             *
             * Painting here is painting at depth 100: the highest the query
             * reaches in play mode, so scenery covers characters and the
             * `cover` role keeps hiding whoever stands behind. */
            foreach($this->sceneryFigures as $figure){

                $fx = ($figure['x'] - $this->coords->x + $this->p) * self::TILE_PX;
                $fy = (-$figure['y'] + $this->coords->y + $this->p) * self::TILE_PX;

                echo '
                    <image
                    id="scenery'. (int) $figure['id'] .'"
                    data-table="scenery"
                    data-entity="'. (int) $figure['id'] .'"
                    width="'. ($figure['w'] * self::TILE_PX) .'"
                    height="'. ($figure['h'] * self::TILE_PX) .'"
                    x="'. floor($fx) .'"
                    y="'. floor($fy) .'"
                    preserveAspectRatio="none"
                    href="'. $figure['image'] .'"
                    />
                ';
            }


            /* L'assombrissement des cases, en UN rectangle par case.
             *
             * `ombre` etait un decor : un PNG noir uni a 5,5 % d'opacite, que
             * les animateurs posaient PLUSIEURS FOIS sur la meme case pour
             * foncer davantage — un degrade peint a la main, sur cinq niveaux,
             * et 82 % des lignes de `map_foregrounds`.
             *
             * L'empilement est devenu une intensite (`coords.shade`), et ce
             * qu'un niveau VAUT a l'ecran se regle PAR PLAN — une grotte se
             * veut plus sombre qu'une plaine (CellShadeService, cascade plan
             * → tableau de bord → defaut). Separer le niveau de son rendu
             * permet de changer l'apparence des ombres sans reprendre les
             * cases qui en portent une.
             *
             * Le rendu reste fidele au pixel pres : N calques d'opacite `a`
             * donnent `1-(1-a)^N`, qu'un seul rectangle porte aussi bien que
             * N images. Les cases les plus sombres passent de cinq elements
             * a un.
             *
             * Dessine APRES les entites, comme le decor l'etait (couche 100,
             * au-dessus des joueurs en 98) : l'ombre couvre ce qui s'y tient. */
            $shadeService = new \App\Service\CellShadeService();
            $shadeConfig = $shadeService->forPlan($this->coords->plan);
            $shadeColor = $shadeConfig['color'];

            foreach($this->inSight as $row){

                $level = (int) ($row->shade ?? 0);

                if($level < 1){

                    continue;
                }

                $opacity = $shadeService->opacityOnPlan($this->coords->plan, $level);

                $sx = ($row->x - $this->coords->x + $this->p) * self::TILE_PX;
                $sy = ($this->coords->y - $row->y + $this->p) * self::TILE_PX;

                echo '
                <rect
                    class="cell-shade"
                    x="'. $sx .'"
                    y="'. $sy .'"
                    width="'. self::TILE_PX .'"
                    height="'. self::TILE_PX .'"
                    fill="'. $shadeColor .'"
                    fill-opacity="'. $opacity .'"
                    pointer-events="none"
                    />
                ';
            }


            // go cases
            $coordsArround = View::get_coords_arround($this->coords, 1);


            // grid or empty clickable cases — the box carries the viewer's footprint
            for ($i = 0; $i < $this->p*2 + $this->footW; $i++) {

                for ($j = 0; $j < $this->p*2 + $this->footH; $j++) {


                    $coordX = $i + $this->coords->x - $this->p;
                    $coordY = -$j + $this->coords->y + $this->p;

                    $x = $i * self::TILE_PX;
                    $y = $j * self::TILE_PX;

                    $goCase = '';

                    if(in_array($coordX .','. $coordY, $coordsArround)){


                        $goCase = 'go';
                    }

                    $blockedAttr = isset($blockedCoordsXY[$coordX .','. $coordY])
                        ? ' data-blocked="1"'
                        : '';

                    if(!in_array('hideGrid', $this->options)){

                        echo '
                        <image
                            class="case '. $goCase .'"
                            data-coords="'. $coordX .','. $coordY .'"'. $blockedAttr;

                            if($this->fullCoordsOnCases){
                                echo ' data-coords-full="'. $coordX .','. $coordY .','.$this->coords->z.','.$this->coords->plan.'"';
                            }

                           echo '
                            x="' . $x . '"
                            y="' . $y . '"

                            href="img/ui/view/grid.webp"
                            />
                        ';
                    }

                    else {

                        echo '
                        <rect
                            class="case '. $goCase .'"
                            data-coords="'. $coordX .','. $coordY .'"'. $blockedAttr;

                            if($this->fullCoordsOnCases){
                                echo ' data-coords-full="'. $coordX .','. $coordY .','.$this->coords->z.','.$this->coords->plan.'"';
                            }

                            echo ' x="' . $x . '"
                            y="' . $y . '"

                            width="'. self::TILE_PX .'"
                            height="'. self::TILE_PX .'"

                            fill="transparent"
                            />
                        ';
                    }
                }
            }


            // go button
            echo '
            <rect
                data-coords=""
                id="go-rect"

                x="'. self::TILE_PX .'"
                y="'. self::TILE_PX .'"

                width="'. self::TILE_PX .'"
                height="'. self::TILE_PX .'"

                fill="green"
                style="opacity: 0.3; display: none;"
                />
            ';

            echo '
            <image
                id="go-img"

                x="'. self::TILE_PX .'"
                y="30"

                style="opacity: 0.8; display: none; pointer-events: none;"
                class="blink"
                href="img/ui/view/arrow.webp"
                />
            ';

            // destroy button
            echo '
            <rect
                data-coords=""
                id="destroy-rect"

                x="'. self::TILE_PX .'"
                y="'. self::TILE_PX .'"

                width="'. self::TILE_PX .'"
                height="'. self::TILE_PX .'"

                fill="red"
                style="opacity: 0.3; display: none;"
                />
            ';

            echo '
            <image
                id="destroy-img"

                x="'. self::TILE_PX .'"
                y="30"

                style="opacity: 0.8; display: none; pointer-events: none; filter: hue-rotate(-100deg); z-index: 100;"
                class="blink"
                href="img/ui/view/arrow.webp"
                />
            ';

            // Mask references resolve wherever the defs sit in the document
            echo self::elementEdgeDefs(array_values($edgePatterns)) . self::elementHalfDefs(array_values($halfClips)) .'
        </svg>
        ';

        /* The weather on the viewer's cell wins over the plan's own mask,
         * and brings its own scroll settings; the plan's apply to its mask. */
        $mask = $weatherMask['mask'] ?? (!empty($planJson->mask) ? $planJson->mask : null);
        $scrollSeconds = $weatherMask['seconds'] ?? (float) ($planJson->scrollingMask ?? 0);
        $scrollVertical = $weatherMask['vertical'] ?? !empty($planJson->verticalScrolling);

        if($mask !== null && $this->coords->z >= 0 && !in_array('noMask', $this->options)){


            if($scrollSeconds > 0){


                list($maskW, $maskH) = getimagesize($mask);

                echo '
                <style>
                .scrolling-mask {

                    animation: scrollMask '. $scrollSeconds .'s linear infinite;
                }

                @keyframes scrollMask {

                    0% {
                    background-position: 0 0;
                    }
                    100% {
                    ';

                    if(!$scrollVertical){

                        echo 'background-position: -'. $maskW .'px 0;';
                    }

                    else{

                        echo 'background-position: 0 '. $maskW .'px;';
                    }

                echo '
                </style>
                ';
            }
            
            echo '
            <div
                class="view-mask scrolling-mask"
                style="background: url(\''. $mask .'\'); max-width:'. $sizeW .'px; max-height:'. $sizeH .'px; "
                >
            </div>
            ';
        }

        echo '
        </div>
        </div>
        ';


        // scroll middle of view overflow
        echo '
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            var scrollableDiv = document.getElementById("view");
            scrollableDiv.scrollLeft = (scrollableDiv.scrollWidth - scrollableDiv.clientWidth) / 2;
        });
        </script>
        ';


        return Str::minify(ob_get_clean());
    }


    // STATIC

    public static function get_coords_arround($coords, $p,CoordType $coordType=CoordType::XY,string $separator=','){


        $return = array();

        for ($i = 0; $i < $p*2+1; $i++) {

            for ($j = 0; $j < $p*2+1; $j++) {


                $coordX = $i + $coords->x - $p;
                $coordY = -$j + $coords->y + $p;
                switch ($coordType) {
                    case CoordType::XY:
                        $return[] = $coordX . $separator . $coordY;
                        break;
                    
                    case CoordType::XYZPLAN:
                        $return[] = $coordX . $separator . $coordY . $separator . $coords->z . $separator . $coords->plan;
                        break;
                }
                
            }
        }

        return $return;
    }


    public static function get_coords_taken($coords){

        $sql = '
        SELECT
        x, y
        FROM
        coords AS c
        INNER JOIN
        players AS p
        ON
        p.coords_id = c.id
        WHERE
        z = ?
        AND
        plan = ?

        UNION

        SELECT
        x, y
        FROM
        coords AS c
        INNER JOIN
        map_triggers AS p
        ON
        p.coords_id = c.id
        WHERE
        z = ?
        AND
        plan = ?
        ';

        $db = new Db();

        $res = $db->exe($sql, array($coords->z, $coords->plan, $coords->z, $coords->plan));

        $coordsTaken = array($coords->x .','. $coords->y);

        while($row = $res->fetch_object()){


            $coordsTaken[] = $row->x .','. $row->y;
        }

        return $coordsTaken;
    }

    public static function get_coords_id($goCoords){

        $db = new Db();

        // Validate input
        if (!isset($goCoords->x, $goCoords->y, $goCoords->z, $goCoords->plan)) {
            error_log("[View::get_coords_id] ERROR: Missing required coordinate fields");
            error_log("[View::get_coords_id] Coords object: " . print_r($goCoords, true));
            return null;
        }

        $sql = '
        SELECT id FROM coords WHERE x = ? AND y = ? AND z = ? AND plan = ?
        ';

        $res = $db->exe($sql, array($goCoords->x, $goCoords->y, $goCoords->z, $goCoords->plan));


        if(!$res->num_rows){

            $coordsData = [
                'x' => (int)$goCoords->x,
                'y' => (int)$goCoords->y,
                'z' => (int)$goCoords->z,
                'plan' => (string)$goCoords->plan
            ];

            try {
                /* Upsert idempotent, PAS insert + get_last_id : deux requêtes
                 * qui découvrent la même case au même instant doivent obtenir
                 * le MÊME id. ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
                 * fait rendre à LAST_INSERT_ID() la ligne existante quand la
                 * clé unique (plan, z, x, y) casse l'insertion — la forme
                 * marche donc pour les deux chemins.
                 *
                 * get_last_id('coords') faisait « ORDER BY id DESC LIMIT 1 »,
                 * c'est-à-dire le MAX de TOUTE la table : sous concurrence il
                 * rendait la case d'un autre joueur. */
                $db->exe(
                    'INSERT INTO coords (x, y, z, plan) VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)',
                    array($coordsData['x'], $coordsData['y'], $coordsData['z'], $coordsData['plan'])
                );

                $row = $db->exe('SELECT LAST_INSERT_ID() AS id')->fetch_assoc();
                $coordsId = (int) ($row['id'] ?? 0);

                /* Repli : en simulation les écritures sont avalées par
                 * SimulationGuard, donc LAST_INSERT_ID() ne désigne pas notre
                 * ligne. On relit alors par coordonnées plutôt que de rendre
                 * un id faux. */
                if (!$coordsId) {
                    $again = $db->exe($sql, array($goCoords->x, $goCoords->y, $goCoords->z, $goCoords->plan));
                    if (!$again->num_rows) {
                        return null;
                    }
                    $coordsId = (int) $again->fetch_object()->id;
                }
                /* Pas de log du chemin nominal : l'auto-création de coords
                 * est un événement normal et fréquent (exploration de la
                 * carte) — le journaliser noie les vraies erreurs, et
                 * PHPUnit 12 capture error_log() et marque risky tout test
                 * qui passe par ici. Les échecs restent logués ci-dessous. */
            } catch (\Exception $e) {
                error_log("[View::get_coords_id] ERROR creating coords: " . $e->getMessage());
                error_log("[View::get_coords_id] Coords data: " . print_r($coordsData, true));
                return null;
            }
        }

        else{

            $row = $res->fetch_object();

            $coordsId = $row->id;
        }

        return $coordsId;
    }


    public static function get_free_coords_id_arround(&$goCoords, $p=1){


        /* Sous terre, une case sans sol creusé (map_tiles) est de la roche
         * pleine : on n'y débarque pas. Sans ce filtre, l'arrivée d'un
         * escalier tombait dans la roche et go.php démarrait `creuser`
         * depuis la surface — refusé, l'escalier ne menait jamais en bas. */
        $dug = null;

        if($goCoords->z < 0){

            $dug = array();

            $res = (new Db())->exe(
                'SELECT c.x, c.y FROM map_tiles t INNER JOIN coords c ON c.id = t.coords_id WHERE c.z = ? AND c.plan = ?',
                array($goCoords->z, $goCoords->plan)
            );

            while($row = $res->fetch_object()){

                $dug[] = $row->x .','. $row->y;
            }
        }


        $coordsTaken = View::get_coords_taken($goCoords);

        $coordsArround = View::get_coords_arround($goCoords, $p);
        $coordsArround = array_diff($coordsArround, $coordsTaken);

        if($dug !== null){

            $coordsArround = array_intersect($coordsArround, $dug);
        }


        while(true){


            if(!count($coordsArround)){

                /* Sous terre : pas de voisine libre au sol creusé — la
                 * destination elle-même dès qu'elle a un sol (la case de
                 * l'escalier), plutôt que d'élargir et débarquer à
                 * plusieurs cases de là. */
                if($dug !== null && in_array($goCoords->x .','. $goCoords->y, $dug)){

                    break;
                }

                $p++;

                /* Rien de libre (et creusé, sous terre) à portée. */
                if($p > 10){

                    break;
                }

                $coordsArround = View::get_coords_arround($goCoords, $p);

                $coordsArround = array_diff($coordsArround, $coordsTaken);

                if($dug !== null){

                    $coordsArround = array_intersect($coordsArround, $dug);
                }

                continue;
            }


            shuffle($coordsArround);


            $randCoords = array_pop($coordsArround);

            $goCoords->x = explode(',', $randCoords)[0];
            $goCoords->y = explode(',', $randCoords)[1];


            break;
        }


        $coordsId = View::get_coords_id($goCoords);


        return $coordsId;
    }

    public static function get_coords_from_id($id){
        $sql = '
        SELECT
        x,y,z,plan
        FROM
        coords AS c
        WHERE 
        c.id = ?
        ';

        $db = new Db();

        $res = $db->exe($sql, $id);

        if(!$res->num_rows){

            exit('error coords');
        }


        $row = $res->fetch_object();


        $coords = (object) array(
            'x'=>$row->x,
            'y'=>$row->y,
            'z'=>$row->z,
            'plan'=>$row->plan
        );

        return $coords;
    }


    public static function get_coords($table, $id):object{

        $sql = '
        SELECT
        x,y,z,plan
        FROM
        coords AS c
        INNER JOIN
        map_'. $table .' AS w
        ON
        w.coords_id = c.id
        WHERE
        w.id = ?
        ';

        $db = new Db();

        $res = $db->exe($sql, $id);

        if(!$res->num_rows){

            exit('error coords');
        }


        $row = $res->fetch_object();


        $coords = (object) array(
            'x'=>$row->x,
            'y'=>$row->y,
            'z'=>$row->z,
            'plan'=>$row->plan
        );

        return $coords;
    }


    public static function get_distance($coords1, $coords2){

        $coords1 = (array) $coords1;

        $coords2 = (array) $coords2;


        // not same z error
        if($coords1['z'] != $coords2['z'])
            return 100000000;

        // not same plan error
        if($coords1['plan'] != $coords2['plan'])
            return 100000000;


        $difX = abs($coords1['x'] - $coords2['x']) ;
        $difY = abs($coords1['y'] - $coords2['y']) ;

        if( $difX > $difY ) return $difX ;
        else return $difY ;
    }

    /**
     * The entity cell nearest a point, or its declared point.
     *
     * That is what a shot aims at: aiming at a far cell traced a line through
     * the object's own body, and it screened the shot meant for it.
     *
     * @return object coords {x, y, z, plan}
     */
    public static function get_nearest_cell_of($coords, int $entityId, $fallbackCoords)
    {
        $coords = (array) $coords;
        $nearest = null;
        $best = null;

        foreach ((new \App\Service\Map\EntityCellService())->cellsOf($entityId) as $cell) {
            $candidate = (object) [
                'x' => (int) $cell['x'], 'y' => (int) $cell['y'],
                'z' => (int) $cell['z'], 'plan' => (string) $cell['plan'],
            ];

            $distance = self::get_distance($coords, $candidate);

            if ($best === null || $distance < $best) {
                $best = $distance;
                $nearest = $candidate;
            }
        }

        return $nearest ?? $fallbackCoords;
    }

    /**
     * Distance to an ENTITY, measured to its nearest cell.
     *
     * One is next to an object as soon as one is next to any of its cells;
     * `get_distance()` measures to a point, which a multi-cell object is not.
     *
     * With no cells at all, falls back to the declared point.
     */
    public static function get_distance_to_entity($coords, int $entityId, $fallbackCoords = null): int
    {
        $coords = (array) $coords;
        $nearest = null;

        foreach ((new \App\Service\Map\EntityCellService())->cellsOf($entityId) as $cell) {
            $distance = self::get_distance($coords, [
                'x' => (int) $cell['x'], 'y' => (int) $cell['y'],
                'z' => (int) $cell['z'], 'plan' => (string) $cell['plan'],
            ]);

            if ($nearest === null || $distance < $nearest) {
                $nearest = $distance;
            }
        }

        if ($nearest !== null) {
            return $nearest;
        }

        /* With no cells at all — an entity nothing has synchronised — measure
         * as before, to the point it declares. A correct set of cells always
         * holds one there, so the two agree once the table is up to date. */
        return $fallbackCoords === null ? 100000000 : self::get_distance($coords, $fallbackCoords);
    }




    public static function put($table, $name, $coords){


        $db = new Db();

        $values = array(
            'name'=>$name,
            'coords_id'=>View::get_coords_id($coords),
            'player_id'=>$_SESSION['playerId']
        );

        $db->insert('map_'. $table, $values);


        self::refresh_players_svg($coords);
    }


    /**
     * Avatar de repli d'une structure sans visuel : ses deux premières
     * lettres dans un cadre, en SVG inline (data-URI). Rester une URL
     * d'image garde tout l'aval intact — le damier émet le même
     * <image data-table="players"> (bouton Aller de js/view.js, ombre
     * .avatar-shadow), et la fiche peut l'afficher en grand.
     */
    /** Per-request memo of the type cut-outs, for the multi-cell sprites. */
    private static ?array $typeFootprints = null;

    /** @return array<string, \App\Service\Map\Footprint> */
    private static function typeFootprints(): array{

        return self::$typeFootprints ??= (new \App\Service\Map\EntityTypeFootprintService())->catalogue();
    }

    /**
     * The one rule for what a structure SHOWS: its type's avatar
     * (resolveAvatar's fallback chain), else the initials frame. The
     * board and the build picker's ghost read HERE — a second copy of
     * the chain would drift.
     */
    public static function structureSprite(string $type, string $name): string{

        $resolved = \App\Service\BuildingService::resolveAvatar($type);

        return $resolved !== '' ? $resolved : self::structureInitialsAvatar($name);
    }

    /**
     * The one rule for what a placed OBJECT shows in VIGNETTES
     * (inventory rows, previews, peeks): its item art
     * (img/items/{type}), else the structure chain — a chest without a
     * picture wears its initials frame like any structure.
     */
    public static function exemplarSprite(string $itemName, string $label): string{

        foreach(['webp', 'png'] as $ext){

            $img = 'img/items/'. $itemName .'.'. $ext;

            if(file_exists($img)){

                return $img;
            }
        }

        return self::structureSprite($itemName, $label);
    }

    /**
     * The same object on the BOARD: the structure art first — a placed
     * chest is scenery-scale, its walls sprite fills the tile where the
     * item icon is a small vignette — then the item art, then the
     * initials frame.
     */
    public static function boardExemplarSprite(string $itemName, string $label): string{

        $resolved = \App\Service\BuildingService::resolveAvatar($itemName);

        if($resolved !== ''){

            return $resolved;
        }

        foreach(['webp', 'png'] as $ext){

            $img = 'img/items/'. $itemName .'.'. $ext;

            if(file_exists($img)){

                return $img;
            }
        }

        return self::structureInitialsAvatar($label);
    }

    public static function structureInitialsAvatar(string $name): string{

        $name = trim($name);
        $initials = mb_strtoupper(mb_substr($name, 0, 1), 'UTF-8')
            . mb_strtolower(mb_substr($name, 1, 1), 'UTF-8');

        // width/height : les dimensions intrinsèques sont OBLIGATOIRES —
        // sans elles, un <img> dans un conteneur shrink-to-fit (fiche)
        // s'effondre à 0. Vectoriel : le damier le rend à 50px sans perte.
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 50 50">'
            . '<rect x="2.5" y="2.5" width="45" height="45" rx="7" fill="#efe3c4" stroke="#5b4322" stroke-width="3"/>'
            . '<rect x="7" y="7" width="36" height="36" rx="4" fill="none" stroke="#b39767" stroke-width="1.5"/>'
            . '<text x="25" y="27" text-anchor="middle" dominant-baseline="central"'
            . ' font-family="Georgia,serif" font-size="19" font-weight="bold" fill="#4a3115">'
            . htmlspecialchars($initials, ENT_QUOTES, 'UTF-8')
            . '</text></svg>';

        return 'data:image/svg+xml;charset=utf-8,' . rawurlencode($svg);
    }


    public static function refresh_players_svg(object $coords,$p=20):void{

        self::refresh_players_svg_in_box(
            $coords->x - $p,
            $coords->x + $p,
            $coords->y - $p,
            $coords->y + $p,
            (int) $coords->z,
            (string) $coords->plan
        );
    }

    /**
     * Même purge, sur une ZONE plutôt qu'autour d'un point.
     *
     * Une poussée depuis Tiled touche une région entière : appeler la version
     * ponctuelle case par case relançait la même requête des centaines de
     * fois, pour effacer les mêmes fichiers.
     */
    /**
     * Purge autour d'une case désignée par son id.
     *
     * Ce que veulent les éditeurs de carte : ils tiennent un `coords_id` et
     * rien d'autre, et sans ça un joueur immobile ne voyait pas apparaître ce
     * qu'un animateur venait de poser sous ses yeux.
     */
    public static function refresh_players_svg_at(int $coordsId, int $p = 20): void
    {
        $res = (new Db())->exe('SELECT x, y, z, plan FROM coords WHERE id = ?', array($coordsId));
        $row = $res ? $res->fetch_assoc() : null;

        if (!$row) {
            return;
        }

        self::refresh_players_svg((object) $row, $p);
    }

    public static function refresh_players_svg_in_box(
        int $minX,
        int $maxX,
        int $minY,
        int $maxY,
        int $z,
        string $plan
    ): void {
        // based on View::get_coords_id_arround that is the fastest implementation
        $db = new Db();
        $coords = (object) ['z' => $z, 'plan' => $plan];

        /* Purge du cache SVG, restreinte à ce qui peut en avoir un.
         *
         * On exclut UNIQUEMENT le mobilier inerte — ressources et décors —
         * qui n'agit jamais et ne rendra donc jamais de vue. Les bâtiments
         * RESTENT dans le balayage : ils sont appelés à agir (bâtiments de
         * défense), donc à tenir une session et un cache comme un joueur.
         *
         * Liste noire et non liste blanche, précisément pour ça : une liste
         * blanche fondée sur « une structure n'agit pas » deviendrait fausse
         * le jour où un bâtiment agit, et son cache cesserait silencieusement
         * d'être purgé. Ici, tout type nouveau est balayé par défaut ; seul
         * ce qui est démontré inerte en sort.
         *
         * Effet : le balayage cesse de croître avec le nombre de ressources
         * et de décors posés, sans rien changer pour l'existant. */
        $sql = '
            SELECT p.id AS id
            FROM
            players AS p
            INNER JOIN
            coords AS c
            ON
            p.coords_id = c.id
            WHERE x BETWEEN ? AND ?
            AND y BETWEEN ? AND ?
            AND c.z = ?
            AND c.plan = ?
            AND (p.player_type IS NULL OR p.player_type NOT IN (\'resource\', \'scenery\'))';

        $res = $db->exe($sql, array($minX, $maxX, $minY, $maxY, $coords->z, $coords->plan));


        while ($row = $res->fetch_object()) {
            /* Absolute: an api/ endpoint's working directory is its own
             * folder, and a relative path silently purged nothing. */
            $file = dirname(__DIR__) . '/datas/private/players/' . $row->id . '.svg';
            if (is_file($file)) {
                unlink($file); // Delete the file
            }
        }
    }


    public static function delete_double($player){


        $url = 'img/foregrounds/doubles/'. $player->id .'.png';

        /* Le double est un SUIVANT, plus une ligne de décor : il se retire de
         * sa propre table. L'ancienne version supprimait au passage tout
         * `map_foregrounds` portant ce nom — sans filtre de joueur. */
        $name = 'doubles/'. $player->id;

        (new Db())->exe(
            'DELETE FROM players_followers WHERE player_id = ? AND name = ?',
            array($player->id, $name)
        );

        if (file_exists($url)) {
            unlink($url); // Delete the file
        }

        if(!isset($player->coords)){

            $player->getCoords();
        }

        self::refresh_players_svg($player->coords);
    }

    /**
     * La case est-elle vide ? — délègue à TileOccupancyService::isVacant(),
     * qui porte les trois questions d'occupation au même endroit (le pas,
     * l'atterrissage, la construction). Comportement inchangé.
     */
    public static function is_free($coords): bool {

        /* Lecture SEULE : surtout pas get_coords_id(), qui CRÉE la case
         * absente — un prédicat de lecture ne doit rien écrire. */
        $res = (new Db())->exe(
            'SELECT id FROM coords WHERE x = ? AND y = ? AND z = ? AND plan = ?',
            [$coords->x, $coords->y, $coords->z, $coords->plan]
        );

        // Case jamais créée : rien ne peut s'y trouver.
        if (!$res || !$res->num_rows) {
            return true;
        }

        $coordsId = $res->fetch_object()->id;

        return (new \App\Service\Map\TileOccupancyService())->isVacant((int) $coordsId);
    }
}
