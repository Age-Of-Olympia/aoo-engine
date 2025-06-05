<?php
use Classes\Db;

//delete the case given on table given
$db = new Db();


$sql = 'DELETE FROM '.$type.' WHERE coords_id =?';

$db->exe($sql, $coordsId);

