<?php
//delete anything at coords given.
$mapTypes = array('tiles','walls','triggers','elements','dialogs','plants');

$db = new Db();

foreach ($mapTypes as $type){
    $sql = 'DELETE FROM map_'.$type.' WHERE coords_id =?';

    $db->exe($sql, $coordsId);

}

