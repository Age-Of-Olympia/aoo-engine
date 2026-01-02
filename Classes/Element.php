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

        // Log coords_id for debugging foreign key issues
        if ($coords_id === NULL || $coords_id === '') {
            error_log("[Element::put] WARNING: coords_id is NULL/empty for element '{$name}'");
            error_log("[Element::put] Stack trace: " . print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3), true));
            return; // Don't try to insert with NULL coords_id
        }

        $db = new Db();

        // CRITICAL: Validate that coords_id actually exists in database
        // This prevents foreign key constraint violations
        $result = $db->exe("SELECT id FROM coords WHERE id = ?", [$coords_id]);
        $coordsExists = $result && $result->num_rows > 0;
        if (!$coordsExists) {
            error_log("[Element::put] ERROR: coords_id {$coords_id} does not exist in database for element '{$name}'");
            error_log("[Element::put] Stack trace: " . print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5), true));
            return; // Don't try to insert with invalid coords_id
        }

        $sql = '
        INSERT INTO
        map_elements
        (`name`,`coords_id`,`endTime`)
        VALUE(?, ?, ?)
        ON DUPLICATE KEY UPDATE
        endTime = VALUES(endTime);
        ';

        $db->exe($sql, array($name, $coords_id, $endTime));
    }
}
