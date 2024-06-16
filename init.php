<?php

// display php errors
error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE);


require_once('config.php');


$ui = new Ui('Init');


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


echo 'delete datas dir : players<br />';

$path = 'datas/private/players/';

$realpath = realpath($path);

rrmdir($realpath);


echo 'create new datas dir : players<br />';

mkdir($path, 0755, true);


echo 'run db/init.sql<br />';

$sql = file_get_contents('db/init.sql');

db()->multi_query($sql);


echo 'done!<br />';


echo '
<br />
press ² to show prompt cmd<br />
create player [character name] [character race]<br />
tp [character name or id] 0,0,0,[plan]<br />
session open [character name or id]<br />
<a href="index.php"><button>Then press this button</button></a>
';
