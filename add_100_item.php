<?php


require_once('config.php');


$player = new Player($_SESSION['playerId']);

if(!$player->have_option('isAdmin')){

    exit('error admin');
}


$db = new Db();


$sql = 'DELETE FROM players_items WHERE player_id = ?';

$db->exe($sql, $player->id);


$sql = '
INSERT INTO
players_items
(`item_id`,`player_id`,`n`)
SELECT
`id`, ?, "100"
FROM
items
';


$db->exe($sql, $player->id);


echo 'done!';
