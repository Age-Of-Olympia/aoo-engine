<?php

// display php errors
error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE);


require_once('config.php');


function rrmdir($dir) {
    if (is_dir($dir)) {
    $objects = scandir($dir);
    foreach ($objects as $object) {
        if ($object != "." && $object != "..") {
        if (filetype($dir."/".$object) == "dir")
            rrmdir($dir."/".$object);
        else unlink   ($dir."/".$object);
        }
    }
    reset($objects);
    rmdir($dir);
    }
}


echo 'deleting data : player<br />';

$path = 'datas/private/player/';

$realpath = realpath($path);

rrmdir($realpath);


echo 'create new data : player<br />';

mkdir($path, 0755, true);


echo 'running db/init.sql<br />';

$sql = file_get_contents('db/init.sql');

db()->multi_query($sql);


echo 'done!';

