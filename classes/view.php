<?php

class View{

    private $coords; // Coordonnées de la vue
    private $p; // Portée de la vue
    private $tiled; // Indique si la vue est dans l'éditeur de map
    private $inSight; // Coordonnées des objets dans le champ de vision
    private $useTbl; // array qui permettra d'augmenter le z-level des images


    function __construct($coords, $p, $tiled=false){


        $this->coords = $coords;
        $this->p = $p;
        $this->tiled = $tiled;
        $this->inSight = $this->get_inSight();
        $this->useTbl = array();
    }


    public function get_inSight(){


        $minX = $this->coords->x - $this->p;
        $maxX = $this->coords->x + $this->p;
        $minY = $this->coords->y - $this->p;
        $maxY = $this->coords->y + $this->p;

        $sql = '
        SELECT id FROM coords
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
            $this->coords->z,
            $this->coords->plan
        ]);


        $return = array();

        while($row = $res->fetch_object()){

            $return[] = $row->id;
        }

        return $return;
    }

    public function get_map($table){


        $return = array();

        $sql = '
        SELECT
        p.*,
        c.id AS coordsId,
        x, y, z, plan
        FROM
        '. $table .' AS p
        INNER JOIN
        coords AS c
        ON
        p.coords_id = c.id
        WHERE
        p.coords_id IN ('. Db::print_in($this->inSight) .')
        ';

        $db = new Db();

        $params = array();

        foreach($this->inSight as $e){

            $params[] = $e;
        }

        $res = $db->exe($sql, $params);

        while($row = $res->fetch_object()){


            $return[] = $row;
        }

        return $return;
    }


    public function get_view(){


        $classTransparent = array();


        ob_start();


        $size = (($this->p * 2) + 1) * 50;


        $planJson = json()->decode('plans', $this->coords->plan);

        $tile = (!empty($planJson->bg)) ? $planJson->bg : 'img/tiles/'. $this->coords->plan .'.png';

        if($this->coords->z < 0){

            $tile = 'img/tiles/underground.png';
        }


        echo '
        <div id="view">
        <?xml version="1.0" encoding="UTF-8" standalone="no"?>
        <svg
            xmlns="http://www.w3.org/2000/svg"
            xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1"
            baseProfile="full"

            id="svg-view"

            width="'. $size .'"
            height="'. $size .'"

            style="background: url(\''. $tile .'\');"

            class="box-shadow"
            >
            ';


            echo $this->print_table('tiles');

            echo $this->print_table('plants');

            echo $this->print_table('items');


            $eleTbl = $this->get_map('map_elements');

            foreach($eleTbl as $row){


                $x = ($row->x - $this->coords->x + $this->p) * 50;
                $y = (-$row->y + $this->coords->y + $this->p) * 50;


                $typesTbl = array(
                    'gif'=>'0.3',
                    'webp'=>'0.5',
                    'png'=>'1'
                );


                foreach($typesTbl as $k=>$e){


                    $url = 'img/elements/'. $row->name .'.'. $k;

                    if(file_exists($url)){

                        echo '
                        <image

                            width="50"
                            height="50"

                            x="'. floor($x) .'"
                            y="'. floor($y) .'"

                            style="opacity: '. $e .';"

                            href="'. $url .'"
                            />
                        ';
                    }
                }


                $classTransparent[$x .','. $y] = 'transparent-gradient';
            }


            // plan exceptions
            if($planJson){


                $playersTbl = $this->get_map('players');
            }
            else{


                // solo
                $playersTbl = array(
                    'row'=>(object) array(
                        'id'=>$_SESSION['playerId'],
                        'x'=>$this->coords->x,
                        'y'=>$this->coords->y
                    )
                );
            }

            foreach($playersTbl as $row){


                $playerJson = json()->decode('players', $row->id);

                $x = ($row->x - $this->coords->x + $this->p) * 50;
                $y = (-$row->y + $this->coords->y + $this->p) * 50;

                echo '
                <image

                    width="50"
                    height="50"

                    x="'. floor($x) .'"
                    y="'. floor($y) .'"

                    href="'. $playerJson->avatar .'"

                    class="avatar-shadow"
                    />
                ';


                $playerClasses = array();

                if(!empty($classTransparent[$x .','. $y])){

                    $playerClasses[] = 'transparent-gradient';
                }

                echo '
                <image

                    width="50"
                    height="50"

                    x="'. floor($x) .'"
                    y="'. floor($y) .'"

                    href="'. $playerJson->avatar .'"

                    class="'. implode(' ', $playerClasses) .'"
                    />
                ';
            }


            // uses
            foreach($this->useTbl as $e){

                echo '<use xlink:href="#'. $e .'" />';
            }


            // walls
            echo $this->print_table('walls');


            if($this->tiled){

                // only for tiled

                echo $this->print_table('triggers');
                echo $this->print_table('dialogs');
            }


            // grid
            for ($i = 0; $i < $this->p*2+1; $i++) {

                for ($j = 0; $j < $this->p*2+1; $j++) {


                    $coordX = $i + $this->coords->x - $this->p;
                    $coordY = -$j + $this->coords->y + $this->p;

                    $x = $i * 50;
                    $y = $j * 50;

                    echo '
                    <image
                        data-coords="'. $coordX .','. $coordY .'"

                        x="' . $x . '"
                        y="' . $y . '"

                        href="img/ui/view/grid.png"
                        />
                    ';

                    echo '
                    <rect
                        class="case"
                        data-coords="'. $coordX .','. $coordY .'"

                        x="' . $x . '"
                        y="' . $y . '"

                        width="50"
                        height="50"

                        fill="transparent"
                        />
                    ';
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
                href="img/ui/view/arrow.png"
                />
            ';


            echo '
        </svg>
        </div>
        ';


        return ob_get_clean();
    }


    public function print_table($table){

        ob_start();

        $tbl = $this->get_map('map_'. $table);

        foreach($tbl as $row){


            $id = $table . $row->id;


            $x = ($row->x - $this->coords->x + $this->p) * 50;
            $y = (-$row->y + $this->coords->y + $this->p) * 50;


            if(!empty($row->foreground) && $row->foreground == 1){

                $this->useTbl[] = $id;
            }


            // items
            if($table == 'items'){


                echo '
                <image

                    id="'. $id .'"

                    width="50"
                    height="50"

                    x="'. floor($x) .'"
                    y="'. floor($y) .'"

                    href="img/tiles/loot.png"
                    />
                ';

                break;
            }


            echo '
            <image

                id="'. $id .'"

                width="50"
                height="50"

                x="'. floor($x) .'"
                y="'. floor($y) .'"

                href="img/'. $table .'/'. $row->name .'.png"
                />
            ';
        }


        return ob_get_clean();
    }


    // STATIC

    public static function get_coords_arround($coords, $p){


        $return = array();

        for ($i = 0; $i < $p*2+1; $i++) {

            for ($j = 0; $j < $p*2+1; $j++) {


                $coordX = $i + $coords->x - $p;
                $coordY = -$j + $coords->y + $p;

                $return[] = $coordX .','. $coordY;
            }
        }

        return $return;
    }


    public static function get_coords_id_arround($coords, $p){


        $return = array();

        $coordsArround = self::get_coords_arround($coords, $p);

        $sql = '
        SELECT
        id
        FROM
        coords
        WHERE
        CONCAT(x, ",", y) IN("'. implode('","', $coordsArround) .'")
        AND
        z = ?
        AND
        plan = ?
        ';

        $db = new Db();

        $res = $db->exe($sql, array($coords->z, $coords->plan));

        while($row = $res->fetch_object()){


            $return[] = $row->id;
        }

        return $return;
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


    public static function get_coords($table, $id){

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
            exit('error not same z');

        // not same plan error
        if($coords1['plan'] != $coords2['plan'])
            exit('error not same plan');


        $difX = abs($coords1['x'] - $coords2['x']) ;
        $difY = abs($coords1['y'] - $coords2['y']) ;

        if( $difX > $difY ) return $difX ;
        else return $difY ;
    }
}
