<?php
namespace Classes;

use App\Enum\CoordType;

class View{

    /**
     * Side of one board tile, in pixels. Every screen measure of the
     * board derives from it — change the UI scale here, nowhere else.
     */
    public const TILE_PX = 50;

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
                    AND player_type NOT IN ("scenery", "plant")
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


                $x = $coords->x;
                $y = $coords->y;


                $x = ($x - $this->coords->x + $this->p) * self::TILE_PX;
                $y = (-$y + $this->coords->y + $this->p) * self::TILE_PX;

                /* One sprite may span several tiles: a 2×2 édifice covers
                 * its whole box. Screen y inverts game y, so the ORIGIN row
                 * is already the top-left of the box. */
                $spanW = self::TILE_PX;
                $spanH = self::TILE_PX;


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
                            ? self::exemplarSprite((string) $entity->race, (string) $entity->name)
                            : self::structureSprite((string) $entity->race, (string) $entity->name);
                    }

                    if($isStructure){

                        $footprint = self::typeFootprints()[(string) $entity->race] ?? null;

                        if($footprint !== null && !$footprint->isSingleCell()){

                            $spanW = self::TILE_PX * $footprint->width();
                            $spanH = self::TILE_PX * $footprint->height();
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

                                width="'. self::TILE_PX .'"
                                height="'. self::TILE_PX .'"

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

                            width="'. $spanW .'"
                            height="'. $spanH .'"

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
                    // A spanned sprite fills its box even when not square.
                    $spanAttr = ($spanW !== self::TILE_PX || $spanH !== self::TILE_PX) ? ' preserveAspectRatio="none"' : '';
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

                        width="'. $spanW .'"
                        height="'. $spanH .'"

                        data-table="'. $row->whichTable .'"
                        data-coords="'. $coords->x .','. $coords->y .'"

                        x="'. floor($x) .'"
                        y="'. floor($y) .'"

                        href="'. $img .'"'. $avatarClassAttr . $spanAttr .'
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
                style="background: url(\''. $planJson->mask .'\'); max-width:'. $sizeW .'px; max-height:'. $sizeH .'px; "
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
     * The one rule for what a placed OBJECT shows: its item art
     * (img/items/{type}), else the structure chain — a chest without a
     * picture wears its initials frame like any structure. The board,
     * the entity card and the container screen all read HERE.
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
            $file = 'datas/private/players/' . $row->id . '.svg';
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
