<?php
use Classes\AdminCommand;
use Classes\Argument;
use Classes\Db;
use Classes\File;
use Classes\Json;
use Classes\Player;
use Classes\View;

class MapCmd extends AdminCommand

{
    private $tables = array('tiles', 'routes', 'walls', 'triggers', 'foregrounds', 'items', 'dialogs', 'plants', 'elements');

    public function __construct() {
        parent::__construct("map",[new Argument('action',true), new Argument('name',true)]);
        parent::setDescription(<<<EOT
permet de sauver/charger la map d'un plan
Exemple:
> map (affiche la liste des map sauvegardées)
> map save [nom_map] [num_element] (sauvegarde la map actuelle et découpe les fichiers en fonction du nombre d'éléments indiqué)
> map load [nom_map] (remplace la map actuelle, gère les fichiers en plusieurs parties et permet de reprendre le chargement en cas d'échec)
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {
        define('PATH', 'datas/private/maps/');

        if(!file_exists(PATH)){

            mkdir(PATH, 0755, true);
        }

        if(!isset($argumentValues[0])){
            return $this->list_map();
        }

        if($argumentValues[0] == 'save'){
            return $this->save_map($argumentValues);
        }

        if($argumentValues[0] == 'load'){
            return $this->load_map($argumentValues);
        }
        return false;
    }


    private function list_map() {
        ob_start();
    
        echo 'Listing saved maps:<br />';
    
        $mapsFound = 0;
        $mapNames = array();
    
        // Scan the directory for map files
        foreach (File::scan_dir(PATH) as $file) {
            // Check if the file is a map file (complete or part)
            if (preg_match('/^(.+?)(\_(tiles|routes|walls|triggers|foregrounds|items|dialogs|plants|elements))?(\_part\_\d+)?\.json$/', $file, $matches)) {
                $baseName = $matches[1];
                if (!in_array($baseName, $mapNames)) {
                    $mapNames[] = $baseName;
                    echo 'Map: ' . $baseName . '<br />';
                    $mapsFound++;
    
                    // List all parts of the map across all tables
                    foreach (File::scan_dir(PATH) as $partFile) {
                        if (preg_match('/^' . preg_quote($baseName) . '(\_(tiles|routes|walls|triggers|foregrounds|items|dialogs|plants|elements))?(\_part\_\d+)?\.json$/', $partFile)) {
                            echo ' - ' . $partFile . '<br />';
                        }
                    }
                }
            }
        }
    
        echo $mapsFound . ' maps found in ' . PATH;
    
        return ob_get_clean();
    }
    
    
    


private function save_map($argumentValues) {
    ob_start();

    if (!isset($argumentValues[1])) {
        echo '<font color="orange">error: missing argument [name], ie: "map save eryn_dolen"</font>';
        return ob_get_clean();
    }

    $name = $argumentValues[1];
    $maxElementsPerFile = isset($argumentValues[2]) ? (int)$argumentValues[2] : 1000; // Default or specified max elements per file

    $player = new Player($_SESSION['playerId']);
    $player->getCoords();

    echo 'saving actual map:<br />';

    $data = array();

    foreach ($this->tables as $table) {
        if (!isset($data[$table])) {
            $data[$table] = array();
        }
        $data[$table] = $this->get_table($table, $player->coords->plan);
    }

    foreach ($data as $table => $tableData) {
        $fileIndex = 0;
        $chunks = array_chunk($tableData, $maxElementsPerFile); // Split the array into chunks

        foreach ($chunks as $chunk) {
            $fileName = PATH . $name . '_' . $table . '_part_' . $fileIndex . '.json';
            Json::write_json($fileName, Json::encode(array($table => $chunk)));
            echo $fileName . ' saved!<br />';
            $fileIndex++;
        }
    }

    return ob_get_clean();
}

function load_map($argumentValues) {
    ob_start();

    if (!isset($argumentValues[1])) {
        echo '<font color="orange">error: missing argument [name], ie: "map load eryn_dolen"</font>';
        ob_flush();
        flush();
        return ob_get_clean();
    }

    $name = $argumentValues[1];
    $player = new Player($_SESSION['playerId']);
    $player->getCoords();

    echo 'Loading on actual map:<br />';
    ob_flush();
    flush();

    $db = new Db();
    $progressFile = PATH . $name . '_progress.json';

    // Read the progress file to determine the last successfully imported part for each table
    $progress = array();
    if (file_exists($progressFile)) {
        $progress = json_decode(file_get_contents($progressFile), true);
    }

    $tables = array('tiles', 'routes', 'walls', 'triggers', 'foregrounds', 'items', 'dialogs', 'plants', 'elements');

    // Check if it's a single-file map
    if (file_exists(PATH . $name . '.json')) {
        $mapJson = json_decode(file_get_contents(PATH . $name . '.json'), true);

        if ($mapJson) {
            $db->start_transaction("map_load");
            echo 'Begin transaction for single-file map.<br />';
            ob_flush();
            flush();

            try {
                foreach ($tables as $table) {
                    if (isset($mapJson[$table])) {
                        $data = $mapJson[$table];
                        $insertValues = array();
                        $n = 0;

                        foreach ($data as $item) {
                            if ($n == 0) {
                                $keys = array_keys($item);
                                foreach ($keys as $key => $val) {
                                    if (in_array($val, array('x', 'y', 'z', 'player_id'))) {
                                        unset($keys[$key]);
                                    }
                                }
                                $keys[] = 'coords_id';
                                $structure = '(`' . implode('`,`', $keys) . '`)';
                            }

                            $coords = (object)array(
                                'x' => $item['x'],
                                'y' => $item['y'],
                                'z' => $item['z'],
                                'plan' => $player->coords->plan
                            );

                            $item['coords_id'] = View::get_coords_id($coords);

                            $insertVal = array();
                            foreach ($keys as $g) {
                                $insertVal[] = '"' . addcslashes($item[$g], '"') . '"';
                            }
                            $insertValues[] = '(' . implode(',', $insertVal) . ')';
                            $n++;
                        }

                        if (count($insertValues)) {
                            $sql = 'INSERT INTO map_' . $table . ' ' . $structure . ' VALUES ' . implode(', ', $insertValues) . ';';
                            $db->exe($sql);
                        }

                        echo $table . ' done (' . $n . ')<br />';
                        ob_flush();
                        flush();
                    }
                }

                // Commit the transaction for the single-file map
                $db->commit_transaction("map_load");
                echo 'End transaction for single-file map.<br />';
                ob_flush();
                flush();

            } catch (Exception $e) {
                $db->rollback_transaction("map_load");
                echo '<font color="orange">error: ' . $e->getMessage() . '</font><br />';
                ob_flush();
                flush();
                return ob_get_clean();
            }
        } else {
            echo '<font color="orange">error: Invalid JSON in single-file map.</font><br />';
            ob_flush();
            flush();
            return ob_get_clean();
        }
    } else {
        // Handle multi-part maps
        while (true) {
            $anyPartLoaded = false; // Flag to check if any part was loaded in this iteration

            foreach ($tables as $table) {
                $fileIndex = isset($progress[$table]) ? $progress[$table] : 0;
                $partFileName = $name . '_' . $table . '_part_' . $fileIndex;
                echo 'Searching for file: ' . $partFileName . '<br />';
                ob_flush();
                flush();

                if (!file_exists(PATH . $partFileName . '.json')) {
                    echo 'File: ' . $partFileName . ' not found.<br />';
                    ob_flush();
                    flush();
                    continue; // Skip to the next table if this part file doesn't exist
                }

                $mapJson = json()->decode('maps', $partFileName);

                if (!$mapJson) {
                    echo 'Invalid Json in file: ' . $partFileName . '.json' . '<br />';
                    ob_flush();
                    flush();
                    continue; // Skip to the next table if the JSON is invalid
                }

                $db->start_transaction("map_load");
                echo 'Begin transaction for ' . $table . ' part ' . $fileIndex . '.json' . '.<br />';
                ob_flush();
                flush();

                try {
                    foreach ($mapJson as $data) {
                        if ($fileIndex == 0) {
                            // Delete existing data only for the first part or complete file
                            $sql = '
                            DELETE a
                            FROM map_' . $table . ' AS a
                            INNER JOIN
                            coords AS b
                            ON
                            a.coords_id = b.id
                            WHERE
                            b.plan = ?
                            ';

                            $db->exe($sql, $player->coords->plan);
                        }

                        $insertValues = array();
                        $n = 0;

                        foreach ($data as $item) {
                            if ($n == 0) {
                                $keys = array_keys((array)$item);
                                foreach ($keys as $key => $val) {
                                    if (in_array($val, array('x', 'y', 'z', 'player_id'))) {
                                        unset($keys[$key]);
                                    }
                                }
                                $keys[] = 'coords_id';
                                $structure = '(`' . implode('`,`', $keys) . '`)';
                            }

                            $coords = (object)array(
                                'x' => $item->x,
                                'y' => $item->y,
                                'z' => $item->z,
                                'plan' => $player->coords->plan
                            );

                            $item->coords_id = View::get_coords_id($coords);

                            $insertVal = array();
                            foreach ($keys as $g) {
                                $insertVal[] = '"' . addcslashes($item->$g, '"') . '"';
                            }
                            $insertValues[] = '(' . implode(',', $insertVal) . ')';
                            $n++;
                        }

                        if (count($insertValues)) {
                            $sql = 'INSERT INTO map_' . $table . ' ' . $structure . ' VALUES ' . implode(', ', $insertValues) . ';';
                            $db->exe($sql);
                        }

                        echo $table . ' part ' . $fileIndex . ' done (' . $n . ')<br />';
                        ob_flush();
                        flush();
                    }

                    // Commit the transaction for this part
                    $db->commit_transaction("map_load");
                    echo 'End transaction for ' . $table . ' part ' . $fileIndex . '.<br />';
                    ob_flush();
                    flush();

                    // Update the progress file for this table and part
                    $progress[$table] = $fileIndex + 1;
                    file_put_contents($progressFile, json_encode($progress));
                    $anyPartLoaded = true;
                } catch (Exception $e) {
                    $db->rollback_transaction("map_load");
                    echo '<font color="orange">error: ' . $e->getMessage() . '</font><br />';
                    ob_flush();
                    flush();
                    echo 'Progress saved. You can resume the process later.<br />';
                    ob_flush();
                    flush();
                    return ob_get_clean();
                }
            }

            if (!$anyPartLoaded) {
                // No more parts to load
                break;
            }
        }
    }

    // Remove the progress file after successful completion
    if (file_exists($progressFile)) {
        unlink($progressFile);
    }

    echo 'Map successfully loaded.';
    ob_flush();
    flush();
    return ob_get_clean();
}


private function get_table($table, $plan){

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

}
