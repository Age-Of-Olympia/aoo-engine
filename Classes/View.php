<?php
namespace Classes;

use App\Enum\CoordType;

class View{

    private $coords; // Coordonnées de la vue
    private $p; // Portée de la vue
    private $tiled; // Indique si la vue est dans l'éditeur de map
    private $inSight; // Coordonnées des objets dans le champ de vision
    private $inSightId; // id de ces coordonnées
    private $useTbl; // array qui permettra d'augmenter le z-level des images
    private $options; // player->get_options()
    private $playerId; // ID du joueur pour qui la vue est générée
    private $fullCoordsOnCases; // data-coords-full sur les cases (éditeur + admins)


    function __construct($coords, $p, $tiled=false, $options=array(), $playerId=null){


        $this->coords = $coords;
        $this->p = $p;
        $this->tiled = $tiled;

        $this->inSight = array();
        $this->inSightId = array();
        View::get_coords_id_arround($this->inSight, $this->inSightId, $coords, $p);

        $this->useTbl = array();
        $this->options = $options;

        /* Coordonnées complètes x,y,z,plan sur chaque case : l'éditeur
         * de map en a besoin pour ses outils, et les admins en jeu pour
         * l'outil clic droit (format directement collable en console). */
        $this->fullCoordsOnCases = $tiled || in_array('isAdmin', $options);

        // Use provided playerId or fall back to session
        $this->playerId = $playerId ?? ($_SESSION['playerId'] ?? null);
    }
   
    //outCoords && $outCoordsId are passed by reference initialized is resposability of caller
    public static function get_coords_id_arround(&$outCoords,&$outCoordsId,$coords,$p){
        $minX = $coords->x - $p;
        $maxX = $coords->x + $p;
        $minY = $coords->y - $p;
        $maxY = $coords->y + $p;

        $sql = '
        SELECT id, x, y FROM coords
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


        $size = (($this->p * 2) + 1) * 50;


        $planJson = json()->decode('plans', $this->coords->plan);

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
            viewBox="0 0 '. $size .' '. $size .'"
            
            id="svg-view"

            width="100%"
            height="100%"

            style="max-width: '. $size .'px;"

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
            $tileW = ($tileSize[0] ?? 0) ?: 50;
            $tileH = ($tileSize[1] ?? 0) ?: 50;

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
            <rect x="-200" y="-200" width="'. ($size + 400) .'" height="'. ($size + 400) .'"
                  fill="url(#ground-pattern)" />
            ';


            $tiledSql = '';
            $inSightIdImploded = implode(',', $this->inSightId);

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
                $resEntities = (new Db())->exe('
                    SELECT id, name, player_type, avatar, race
                    FROM players
                    WHERE coords_id IN ('. $inSightIdImploded .')
                ');
                while ($rowE = $resEntities->fetch_object()) {
                    $entitiesInSight[(int) $rowE->id] = $rowE;
                }
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
            MIN(instance_id) AS id, "bourse" AS name, coords_id,
            "items" AS whichTable,
            96 AS tableOrder
            FROM
            map_items_instances
            WHERE
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

            SELECT
            id, name, coords_id,
            "plants" AS whichTable,
            97.5 AS tableOrder
            FROM
            map_plants
            WHERE
            coords_id IN ('. $inSightIdImploded .')

            UNION

            SELECT
            id, name, coords_id,
            "routes" AS whichTable,
            97.6 AS tableOrder
            FROM
            map_routes
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

            UNION

            SELECT
            id, name, coords_id,
            "resources" AS whichTable,
            99 AS tableOrder
            FROM
            map_resources
            WHERE
            coords_id IN ('. $inSightIdImploded .')

            UNION

            SELECT
            id, name, coords_id,
            "foregrounds" AS whichTable,
            100 AS tableOrder
            FROM
            map_foregrounds
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


                $x = $coords->x;
                $y = $coords->y;


                $x = ($x - $this->coords->x + $this->p) * 50;
                $y = (-$y + $this->coords->y + $this->p) * 50;


                // La couche resources garde ses images dans img/walls
                // (dépôt d'assets + avatars copiés en base — voir
                // TiledMapService::layerImageDir)
                $imgDir = $row->whichTable == 'resources' ? 'walls' : $row->whichTable;
                $img = 'img/'. $imgDir .'/'. $row->name .'.png';


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
                    $isStructure = in_array($entity->player_type ?? 'real', ['building', 'unique'], true);

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

                        $resolved = \App\Service\BuildingService::resolveAvatar((string) $entity->race);

                        if($resolved !== ''){

                            $img = $resolved;
                        }
                        else{

                            // Vraiment sans visuel (taverne…) : initiales.
                            $img = self::structureInitialsAvatar((string) $entity->name);
                        }
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

                            width="50"
                            height="50"

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

                    $img .= '" class="transparent-gradient';
                }


                if($row->whichTable == 'elements'){


                    // elements


                    $typesTbl = array(
                        'gif'=>'0.3',
                        'webp'=>'0.5',
                        'png'=>'1'
                    );


                    foreach($typesTbl as $k=>$e){


                        $img = 'img/elements/'. $row->name .'.'. $k;

                        if(file_exists($img)){

                            echo '
                            <image

                                width="50"
                                height="50"

                                data-table="'. $row->whichTable .'"
                                data-coords="'. $coords->x .','. $coords->y .'"

                                x="'. floor($x) .'"
                                y="'. floor($y) .'"

                                style="opacity: '. $e .';"
                                pointer-events="none"

                                href="'. $img .'"
                                />
                            ';
                        }
                    }


                    if($row->name != 'sang' && !str_starts_with($row->name, 'trace_pas') && $row->name != 'routes'){
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

                            width="50"
                            height="50"

                            data-table="'. $row->whichTable .'"
                            data-coords="'. $coords->x .','. $coords->y .'"

                            x="'. floor($x) .'"
                            y="'. floor($y) .'"

                            href="'. $img .'"
                            class="avatar-shadow"
                            />
                        ';
                    }


                    // Full-size avatar (50x50, no offsets). All tutorial
                    // selectors target this so highlights stay aligned.
                    $avatarClasses = [];
                    if ($isCurrentPlayer) {
                        $avatarClasses[] = 'current-player';
                    }
                    if ($isTutorialEnemy) {
                        $avatarClasses[] = 'tutorial-enemy';
                    }
                    $avatarClassAttr = $avatarClasses ? ' class="'. implode(' ', $avatarClasses) .'"' : '';

                    echo '
                    <image

                        id="'. ($isCurrentPlayer ? 'current-player-avatar' : $id) .'"

                        width="50"
                        height="50"

                        data-table="'. $row->whichTable .'"
                        data-coords="'. $coords->x .','. $coords->y .'"

                        x="'. floor($x) .'"
                        y="'. floor($y) .'"

                        href="'. $img .'"'. $avatarClassAttr .'
                        />
                    ';
                }

            }


            // uses
            foreach($this->useTbl as $e){

                echo '<use xlink:href="#'. $e .'" />';
            }


            // go cases
            $coordsArround = View::get_coords_arround($this->coords, 1);


            // grid or empty clickable cases
            for ($i = 0; $i < $this->p*2+1; $i++) {

                for ($j = 0; $j < $this->p*2+1; $j++) {


                    $coordX = $i + $this->coords->x - $this->p;
                    $coordY = -$j + $this->coords->y + $this->p;

                    $x = $i * 50;
                    $y = $j * 50;

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

                            width="50"
                            height="50"

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

                x="50"
                y="50"

                width="50"
                height="50"

                fill="green"
                style="opacity: 0.3; display: none;"
                />
            ';

            echo '
            <image
                id="go-img"

                x="50"
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

                x="50"
                y="50"

                width="50"
                height="50"

                fill="red"
                style="opacity: 0.3; display: none;"
                />
            ';

            echo '
            <image
                id="destroy-img"

                x="50"
                y="30"

                style="opacity: 0.8; display: none; pointer-events: none; filter: hue-rotate(-100deg); z-index: 100;"
                class="blink"
                href="img/ui/view/arrow.webp"
                />
            ';

            echo '
        </svg>
        ';

        if(!empty($planJson->mask) && $this->coords->z >= 0 && !in_array('noMask', $this->options)){


            if(!empty($planJson->scrollingMask)){


                list($maskW, $maskH) = getimagesize($planJson->mask);

                echo '
                <style>
                .scrolling-mask {

                    animation: scrollMask '. $planJson->scrollingMask .'s linear infinite;
                }

                @keyframes scrollMask {

                    0% {
                    background-position: 0 0;
                    }
                    100% {
                    ';

                    if(!isset($planJson->verticalScrolling)){

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
                style="background: url(\''. $planJson->mask .'\'); max-width:'. $size .'px; max-height:'. $size .'px; "
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
        map_resources AS p
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

        $res = $db->exe($sql, array($coords->z, $coords->plan, $coords->z, $coords->plan, $coords->z, $coords->plan));

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



        $coordsArround = View::get_coords_arround($goCoords, $p);

        $coordsTaken = View::get_coords_taken($goCoords);

        $coordsArround = array_diff($coordsArround, $coordsTaken);


        while(true){


            if(!count($coordsArround)){

                $p++;

                $coordsArround = View::get_coords_arround($goCoords, $p);

                $coordsArround = array_diff($coordsArround, $coordsTaken);
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
        // based on View::get_coords_id_arround that is the fastest implementation 
        $db = new Db();
        $minX = $coords->x - $p;
        $maxX = $coords->x + $p;
        $minY = $coords->y - $p;
        $maxY = $coords->y + $p;

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
            $file = 'datas/private/players/' . $row->id . '.svg';
            if (is_file($file)) {
                unlink($file); // Delete the file
            }
        }
    }


    public static function delete_double($player){


        $url = 'img/foregrounds/doubles/'. $player->id .'.png';

        $sql = '
        DELETE p
        FROM
        map_foregrounds AS m
        INNER JOIN
        players_followers AS p
        ON
        m.id = p.foreground_id
        WHERE
        p.player_id = ?
        AND
        m.name = ?
        ';

        $name = $name='doubles/'. $player->id;

        $db = new Db();

        $db->exe($sql, array($player->id, $name));

        $values = array(
            'name'=>'doubles/'. $player->id
        );

        $db->delete('map_foregrounds', $values);

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
