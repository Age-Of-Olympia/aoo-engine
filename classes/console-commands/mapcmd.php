<?php

class MapCmd extends AdminCommand
{
    public function __construct() {
        parent::__construct("map",[new Argument('action',true), new Argument('name',true)]);
        parent::setDescription(<<<EOT
permet de sauver/charger la map d'un plan
Exemple:
> map (affiche la liste des map sauvegardées)
> map save [nom_map] (sauvegarde la map actuelle)
> map load [nom_map] (remplace la map actuelle)
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {


        define('PATH', 'datas/private/maps/');

        if(!file_exists(PATH)){

            mkdir(PATH, 0755, true);
        }

        if(!isset($argumentValues[0])){

            return list_map();
        }

        if($argumentValues[0] == 'save'){

            return save_map($argumentValues);
        }

        if($argumentValues[0] == 'load'){

            return load_map($argumentValues);
        }
    }
}

function list_map(){


    ob_start();

    echo 'listing saved maps:<br />';

    $mapsFound = 0;

    foreach(File::scan_dir(PATH) as $file){


        echo $file .'<br />';

        $mapsFound++;
    }

    echo $mapsFound .' maps found in '. PATH;

    return ob_get_clean();
}

function save_map($argumentValues){


    ob_start();


    if(!isset($argumentValues[1])){

        echo '<font color="orange">error: missing argument [name], ie: "map save eryn_dolen"</font>';

        return ob_get_clean();
    }


    $name = $argumentValues[1];


    $player = new Player($_SESSION['playerId']);
    $player->get_coords();


    echo 'saving actual map:<br />';


    // $sql = '
    // SELECT *
    // FROM
    // coords
    // WHERE
    // plan = ?
    // ';
    //
    // $db = new Db();
    //
    // $res = $db->exe($sql, $player->coords->plan);
    //
    // $data = array();
    //
    // $coordsId = array();
    //
    // while($row = $res->fetch_object()){
    //
    //
    //     $data['coords'][] = $row;
    //
    //     $coordsId[] = $row->id;
    // }


    $data = array();


    foreach(array('tiles','walls','triggers','foregrounds','items','dialogs','plants','elements') as $table){


        if(!isset($data[$table])){


            $data[$table] = array();
        }

        $data[$table] = get_table($table, $player->coords->plan);
    }


    Json::write_json(PATH . $name .'.json', Json::encode($data));

    echo PATH . $name .'.json saved!';

    return ob_get_clean();
}

function load_map($argumentValues){


    ob_start();

    if (!isset($argumentValues[1])) {
        echo '<font color="orange">error: missing argument [name], ie: "map load eryn_dolen"</font>';
        return ob_get_clean();
    }

    $name = $argumentValues[1];
    $mapJson = json()->decode('maps', $name);

    if (!$mapJson) {
        echo '<font color="orange">error: ' . PATH . $name . '.json does not exist</font>';
        return ob_get_clean();
    }

    $player = new Player($_SESSION['playerId']);
    $player->get_coords();

    echo 'loading on actual map:<br />';

    $db = new Db();

    // Start a transaction
    $db->start_transaction("map_load");

    try {
        foreach ($mapJson as $k => $e) {
            $sql = '
            DELETE a
            FROM map_' . $k . ' AS a
            INNER JOIN
            coords AS b
            ON
            a.coords_id = b.id
            WHERE
            b.plan = ?
            ';

            $db->exe($sql, $player->coords->plan);

            $insertValues = array();
            $n = 0;
            $batchSize = 1000; // Define a batch size for inserts

            foreach ($e as $l => $f) {
                if (!$n) {
                    $keys = array_keys((array)$f);

                    foreach ($keys as $key => $val) {
                        if (in_array($val, array('x', 'y', 'z', 'player_id'))) {
                            unset($keys[$key]);
                        }
                    }

                    $keys[] = 'coords_id';
                    $structure = '(`' . implode('`,`', $keys) . '`)';
                }

                $coords = (object)array(
                    'x' => $f->x,
                    'y' => $f->y,
                    'z' => $f->z,
                    'plan' => $player->coords->plan
                );

                $f->coords_id = View::get_coords_id($coords);

                $insertVal = array();

                foreach ($keys as $g) {
                    $insertVal[] = '"' . addcslashes($f->$g, '"') . '"';
                }

                $insertValues[] = '(' . implode(',', $insertVal) . ')';
                $n++;

                // Perform batch insert
                if (count($insertValues) >= $batchSize) {
                    $sql = 'INSERT INTO map_' . $k . ' ' . $structure . ' VALUES ' . implode(', ', $insertValues) . ';';
                    $db->exe($sql);
                    $insertValues = array(); // Reset batch
                }
            }

            // Insert any remaining records
            if (count($insertValues) > 0) {
                $sql = 'INSERT INTO map_' . $k . ' ' . $structure . ' VALUES ' . implode(', ', $insertValues) . ';';
                $db->exe($sql);
            }

            echo $k . ' done (' . $n . ')<br />';
        }

        // Commit the transaction
        $db->commit_transaction("map_load");
        echo 'map successfully loaded.';
    } catch (Exception $e) {
        // Rollback the transaction in case of error
        $db->rollback_transaction("map_load");
        echo '<font color="orange">error: ' . $e->getMessage() . '</font>';
    }

    return ob_get_clean();
}

function get_table($table, $plan){


    $sql = '
    SELECT *
    FROM
    map_'. $table .'
    INNER JOIN
    coords
    ON
    coords.id = map_'. $table .'.coords_id
    WHERE
    coords.plan = ?
    ';

    $db = new Db();

    $res = $db->exe($sql, $plan);

    $data = array();

    while($row = $res->fetch_object()){


        unset($row->id);
        unset($row->coords_id);
        unset($row->plan);

        $data[] = $row;
    }

    return $data;
}

function get_inserts($table){


    ob_start();



    return ob_get_clean();
}
