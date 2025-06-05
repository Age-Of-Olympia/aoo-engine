<?php
namespace Classes;

class Element{


    public static function put($name, $coords, $duration=THREE_DAYS){


        if(!isset(EFFECTS_RA_FONT[$name])){

            exit('error element '. $name);
        }


        $endTime = time() + $duration;

        if(is_numeric($coords)){

            $coords_id = $coords;
        }
        else{

            $coords_id = View::get_coords_id($coords);
        }

        $sql = '
        INSERT INTO
        map_elements
        (`name`,`coords_id`,`endTime`)
        VALUE(?, ?, ?)
        ON DUPLICATE KEY UPDATE
        endTime = VALUES(endTime);
        ';

        $db = new Db();

        $db->exe($sql, array($name, $coords_id, $endTime));
    }
}
