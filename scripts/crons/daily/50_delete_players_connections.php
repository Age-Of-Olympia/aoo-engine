<?php
use Classes\Db;
/*
 * ce script les entrées dans players connections plus vieilles que 30 jours
 */

$sql = 'DELETE FROM players_connections
WHERE time < UNIX_TIMESTAMP(NOW() - INTERVAL 30 DAY);
';

$db = new Db();

$db->exe($sql);


echo 'done';
