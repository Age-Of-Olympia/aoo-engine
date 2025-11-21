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


    function __construct($coords, $p, $tiled=false, $options=array(), $playerId=null){


        $this->coords = $coords;
        $this->p = $p;
        $this->tiled = $tiled;

        $this->inSight = array();
        $this->inSightId = array();
        View::get_coords_id_arround($this->inSight, $this->inSightId, $coords, $p);

        $this->useTbl = array();
        $this->options = $options;

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

            style="background: url(\''. $tile .'\');max-width: '. $size .'px;"

            class="box-shadow"
            >
            ';


            $tiledSql = '';
            $inSightIdImploded = implode(',', $this->inSightId);

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
            id, name, coords_id,
            "items" AS whichTable,
            96 AS tableOrder
            FROM
            map_items
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
            "walls" AS whichTable,
            99 AS tableOrder
            FROM
            map_walls
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


                $img = 'img/'. $row->whichTable .'/'. $row->name .'.png';


                if($row->whichTable == 'items'){


                    $img = 'img/tiles/loot.png';
                }

                elseif($row->whichTable == 'players'){
                    $player = new Player($row->id);
                    $player->get_data();

                    // Les joueurs normaux sont soumis aux règles de visibilité
                    if ($this->playerId > 0) {
                        // Masquer les autres joueurs si :
                        // 1. Le JSON du plan n'existe pas OU
                        // 2. Le JSON du plan existe et player_visibility est explicitement défini sur false
                        if ((!$planJson || (isset($planJson->player_visibility) && $planJson->player_visibility === false))
                            && $row->id > 0 && $row->id != $this->playerId) {
                            continue;
                        }
                    }
                    // Les PNJs peuvent voir tout le monde, sans restriction de visibilité

                    $img = $player->data->avatar;


                    if(in_array('raceHint', $this->options)){


                        $raceJson = json()->decode('races', $player->data->race);


                        if(in_array('raceHintMax', $this->options)){

                            $style = 'fill: '. $raceJson->bgColor;
                        }

                        else{

                            $style = 'fill: transparent; stroke-width: 5; stroke: '. $raceJson->bgColor;
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

                                x="'. floor($x) .'"
                                y="'. floor($y) .'"

                                style="opacity: '. $e .';"

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


                    if($row->whichTable == 'players'){

                        // Add "current-player" class and ID for tutorial targeting
                        $playerClass = 'avatar-shadow';
                        $currentPlayerId = '';
                        if ($row->id == $this->playerId) {
                            $playerClass .= ' current-player';
                            $currentPlayerId = 'current-player-avatar'; // Additional ID for reliable targeting
                        }
                        // Add tutorial-enemy class for tutorial enemy targeting
                        if ($row->id < 0 && $player->data->name === "Mannequin d'entraînement") {
                            $playerClass .= ' tutorial-enemy';
                        }

                        echo '
                        <image

                            id="'. ($currentPlayerId ?: $id) .'"

                            width="50"
                            height="50"

                            x="'. floor($x) .'"
                            y="'. floor($y) .'"

                            href="'. $img .'"

                            class="'. $playerClass .'"
                            />
                        ';
                    }


                    echo '
                    <image

                        id="'. $id .'"

                        width="50"
                        height="50"

                        data-table="'. $row->whichTable .'"

                        x="'. floor($x) .'"
                        y="'. floor($y) .'"

                        href="'. $img .'"
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

                    if(!in_array('hideGrid', $this->options)){

                        echo '
                        <image
                            class="case '. $goCase .'"
                            data-coords="'. $coordX .','. $coordY .'"';

                            if($this->tiled){
                                echo 'data-coords-full="'. $coordX .','. $coordY .','.$this->coords->z.','.$this->coords->plan.'"';
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
                            class="case"
                            data-coords="'. $coordX .','. $coordY .'"';

                            if($this->tiled){
                                echo 'data-coords-full="'. $coordX .','. $coordY .','.$this->coords->z.','.$this->coords->plan.'"';
                            }

                            echo 'x="' . $x . '"
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
        map_walls AS p
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

        $sql = '
        SELECT id FROM coords WHERE x = ? AND y = ? AND z = ? AND plan = ?
        ';

        $res = $db->exe($sql, array($goCoords->x, $goCoords->y, $goCoords->z, $goCoords->plan));


        if(!$res->num_rows){


            $db->insert('coords', (array) $goCoords);

            $coordsId = $db->get_last_id('coords');
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


    public static function get_walls_between($coords1, $coords2){


        $playerA = (array) $coords1;
        $playerB = (array) $coords2;

        if (!$playerA || !$playerB) {
            die('Erreur: Impossible de récupérer les coordonnées des joueurs.');
        }

        $xA = $playerA['x'];
        $yA = $playerA['y'];
        $xB = $playerB['x'];
        $yB = $playerB['y'];


        // walls
        $db = new Db();

        $sql = '
        SELECT
        map_walls.id AS id,
        x,y
        FROM
        coords
        INNER JOIN
        map_walls
        ON
        coords.id = coords_id
        WHERE
        z = ?
        AND
        plan = ?
        AND
        x BETWEEN ? AND ?
        AND
        y BETWEEN ? AND ?
        ';

        $xMin = min($xA, $xB);
        $xMax = max($xA, $xB);
        $yMin = min($yA, $yB);
        $yMax = max($yA, $yB);


        $res = $db->exe($sql, array(
            $coords1->z,
            $coords1->plan,
            $xMin,
            $xMax,
            $yMin,
            $yMax
        ));

        if(!$res->num_rows){

            return false;
        }

        $wallsTbl = array();

        while($row = $res->fetch_object()){

            $wallsTbl[$row->x .','. $row->y] = $row->id;
        }


        // Fonction pour utiliser l'algorithme de Bresenham pour tracer une ligne
        function bresenham($x1, $y1, $x2, $y2) {
            $points = [];
            $dx = abs($x2 - $x1);
            $dy = abs($y2 - $y1);
            $sx = ($x1 < $x2) ? 1 : -1;
            $sy = ($y1 < $y2) ? 1 : -1;
            $err = $dx - $dy;

            while (true) {
                $points[] = [$x1, $y1];
                if ($x1 == $x2 && $y1 == $y2) break;
                $e2 = 2 * $err;
                if ($e2 > -$dy) {
                    $err -= $dy;
                    $x1 += $sx;
                }
                if ($e2 < $dx) {
                    $err += $dx;
                    $y1 += $sy;
                }
            }
            return $points;
        }


        // Tracer la ligne entre les deux joueurs
        $line_points = bresenham($xA, $yA, $xB, $yB);


        ob_start();


        ?>
        <script>

        alert("Un ou plusieurs obstacles gênent votre action.");
        $("#ui-card").hide();

        <?php

        // echo '';

        $obstacle = false;

        // Vérifier chaque point pour des obstacles
        foreach ($line_points as $point) {
            list($x, $y) = $point;

            if(!empty($wallsTbl[$x .','. $y])){


                $obstacle = true;

                ?>
                $('#walls'+ <?php echo $wallsTbl[$x .','. $y] ?>).addClass('blink');
                <?php
            }
        }

        ?>
        </script>
        <?php

        $js = ob_get_clean();


        if($obstacle){
            echo $js;
            exit();
        }
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


    public static function refresh_players_svg(object $coords,$p=20):void{
        // based on View::get_coords_id_arround that is the fastest implementation 
        $db = new Db();
        $minX = $coords->x - $p;
        $maxX = $coords->x + $p;
        $minY = $coords->y - $p;
        $maxY = $coords->y + $p;

        // delete svg cache
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
            AND c.plan = ?';

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
}
