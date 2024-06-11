<?php

class View{


    function __construct($coords, $p){


        $this->p = $p;
        $this->coords = $coords;


        $this->inSight = array();


        $minX = $coords->x - $p;
        $maxX = $coords->x + $p;
        $minY = $coords->y - $p;
        $maxY = $coords->y + $p;

        $sql = '
        SELECT * FROM
        coords
        WHERE
        x >= '. $minX .' AND x <= '. $maxX .'
        AND
        y >= '. $minY .' AND y <= '. $maxY .'
        AND
        z = '. $coords->z .'
        AND
        plan = ?
        ';

        $db = new Db();

        $res = $db->exe($sql, $coords->plan);

        while($row = $res->fetch_object()){

            $this->inSight[] = $row->id;
        }
    }


    public function get_map($table){


        $return = array();

        $sql = '
        SELECT
        p.id AS id,
        p.name AS name,
        c.id AS coordsId,
        x, y, z, plan
        FROM
        '. $table .' AS p
        INNER JOIN
        coords AS c
        ON
        p.coords_id = c.id
        WHERE
        p.coords_id IN('. implode(',', $this->inSight) .')
        ';

        $db = new Db();

        $res = $db->exe($sql);

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

        $tile = (!empty($planJson->background)) ? $planJson->background : 'img/tiles/'. $this->coords->plan .'.png';


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

            $bgTbl = $this->get_map('map_tiles');

            foreach($bgTbl as $row){


                $x = ($row->x - $this->coords->x + $this->p) * 50;
                $y = (-$row->y + $this->coords->y + $this->p) * 50;

                echo '
                <image

                    width="50"
                    height="50"

                    x="'. floor($x) .'"
                    y="'. floor($y) .'"

                    href="'. $row->name .'"
                    />
                ';
            }


            $eleTbl = $this->get_map('map_elements');

            foreach($eleTbl as $row){


                $x = ($row->x - $this->coords->x + $this->p) * 50;
                $y = (-$row->y + $this->coords->y + $this->p) * 50;


                if(file_exists('img/elements/'. $row->name .'.gif')){

                    echo '
                    <image

                        width="50"
                        height="50"

                        x="'. floor($x) .'"
                        y="'. floor($y) .'"

                        style="opacity: 0.3;"

                        href="img/elements/'. $row->name .'.gif"
                        />
                    ';
                }


                if(file_exists('img/elements/'. $row->name .'.webp')){

                    echo '
                    <image

                        width="50"
                        height="50"

                        x="'. floor($x) .'"
                        y="'. floor($y) .'"

                        style="opacity: 0.5;"

                        href="img/elements/'. $row->name .'.webp"
                        />
                    ';
                }


                if(file_exists('img/elements/'. $row->name .'.png')){

                    echo '
                    <image

                        width="50"
                        height="50"

                        x="'. floor($x) .'"
                        y="'. floor($y) .'"

                        href="img/elements/'. $row->name .'.png"
                        />
                    ';
                }


                $classTransparent[$x .','. $y] = 'transparent-gradient';
            }


            $playersTbl = $this->get_map('players');

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


            $wallsTbl = $this->get_map('map_walls');

            foreach($wallsTbl as $row){


                $x = ($row->x - $this->coords->x + $this->p) * 50;
                $y = (-$row->y + $this->coords->y + $this->p) * 50;

                echo '
                <image

                    width="50"
                    height="50"

                    x="'. floor($x) .'"
                    y="'. floor($y) .'"

                    href="img/walls/'. $row->name .'.png"
                    />
                ';
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


        $return = ob_get_contents();
        ob_end_clean();

        return $return;
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

    public static function get_coords_id($goCoords){

        $db = new Db();

        $sql = '
        SELECT id FROM coords WHERE x = ? AND y = ? AND z = ? AND plan = ?
        ';

        $res = $db->exe($sql, array(&$goCoords->x, &$goCoords->y, &$goCoords->z, &$goCoords->plan));


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
}
