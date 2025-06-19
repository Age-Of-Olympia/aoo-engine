<?php
use Classes\Db;

$db = new Db();

$infos ='';

$db = new Db();
$sql = "UPDATE map_walls 
    SET damages = CASE
    WHEN damages = 0 THEN -1
    WHEN damages = -1 THEN -2
    WHEN damages = -2 THEN -1
    ELSE damages END
     where coords_id = ?";
$db->exe($sql,$coordsId);



